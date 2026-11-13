let productosGlobalesAdmin = [];
let sedesAdmin = [];
let estadosOrdenAdmin = [];

document.addEventListener("DOMContentLoaded", () => {
    cargarInventario();
    cargarOrdenes();
});

function cambiarPestana(idModulo, boton) {
    document.querySelectorAll('.modulo').forEach(m => m.classList.remove('activo'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('activo'));
    document.getElementById(idModulo).classList.add('activo');
    boton.classList.add('activo');
}

function cargarInventario() {
    fetch('obtener_stock_sedes.php')
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                sedesAdmin = data.sedes;
                renderizarEncabezadoStock(data.sedes);
                fetch('obtener_productos.php')
                    .then(r => r.json())
                    .then(prods => {
                        productosGlobalesAdmin = prods;
                        renderizarTablaInventario(prods, data.stock);
                    });
            }
        });
}

function renderizarEncabezadoStock(sedes) {
    const fila = document.getElementById("fila-encabezado-stock");
    let html = "<th>ID</th><th>Producto</th><th>Categoría</th><th>Precio</th>";
    sedes.forEach(s => {
        html += `<th>${escaparHTML(s.nombre)}</th>`;
    });
    html += "<th>Acciones</th>";
    fila.innerHTML = html;
}

function renderizarTablaInventario(productos, stockMap = {}) {
    const tabla = document.getElementById("tabla-stock");
    let html = "";
    productos.forEach(prod => {
        let celdasStock = sedesAdmin.map(s => {
            let cant = (stockMap[prod.id] && stockMap[prod.id][s.id] !== undefined) ? stockMap[prod.id][s.id] : 20;
            return `<td><input type="number" min="0" id="stock-${prod.id}-${s.id}" class="input-stock" value="${cant}"></td>`;
        }).join("");

        html += `
            <tr>
                <td>${prod.id}</td>
                <td>${escaparHTML(prod.nombre)}</td>
                <td style="text-transform: capitalize;">${escaparHTML(prod.categoria)}</td>
                <td>$<input type="number" min="0" step="any" id="precio-${prod.id}" class="input-stock" style="width: 90px;" value="${escaparHTML(prod.precio)}"></td>
                ${celdasStock}
                <td style="display: flex; gap: 6px;">
                    <button class="btn btn-update" onclick="actualizarProducto(${prod.id})" title="Guardar precio y stock">💾</button>
                    <button class="btn btn-logout" style="margin:0; padding:8px;" onclick="eliminarProducto(${prod.id})" title="Eliminar">🗑️</button>
                </td>
            </tr>
        `;
    });
    tabla.innerHTML = html;
}

function filtrarInventario() {
    let q = document.getElementById("buscador-admin").value.toLowerCase();
    let cat = document.getElementById("filtro-categoria-admin").value;
    let filtrados = productosGlobalesAdmin.filter(p => {
        let okN = p.nombre.toLowerCase().includes(q);
        let okC = cat === "todos" || p.categoria === cat;
        return okN && okC;
    });
    fetch('obtener_stock_sedes.php').then(r => r.json()).then(d => renderizarTablaInventario(filtrados, d.stock));
}

function actualizarProducto(id) {
    let nuevoPrecio = document.getElementById(`precio-${id}`).value;
    let stocks = {};
    sedesAdmin.forEach(s => {
        stocks[s.id] = document.getElementById(`stock-${id}-${s.id}`).value || 0;
    });

    let formData = new FormData();
    formData.append('id', id);
    formData.append('precio', nuevoPrecio);
    formData.append('stocks', JSON.stringify(stocks));

    fetch('actualizar_producto.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.exito) {
                mostrarToast("✅ Precio y stock actualizados");
                cargarInventario();
            } else {
                alert("Error: " + d.error);
            }
        })
        .catch(() => alert("Error al actualizar producto."));
}

function eliminarProducto(id) {
    if (confirm("⚠️ ¿Estás seguro de que deseas eliminar este producto?")) {
        let formData = new FormData();
        formData.append('id', id);

        fetch('eliminar_producto.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if (d.exito) {
                    mostrarToast("🗑️ Producto eliminado");
                    cargarInventario();
                } else {
                    alert("❌ " + d.error);
                }
            })
            .catch(() => alert("Error al eliminar producto."));
    }
}

