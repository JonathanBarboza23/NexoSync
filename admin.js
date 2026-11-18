let productosGlobalesAdmin = [];
let sedesAdmin = [];
let estadosOrdenAdmin = [];
let ordenesGlobalesAdmin = [];

document.addEventListener("DOMContentLoaded", () => {
    cargarInventario();
    cargarOrdenes();
});

function cambiarPestana(idModulo, boton) {
    document.querySelectorAll('.modulo').forEach(m => m.classList.remove('activo'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('activo'));
    document.getElementById(idModulo).classList.add('activo');
    if (boton) boton.classList.add('activo');
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
                mostrarToast("Error: " + d.error, true);
            }
        })
        .catch(() => mostrarToast("Error al actualizar producto.", true));
}

function eliminarProducto(id) {
    let formData = new FormData();
    formData.append('id', id);

    fetch('eliminar_producto.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.exito) {
                mostrarToast("🗑️ Producto eliminado");
                cargarInventario();
            } else {
                mostrarToast("❌ " + d.error, true);
            }
        })
        .catch(() => mostrarToast("Error al eliminar producto.", true));
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
                ordenesGlobalesAdmin = Array.isArray(data.ordenes) ? data.ordenes : [];
                renderizarTablaOrdenes(ordenesGlobalesAdmin);
                renderizarEstadisticasCategorias(data.estadisticas_categorias || {});
                filtrarDetallesOrdenes();
            }
        });
}

function parsearFechaOrden(fechaStr) {
    if (!fechaStr) return null;
    let normalizada = String(fechaStr).trim().replace(" ", "T");
    let d = new Date(normalizada);
    if (isNaN(d.getTime())) {
        d = new Date(fechaStr);
    }
    return isNaN(d.getTime()) ? null : d;
}

