const ESTADO_ENTREGADA = 5;
fetch('sesion.php')
    .then(res => res.json())
    .then(d => {
        if (!d.admin) {
            localStorage.removeItem("usuarioElectroHogar");
            window.location.href = 'login.html';
        }
    })
    .catch(() => { window.location.href = 'login.html'; });


function mostrarToast(mensaje, esError = false) {
    let cont = document.getElementById("toast-container");
    if (!cont) {
        cont = document.createElement("div");
        cont.id = "toast-container";
        cont.className = "toast-container";
        document.body.appendChild(cont);
    }
    const el = document.createElement("div");
    el.className = "toast-msg" + (esError ? " error" : "");
    el.textContent = mensaje;
    cont.appendChild(el);
    setTimeout(() => { el.remove(); }, 3200);
}

function escaparHTML(texto) {
    return String(texto ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener("DOMContentLoaded", () => {
    cargarInventario();
    cargarOrdenesAdmin();
});

function cambiarPestana(idModulo, boton) {
    document.querySelectorAll('.modulo').forEach(mod => mod.classList.remove('activo'));
    document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('activo'));

    document.getElementById(idModulo).classList.add('activo');
    boton.classList.add('activo');
}

let productosGlobalesAdmin = [];
let sedesAdmin = [];
let stockPorSede = {};
function cargarInventario() {
    Promise.all([
        fetch('obtener_productos.php').then(res => res.json()),
        fetch('obtener_stock_sedes.php').then(res => res.json())
    ])
    .then(([productos, datosStock]) => {
        productosGlobalesAdmin = productos;
        sedesAdmin = datosStock.sedes || [];
        stockPorSede = datosStock.stock || {};
        construirEncabezadoInventario();
        construirCamposNuevoProducto();
        renderizarTablaInventario(productosGlobalesAdmin);
    })
    .catch(error => {
        console.error("Error al cargar productos:", error);
        let tabla = document.getElementById("tabla-stock");
        if (tabla) tabla.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center;">Error de conexión con la base de datos.</td></tr>`;
    });
}

function construirEncabezadoInventario() {
    let fila = document.getElementById("fila-encabezado-stock");
    if (!fila) return;
    let html = "<th>ID</th><th>Producto</th><th>Categoría</th><th>Precio</th>";
    sedesAdmin.forEach(sede => { html += `<th>Stock<br><small>${escaparHTML(sede.nombre)}</small></th>`; });
    html += "<th>Acciones</th>";
    fila.innerHTML = html;
}

function construirCamposNuevoProducto() {
    let contenedor = document.getElementById("add-stocks");
    if (!contenedor) return;
    let html = "";
    sedesAdmin.forEach(sede => {
        html += `<div class="form-group"><label>Stock inicial - ${escaparHTML(sede.nombre)}</label><input type="number" min="0" id="add-stock-${sede.id}"></div>`;
    });
    contenedor.innerHTML = html;
}

function renderizarTablaInventario(productos) {
    let tabla = document.getElementById("tabla-stock");
    if (!tabla) return;

    let columnas = 5 + sedesAdmin.length;
    if (productos.length === 0) {
        tabla.innerHTML = `<tr><td colspan="${columnas}" style="text-align:center; padding: 20px;">No se encontraron productos.</td></tr>`;
        return;
    }

    let html = "";
    productos.forEach(prod => {
        let celdasStock = "";
        sedesAdmin.forEach(sede => {
            let cantidad = (stockPorSede[prod.id] && stockPorSede[prod.id][sede.id] !== undefined) ? stockPorSede[prod.id][sede.id] : 0;
            celdasStock += `<td><input type="number" min="0" id="stock-${prod.id}-${sede.id}" class="input-stock" value="${escaparHTML(cantidad)}"></td>`;
        });

        html += `
            <tr>
                <td>${prod.id}</td>
                <td>${escaparHTML(prod.nombre)}</td>
                <td style="text-transform: capitalize;">${escaparHTML(prod.categoria)}</td>
                <td>$<input type="number" min="0" step="any" id="precio-${prod.id}" class="input-stock" style="width: 80px;" value="${escaparHTML(prod.precio)}"></td>
                ${celdasStock}
                <td style="display: flex; gap: 5px;">
                    <button class="btn btn-update" onclick="actualizarProducto(${prod.id})" title="Guardar precio y stock">💾</button>
                    <button class="btn btn-logout" style="margin: 0; padding: 8px;" onclick="eliminarProducto(${prod.id})" title="Eliminar">🗑️</button>
                </td>
            </tr>
        `;
    });
    tabla.innerHTML = html;
}

function filtrarInventario() {
    let textoBusqueda = document.getElementById("buscador-admin").value.toLowerCase();
    let categoriaSeleccionada = document.getElementById("filtro-categoria-admin").value;

    let productosFiltrados = productosGlobalesAdmin.filter(prod => {
        let coincideNombre = prod.nombre.toLowerCase().includes(textoBusqueda);
        let coincideCategoria = categoriaSeleccionada === "todos" || prod.categoria === categoriaSeleccionada;
        return coincideNombre && coincideCategoria;
    });

    renderizarTablaInventario(productosFiltrados);
}

function actualizarProducto(idProducto) {
    let nuevoPrecio = document.getElementById(`precio-${idProducto}`).value;

    let stocks = {};
    sedesAdmin.forEach(sede => {
        stocks[sede.id] = document.getElementById(`stock-${idProducto}-${sede.id}`).value || 0;
    });

    let formData = new FormData();
    formData.append('id', idProducto);
    formData.append('precio', nuevoPrecio);
    formData.append('stocks', JSON.stringify(stocks));

    fetch('actualizar_producto.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                mostrarToast("✅ Precio y stock por sucursal actualizados");
                cargarInventario();
            } else {
                alert("Error: " + data.error);
            }
        })
        .catch(() => alert("Error de conexión al actualizar el producto."));
}