function obtenerClaseEstadoBadge(nombreEstado) {
    let n = String(nombreEstado || '').toLowerCase();
    if (n.includes('cancel')) return 'badge-estado cancelado';
    if (n.includes('complet') || n.includes('entreg') || n.includes('confirm')) return 'badge-estado completado';
    return 'badge-estado proceso';
}

function cargarOrdenes() {
    fetch('obtener_ordenes.php')
        .then(r => r.json())
        .then(data => {
            if (data.exito) {
                if (Array.isArray(data.estados) && data.estados.length > 0) {
                    estadosOrdenAdmin = data.estados;
                } else {
                    estadosOrdenAdmin = [
                        { id_estado: 1, nombre_estado: "En proceso" },
                        { id_estado: 2, nombre_estado: "Completado" },
                        { id_estado: 3, nombre_estado: "Cancelado" }
                    ];
                }
                renderizarTablaOrdenes(data.ordenes);
            }
        });
}

function renderizarTablaOrdenes(ordenes) {
    const tbody = document.getElementById("tbody-ordenes");
    if (!tbody) return;
    if (!ordenes || ordenes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:25px; color:#666;">No hay órdenes registradas en el sistema.</td></tr>`;
        return;
    }
    tbody.innerHTML = ordenes.map(o => {
        const estadoActualNombre = o.estado_nombre || 'En proceso';
        const estadoActualId = Number(o.id_estado || 0);
        const opcionesEstado = estadosOrdenAdmin.map(est => {
            const seleccionado = (estadoActualId > 0 && Number(est.id_estado) === estadoActualId) ||
                                 (String(est.nombre_estado).toLowerCase() === String(estadoActualNombre).toLowerCase());
            return `<option value="${est.id_estado}" ${seleccionado ? 'selected' : ''}>${escaparHTML(est.nombre_estado)}</option>`;
        }).join("");

        const detalleEntrega = o.tipo_entrega === 'retiro' && o.nombre_sede
            ? `Retiro (${escaparHTML(o.nombre_sede)})`
            : escaparHTML(o.tipo_entrega || 'Envío');

        return `
            <tr>
                <td><strong>${escaparHTML(o.codigo_seguimiento || '#' + o.id_orden)}</strong></td>
                <td>${escaparHTML(o.nombre_cliente || 'Cliente')} (${escaparHTML(o.email_cliente || '')})</td>
                <td>$${Number(o.total).toLocaleString('es-AR')}</td>
                <td style="text-transform: capitalize;">${detalleEntrega}</td>
                <td><span id="badge-estado-orden-${o.id_orden}" class="${obtenerClaseEstadoBadge(estadoActualNombre)}">${escaparHTML(estadoActualNombre)}</span></td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <select id="select-estado-${o.id_orden}" class="select-estado-orden" onchange="cambiarEstadoOrden(${o.id_orden}, this.value)">
                            ${opcionesEstado}
                        </select>
                        <button type="button" class="btn-update" style="padding:7px 12px; font-size:12.5px;" onclick="cambiarEstadoOrden(${o.id_orden})">💾 Guardar</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

function cambiarEstadoOrden(idOrden, idEstadoParam) {
    const selectEl = document.getElementById(`select-estado-${idOrden}`);
    const idEstado = idEstadoParam !== undefined ? idEstadoParam : (selectEl ? selectEl.value : 0);
    const nombreEstado = selectEl && selectEl.options[selectEl.selectedIndex] ? selectEl.options[selectEl.selectedIndex].text : '';

    let formData = new FormData();
    formData.append('id_orden', idOrden);
    formData.append('id_estado', idEstado);
    formData.append('nombre_estado', nombreEstado);

    fetch('actualizar_orden.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.exito) {
                mostrarToast("✅ Estado de la orden actualizado a: " + nombreEstado);
                const badge = document.getElementById(`badge-estado-orden-${idOrden}`);
                if (badge && nombreEstado) {
                    badge.textContent = nombreEstado;
                    badge.className = obtenerClaseEstadoBadge(nombreEstado);
                }
                cargarOrdenes();
            } else {
                mostrarToast("❌ " + (d.error || "No se pudo actualizar el estado de la orden."), true);
            }
        })
        .catch(() => mostrarToast("❌ Error de conexión al actualizar la orden.", true));
}

function cerrarSesionAdmin() {
    fetch('logout.php').finally(() => window.location.href = 'index.html');
}