function formatearFechaOrden(fechaStr) {
    let d = parsearFechaOrden(fechaStr);
    if (!d) return fechaStr || "Fecha no registrada";
    return d.toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function limpiarFiltrosDetallesOrdenes() {
    const inputBusq = document.getElementById("buscador-detalle-orden");
    const selectTiempo = document.getElementById("filtro-tiempo-ordenes");
    if (inputBusq) inputBusq.value = "";
    if (selectTiempo) selectTiempo.value = "completo";
    filtrarDetallesOrdenes();
}

function filtrarDetallesOrdenes() {
    const inputBusq = document.getElementById("buscador-detalle-orden");
    const selectTiempo = document.getElementById("filtro-tiempo-ordenes");
    const q = inputBusq ? inputBusq.value.trim().toLowerCase() : "";
    const periodo = selectTiempo ? selectTiempo.value : "completo";

    const ahora = new Date();
    const msDia = 24 * 60 * 60 * 1000;

    const filtradas = ordenesGlobalesAdmin.filter(o => {
        if (periodo === "7dias" || periodo === "mes") {
            const fechaOrd = parsearFechaOrden(o.fecha);
            if (fechaOrd) {
                const diasDif = (ahora.getTime() - fechaOrd.getTime()) / msDia;
                if (periodo === "7dias" && diasDif > 7) return false;
                if (periodo === "mes" && diasDif > 30) return false;
            }
        }

        if (q !== "") {
            const codigo = String(o.codigo_seguimiento || ("#" + o.id_orden)).toLowerCase();
            const idStr = String(o.id_orden || "").toLowerCase();
            const cliente = String(o.nombre_cliente || "").toLowerCase();
            const email = String(o.email_cliente || "").toLowerCase();
            const dni = String(o.dni_cliente || "").toLowerCase();
            const itemsMatch = Array.isArray(o.items) && o.items.some(it => String(it.nombre || "").toLowerCase().includes(q));

            if (!codigo.includes(q) && !idStr.includes(q) && !cliente.includes(q) && !email.includes(q) && !dni.includes(q) && !itemsMatch) {
                return false;
            }
        }
        return true;
    });

    renderizarDetallesOrdenes(filtradas);
}

function renderizarDetallesOrdenes(listaOrdenes) {
    const contKpis = document.getElementById("resumen-kpis-detalles");
    const contLista = document.getElementById("contenedor-lista-detalles-ordenes");
    if (!contLista) return;

    let totalOrdenes = listaOrdenes.length;
    let totalUnidadesVendidas = 0;
    let totalFacturadoPeriodo = 0;

    listaOrdenes.forEach(o => {
        const esCancelada = String(o.estado_nombre || "").toLowerCase().includes("cancel");
        if (!esCancelada) {
            totalFacturadoPeriodo += Number(o.total || 0);
        }
        if (Array.isArray(o.items)) {
            o.items.forEach(it => {
                if (!esCancelada) {
                    totalUnidadesVendidas += Number(it.cantidad || 0);
                }
            });
        }
    });

    if (contKpis) {
        contKpis.innerHTML = `
            <div class="kpi-detalle-card">
                <span>Órdenes Encontradas</span>
                <strong>${totalOrdenes}</strong>
            </div>
            <div class="kpi-detalle-card" style="border-left-color:#007bff;">
                <span>Unidades Vendidas</span>
                <strong>${totalUnidadesVendidas} unid.</strong>
            </div>
            <div class="kpi-detalle-card" style="border-left-color:#2e7d32;">
                <span>Total Facturado</span>
                <strong>$${totalFacturadoPeriodo.toLocaleString('es-AR')}</strong>
            </div>
        `;
    }

    if (listaOrdenes.length === 0) {
        contLista.innerHTML = `
            <div class="tarjeta-detalle-orden" style="text-align:center; padding:35px;">
                <p style="margin:0; font-size:15px; color:#666;">No se encontraron órdenes ni detalles de productos para los filtros seleccionados.</p>
            </div>
        `;
        return;
    }

    contLista.innerHTML = listaOrdenes.map(o => {
        const codigo = o.codigo_seguimiento || ('#' + o.id_orden);
        const estadoNombre = o.estado_nombre || 'En proceso';
        const fechaTexto = formatearFechaOrden(o.fecha);
        const modalidadTexto = (o.tipo_entrega === 'retiro')
            ? `🏪 Retiro en Sucursal (${escaparHTML(o.nombre_sede || 'Sucursal Central')})`
            : `🚚 Envío a Domicilio (${escaparHTML(o.direccion || 'Dirección registrada')})`;

        const items = Array.isArray(o.items) ? o.items : [];
        const cantTotalOrden = items.reduce((acc, it) => acc + Number(it.cantidad || 0), 0);

        const htmlItems = items.length > 0 ? items.map(it => {
            const sub = Number(it.subtotal !== undefined ? it.subtotal : (Number(it.cantidad) * Number(it.precio_unitario)));
            return `
                <div class="item-vendido-fila">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <img src="${escaparHTML(it.imagen || 'img/productos/placeholder.jpg')}" onerror="this.onerror=null;this.src='img/productos/placeholder.jpg';" class="item-vendido-img" alt="">
                        <div>
                            <strong style="font-size:14px; color:#111;">${escaparHTML(it.nombre)}</strong>
                            <div style="font-size:12.5px; color:#666;">
                                Categoría: <span style="text-transform:capitalize;">${escaparHTML(it.categoria || 'General')}</span> | Precio unitario: $${Number(it.precio_unitario).toLocaleString('es-AR')}
                            </div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="background:#000; color:#fff; padding:3px 9px; border-radius:12px; font-size:12px; font-weight:bold;">${Number(it.cantidad)} unid.</span>
                        <div style="font-size:14px; font-weight:700; color:#111; margin-top:4px;">Subtotal: $${sub.toLocaleString('es-AR')}</div>
                    </div>
                </div>
            `;
        }).join("") : `<p style="color:#777; font-size:13px; margin:6px 0;">Sin detalle de ítems registrado para esta orden.</p>`;

        return `
            <div class="tarjeta-detalle-orden">
                <div class="tarjeta-detalle-header">
                    <div>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <span style="font-size:17px; font-weight:800; color:#000;">Orden ${escaparHTML(codigo)}</span>
                            <span style="font-size:12.5px; color:#666;">(ID Interno: #${o.id_orden})</span>
                            <span class="${obtenerClaseEstadoBadge(estadoNombre)}">${escaparHTML(estadoNombre)}</span>
                        </div>
                        <div style="font-size:13px; color:#444; margin-top:6px;">
                            👤 <strong>Cliente:</strong> ${escaparHTML(o.nombre_cliente || 'Cliente')} (${escaparHTML(o.email_cliente || 'Sin email')}${o.dni_cliente ? ' - DNI: ' + escaparHTML(o.dni_cliente) : ''})
                        </div>
                        <div style="font-size:13px; color:#444; margin-top:3px;">
                            ${modalidadTexto} &nbsp;|&nbsp; 🕒 <strong>Fecha:</strong> ${escaparHTML(fechaTexto)}
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#666; text-transform:uppercase; font-weight:700;">Total de la Orden (${cantTotalOrden} prod.)</span>
                        <div style="font-size:20px; font-weight:800; color:#2e7d32; margin-top:2px;">$${Number(o.total).toLocaleString('es-AR')}</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:12.5px; font-weight:700; color:#333; text-transform:uppercase; margin-bottom:8px;">📦 Productos y Detalle de lo Vendido:</div>
                    ${htmlItems}
                </div>
            </div>
        `;
    }).join("");
}

function renderizarEstadisticasCategorias(statsMap) {
    const contOrdenes = document.getElementById("contenedor-estadisticas-ordenes");
    const contModulo = document.getElementById("contenedor-estadisticas-modulo");
    const ordenCategorias = ["cocina", "lavado", "tecnologia"];
    const iconosCategorias = {
        cocina: "🍳 Cocina",
        lavado: "🧺 Lavado",
        tecnologia: "💻 Tecnología"
    };

    const clavesDisponibles = Object.keys(statsMap || {});
    const listaClaves = ordenCategorias.filter(c => clavesDisponibles.includes(c))
        .concat(clavesDisponibles.filter(c => !ordenCategorias.includes(c)));

    if (listaClaves.length === 0) {
        const msgVacio = `<div class="stat-card"><p style="margin:0; color:#666; font-size:13.5px;">No hay datos de categorías disponibles para calcular estadísticas.</p></div>`;
        if (contOrdenes) contOrdenes.innerHTML = msgVacio;
        if (contModulo) contModulo.innerHTML = msgVacio;
        return;
    }

    const htmlCards = listaClaves.map(clave => {
        const info = statsMap[clave];
        const tituloCat = iconosCategorias[clave] || ("📦 " + clave.charAt(0).toUpperCase() + clave.slice(1));
        const topMas = Array.isArray(info.top_mas_vendidos) ? info.top_mas_vendidos : [];
        const topMenos = Array.isArray(info.top_menos_vendidos) ? info.top_menos_vendidos : [];
        const totalUnid = Number(info.total_unidades || 0);

        const htmlMas = topMas.map((prod, idx) => `
            <li class="stat-item mas">
                <span><strong>#${idx + 1}</strong> ${escaparHTML(prod.nombre)}</span>
                <span class="stat-cant">${Number(prod.total_vendido)} vend.</span>
            </li>
        `).join("");

        const htmlMenos = topMenos.map((prod, idx) => `
            <li class="stat-item menos">
                <span><strong>#${idx + 1}</strong> ${escaparHTML(prod.nombre)}</span>
                <span class="stat-cant">${Number(prod.total_vendido)} vend.</span>
            </li>
        `).join("");

        return `
            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>${escaparHTML(tituloCat)}</h4>
                    <span class="stat-badge-total">Total: ${totalUnid} unid. vendidas</span>
                </div>
                <div class="stat-subtitulo mas">🔥 Top 3 Más Vendidos</div>
                <ul class="stat-lista">${htmlMas}</ul>
                <div class="stat-subtitulo menos">📉 Top 3 Menos Vendidos</div>
                <ul class="stat-lista">${htmlMenos}</ul>
            </div>
        `;
    }).join("");

    if (contOrdenes) contOrdenes.innerHTML = htmlCards;
    if (contModulo) contModulo.innerHTML = htmlCards;
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