function eliminarProducto(idProducto) {
    if (confirm("⚠️ ¿Estás seguro de que deseas eliminar este producto de la base de datos?")) {
        let formData = new FormData();
        formData.append('id', idProducto);

        fetch('eliminar_producto.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    mostrarToast("🗑️ Producto eliminado del inventario");
                    cargarInventario();
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(() => alert("Error de conexión al eliminar el producto."));
    }
}

function guardarNuevoProducto() {
    let nombre = document.getElementById("add-nombre").value;
    let precio = document.getElementById("add-precio").value;
    let categoria = document.getElementById("add-categoria").value;
    let precioAnterior = document.getElementById("add-precio-anterior") ? document.getElementById("add-precio-anterior").value : "";
    let imagen = document.getElementById("add-imagen") ? document.getElementById("add-imagen").value.trim() : "";
    let descripcion = document.getElementById("add-descripcion") ? document.getElementById("add-descripcion").value.trim() : "";

    if (nombre.trim() === "" || precio === "") {
        alert("Por favor, completa al menos el nombre y el precio.");
        return;
    }

    let stocks = {};
    sedesAdmin.forEach(sede => {
        let campo = document.getElementById(`add-stock-${sede.id}`);
        stocks[sede.id] = campo && campo.value !== "" ? campo.value : 0;
    });

    let formData = new FormData();
    formData.append("nombre", nombre);
    formData.append("precio", precio);
    formData.append("categoria", categoria);
    if (precioAnterior !== "") formData.append("precio_anterior", precioAnterior);
    if (imagen !== "") formData.append("imagen", imagen);
    if (descripcion !== "") formData.append("descripcion", descripcion);
    formData.append("stocks", JSON.stringify(stocks));

    fetch('guardar_producto.php', { method: 'POST', body: formData })
        .then(respuesta => respuesta.json())
        .then(datos => {
            if (datos.exito) {
                mostrarToast("✅ Nuevo producto creado y sincronizado en las 3 sedes");
                cerrarModal();
                cargarInventario();

                document.getElementById("add-nombre").value = "";
                document.getElementById("add-precio").value = "";
                if (document.getElementById("add-precio-anterior")) document.getElementById("add-precio-anterior").value = "";
                if (document.getElementById("add-imagen")) document.getElementById("add-imagen").value = "";
                if (document.getElementById("add-descripcion")) document.getElementById("add-descripcion").value = "";
            } else {
                alert("Error al guardar: " + datos.error);
            }
        })
        .catch(error => {
            console.error(error);
            alert("Error de conexión al intentar guardar.");
        });
}

function abrirModal() { document.getElementById("modal-producto").style.display = "flex"; }
function cerrarModal() { document.getElementById("modal-producto").style.display = "none"; }
function cargarOrdenesAdmin() {
    let contenedor = document.getElementById("tbody-ordenes");
    if (!contenedor) return;

    let selectFiltro = document.getElementById("filtro-tiempo");
    let filtro = selectFiltro ? selectFiltro.value : "historico";

    fetch('obtener_ordenes.php?filtro=' + encodeURIComponent(filtro))
        .then(res => res.json())
        .then(datos => {
            contenedor.innerHTML = "";

            if (!datos.exito) {
                contenedor.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center;">${escaparHTML(datos.error || "No se pudieron cargar las órdenes.")}</td></tr>`;
                return;
            }
            if (datos.ordenes.length === 0) {
                contenedor.innerHTML = `<tr><td colspan="6" style="text-align:center;">No hay órdenes para este período.</td></tr>`;
                return;
            }

            datos.ordenes.forEach(orden => {
                let tr = document.createElement("tr");
                let esCompletada = parseInt(orden.id_estado) === ESTADO_ENTREGADA || parseInt(orden.id_estado) === 2 || String(orden.estado_nombre || '').toLowerCase().indexOf('completad') !== -1;
                let claseBoton = esCompletada ? 'btn-secundario' : 'btn-primario';
                let estadoBoton = esCompletada ? 'disabled' : '';
                let estadoTexto = (orden.estado_nombre || 'SIN ESTADO').toUpperCase();

                tr.innerHTML = `
                    <td>${escaparHTML(orden.codigo_seguimiento || ('#EH-' + orden.id))}</td>
                    <td>${escaparHTML(orden.nombre_cliente)}</td>
                    <td>$${(parseFloat(orden.total) || 0).toLocaleString('es-AR')}</td>
                    <td>${escaparHTML(orden.metodo_envio)}</td>
                    <td><strong>${escaparHTML(estadoTexto)}</strong></td>
                    <td style="display:flex; gap:5px; justify-content:center;">
                        <button class="btn-secundario" onclick="verDetallesOrden(${orden.id})">🔍 Detalles</button>
                        <button class="${claseBoton}" ${estadoBoton} onclick="marcarOrdenEntregada(${orden.id})">✓ Completada</button>
                    </td>
                `;
                contenedor.appendChild(tr);
            });
        })
        .catch(error => {
            console.error(error);
            contenedor.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center;">Error de conexión al cargar las órdenes.</td></tr>`;
        });
}

function verDetallesOrden(idOrden) {
    fetch(`obtener_detalle_orden.php?id_orden=${idOrden}`)
        .then(res => res.json())
        .then(datos => {
            if (datos.exito) {
                document.getElementById("modal-orden-id").innerText = idOrden;
                let lista = document.getElementById("lista-detalles-orden");
                lista.innerHTML = "";

                if (datos.detalles.length === 0) {
                    lista.innerHTML = "<li>No hay productos registrados.</li>";
                } else {
                    datos.detalles.forEach(item => {
                        let subtotal = (item.cantidad * parseFloat(item.precio_unitario)).toFixed(2);
                        lista.innerHTML += `
                            <li style="border-bottom:1px solid #eee; padding:10px 0;">
                                <strong>${item.cantidad}x ${escaparHTML(item.nombre || 'Producto eliminado')}</strong><br>
                                <span style="font-size:0.9em; color:#555;">Precio: $${escaparHTML(item.precio_unitario)} | Subtotal: $${subtotal}</span>
                            </li>
                        `;
                    });
                }

                document.getElementById("modal-detalles-orden").style.display = "flex";
            } else {
                alert("Error al cargar los detalles.");
            }
        })
        .catch(error => console.error(error));
}

function cerrarModalDetalles() {
    document.getElementById("modal-detalles-orden").style.display = "none";
}

function marcarOrdenEntregada(idOrden) {
    fetch('actualizar_orden.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: idOrden, id_estado: ESTADO_ENTREGADA })
    })
    .then(res => res.json())
    .then(datos => {
        if (datos.exito) {
            cargarOrdenesAdmin();
            mostrarToast(`✅ Orden #EH-${idOrden} marcada como Completada`);
        } else {
            mostrarToast("Error al actualizar el estado en el servidor.", true);
        }
    })
    .catch(error => console.error(error));
}

function cerrarSesionAdmin() {
    if (confirm("¿Estás seguro que deseas salir del panel de administración?")) {
        localStorage.removeItem("usuarioElectroHogar");
        fetch('logout.php').catch(() => {}).finally(() => { window.location.href = "index.html"; });
    }
}
