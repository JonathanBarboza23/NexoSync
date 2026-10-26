let carrito = [];
let slideIndex = 0;
let catalogoGlobal = [];
let productosCargados = [];
function escaparHTML(texto) {
    return String(texto ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

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

function obtenerUsuarioActivo() {
    try {
        return JSON.parse(localStorage.getItem("usuarioElectroHogar"));
    } catch (e) {
        return null;
    }
}

function obtenerClaveCarrito() {
    let usuarioActivo = obtenerUsuarioActivo();
    return usuarioActivo ? "carrito_" + usuarioActivo.email : "carrito_invitado";
}

function cargarCarritoUsuario() {
    let usuarioActivo = obtenerUsuarioActivo();
    if (usuarioActivo && usuarioActivo.rol === 'admin') {
        carrito = [];
        actualizarContador();
        return;
    }

    let guardado = [];
    try {
        guardado = JSON.parse(localStorage.getItem(obtenerClaveCarrito())) || [];
    } catch (e) {
        guardado = [];
    }
    carrito = guardado.filter(item => item && (item.id || item.nombre));
    if (carrito.some(item => !item.id && item.nombre)) {
        fetch('obtener_productos.php')
            .then(r => r.json())
            .then(prods => {
                if (Array.isArray(prods)) {
                    carrito.forEach(item => {
                        if (!item.id && item.nombre) {
                            let enc = prods.find(p => p.nombre && p.nombre.toLowerCase() === item.nombre.toLowerCase());
                            if (enc) {
                                item.id = Number(enc.id);
                                if (!item.imagen) item.imagen = enc.imagen;
                            }
                        }
                    });
                    carrito = carrito.filter(item => item && item.id);
                    localStorage.setItem(obtenerClaveCarrito(), JSON.stringify(carrito));
                    actualizarContador();
                    if (document.getElementById("contenedor-items")) renderizarCarrito();
                }
            })
            .catch(() => {});
    }

    actualizarContador();
    if (document.getElementById("contenedor-items")) {
        renderizarCarrito();
    }
}

function sincronizarCarritoDB() {
    localStorage.setItem(obtenerClaveCarrito(), JSON.stringify(carrito));

    let usuarioActivo = obtenerUsuarioActivo();
    if (usuarioActivo && usuarioActivo.rol !== 'admin') {
        fetch('guardar_carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ carrito: JSON.stringify(carrito) })
        }).catch(e => console.error(e));
    }
}

function aplicarTemaGuardado() {
    let temaGuardado = localStorage.getItem("temaElectroHogar");
    let botonTema = document.getElementById("btn-tema");
    
    if (temaGuardado === "oscuro") {
        document.body.classList.add("dark-mode");
        if (botonTema) botonTema.innerText = "☀️";
    }
}

function cambiarTema() {
    let cuerpo = document.body;
    let botonTema = document.getElementById("btn-tema");
    
    cuerpo.classList.toggle("dark-mode");
    
    if (cuerpo.classList.contains("dark-mode")) {
        localStorage.setItem("temaElectroHogar", "oscuro");
        if (botonTema) botonTema.innerText = "☀️";
    } else {
        localStorage.setItem("temaElectroHogar", "claro");
        if (botonTema) botonTema.innerText = "🌙";
    }
}

function actualizarContador() {
    let contador = document.getElementById("contador-carrito");
    if (contador) {
        let totalItems = carrito.reduce((sum, item) => sum + item.cantidad, 0);
        contador.innerText = totalItems;
    }
}

function agregarAlCarrito(id) {
    let prod = productosCargados.find(p => Number(p.id) === Number(id));
    if (!prod) return;

    let item = carrito.find(p => Number(p.id) === Number(id));
    if (item) {
        item.cantidad += 1;
    } else {
        carrito.push({
            id: prod.id,
            nombre: prod.nombre,
            precio: Number(prod.precio),
            imagen: prod.imagen || 'img/productos/placeholder.jpg',
            cantidad: 1
        });
    }

    sincronizarCarritoDB();
    actualizarContador();
    mostrarToast(`🛒 ¡${prod.nombre} agregado al carrito!`);
}

function procesarRegistro(evento) {
    evento.preventDefault();
    let btn = document.getElementById("btn-submit-registro");
    if (btn) { btn.disabled = true; btn.innerText = "Registrando..."; }

    let datos = {
        nombre: document.getElementById("reg-nombre").value.trim(),
        dni: document.getElementById("reg-dni").value.trim(),
        email: document.getElementById("reg-email").value.trim(),
        password: document.getElementById("reg-password").value.trim()
    };

    fetch('registro_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(res => res.json())
    .then(data => {
        if (data.exito) {
            mostrarToast("✅ ¡Cuenta creada con éxito! Ahora puedes iniciar sesión.");
            setTimeout(() => { window.location.href = "login.html"; }, 900);
        } else {
            mostrarToast("❌ " + data.error, true);
            if (btn) { btn.disabled = false; btn.innerText = "Registrarme"; }
        }
    })
    .catch(() => {
        mostrarToast("No se pudo conectar con el servidor. Intenta de nuevo.", true);
        if (btn) { btn.disabled = false; btn.innerText = "Registrarme"; }
    });
}

function renderizarCarrito() {
    let contenedor = document.getElementById("contenedor-items");
    let spanSubtotal = document.getElementById("resumen-subtotal");
    let spanTotal = document.getElementById("resumen-total");
    let btnPagar = document.getElementById("btn-ir-pagar");

    if (!contenedor) return;

    function mostrarTotales(valor) {
        let texto = "$" + valor.toLocaleString('es-AR');
        if (spanSubtotal) spanSubtotal.innerText = texto;
        if (spanTotal) spanTotal.innerText = texto;
    }

    if (carrito.length === 0) {
        contenedor.innerHTML = "<p>Tu carrito está vacío. Explora el catálogo para agregar productos.</p>";
        mostrarTotales(0);
        if (btnPagar) { btnPagar.disabled = true; btnPagar.innerText = "FINALIZAR COMPRA"; }
        return;
    }

    fetch('verificar_stock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ productos: carrito.map(item => item.id) })
    })
    .then(res => res.json())
    .then(datos => {
        contenedor.innerHTML = "";
        let total = 0;
        let carritoBloqueado = false;

        carrito.forEach((item, index) => {
            let stockReal = Number(datos.stock[item.id] ?? 0);
            let sinStock = stockReal < item.cantidad;
            if (sinStock) carritoBloqueado = true;

            let estiloOpacidad = sinStock ? "opacity: 0.6;" : "";
            let mensajeError = "";
            if (sinStock) {
                mensajeError = stockReal === 0
                    ? `<p style="color: #e53935; font-weight: bold; font-size: 13px; margin-top: 5px;">⚠️ AGOTADO - Elimina este producto para continuar</p>`
                    : `<p style="color: #e53935; font-weight: bold; font-size: 13px; margin-top: 5px;">⚠️ Solo quedan ${stockReal} unidades - Reduce la cantidad</p>`;
            }

            if (!sinStock) {
                total += item.precio * item.cantidad;
            }

            let rutaImg = item.imagen || 'img/productos/placeholder.jpg';
            let subtotalItem = (Number(item.precio) || 0) * item.cantidad;

            contenedor.innerHTML += `
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ddd; padding:15px 0; ${estiloOpacidad}">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <img src="${escaparHTML(rutaImg)}" onerror="this.onerror=null;this.src='img/productos/placeholder.jpg';" alt="" style="width:64px; height:64px; object-fit:contain; background:#fff; border:1px solid #eee; border-radius:6px; padding:4px;">
                        <div>
                            <h4 style="margin:0;">${escaparHTML(item.nombre)}</h4>
                            <p style="margin:5px 0 0 0;">$${Number(item.precio).toLocaleString('es-AR')} x ${item.cantidad} = <strong>$${subtotalItem.toLocaleString('es-AR')}</strong></p>
                            ${mensajeError}
                        </div>
                    </div>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" onclick="cambiarCantidad(${index}, ${item.cantidad - 1})" style="padding:6px 12px; cursor:pointer; font-weight:bold;">−</button>
                        <button type="button" onclick="cambiarCantidad(${index}, ${item.cantidad + 1})" style="padding:6px 12px; cursor:pointer; font-weight:bold;">+</button>
                        <button type="button" class="btn-eliminar" onclick="eliminarItem(${index})" style="background:#dc3545; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            `;
        });

        mostrarTotales(total);

        if (btnPagar) {
            btnPagar.disabled = carritoBloqueado;
            btnPagar.innerText = carritoBloqueado ? "Corrija su carrito" : "FINALIZAR COMPRA";
            btnPagar.style.backgroundColor = carritoBloqueado ? "#6c757d" : "";
        }
    })
    .catch(() => {
        contenedor.innerHTML = "<p style='color:red;'>No se pudo verificar el stock. Intenta recargar la página.</p>";
    });
}

function cambiarCantidad(index, nuevaCantidad) {
    nuevaCantidad = parseInt(nuevaCantidad);
    if (!carrito[index] || nuevaCantidad < 1) return;
    carrito[index].cantidad = nuevaCantidad;
    sincronizarCarritoDB();
    actualizarContador();
    renderizarCarrito();
}

function eliminarItem(index) {
    carrito.splice(index, 1);
    sincronizarCarritoDB();
    actualizarContador();
    renderizarCarrito();
}

function sincronizarSesionServidor() {
    fetch('sesion.php')
        .then(res => res.json())
        .then(d => {
            let local = obtenerUsuarioActivo();
            if (local && !d.logueado) {
                localStorage.removeItem("usuarioElectroHogar");
                verificarEstadoSesionUI();
            } else if (d.logueado && d.rol !== "admin") {
                let actualizado = {
                    id: d.usuario_id || (local ? local.id : null),
                    nombre: d.nombre || (local ? local.nombre : ""),
                    email: d.email || (local ? local.email : ""),
                    dni: d.dni || (local ? local.dni : ""),
                    rol: d.rol || (local ? local.rol : "cliente")
                };
                localStorage.setItem("usuarioElectroHogar", JSON.stringify(actualizado));
                verificarEstadoSesionUI();
                if (document.getElementById("seccion-checkout")) {
                    bloquearDatosClienteCheckout(actualizado);
                }
            }
        })
        .catch(() => {});
}

function verificarEstadoSesion() {
    sincronizarSesionServidor();
    verificarEstadoSesionUI();
}

function verificarEstadoSesionUI() {
    let usuarioActivo = obtenerUsuarioActivo();
    let btnLogin = document.getElementById("btn-login");
    let btnAdmin = document.getElementById("btn-panel-admin");
    let btnCarrito = document.getElementById("btn-carrito");

    if (usuarioActivo) {
        let primerNombre = (usuarioActivo.nombre || "Usuario").split(" ")[0];
        if (btnLogin) {
            btnLogin.innerHTML = `Salir (${escaparHTML(primerNombre)})`;
            btnLogin.onclick = cerrarSesion;
        }
        if (usuarioActivo.rol === 'admin') {
            if (btnAdmin) btnAdmin.style.display = "inline-flex";
            if (btnCarrito) btnCarrito.style.display = "none";
        } else {
            if (btnAdmin) btnAdmin.style.display = "none";
            if (btnCarrito) btnCarrito.style.display = "inline-flex";
        }
    } else {
        if (btnLogin) {
            btnLogin.innerHTML = "👤 Iniciar Sesión";
            btnLogin.onclick = iniciarSesion;
        }
        if (btnAdmin) btnAdmin.style.display = "none";
        if (btnCarrito) btnCarrito.style.display = "inline-flex";
    }
}

function iniciarSesion() {
    window.location.href = "login.html";
}

function cerrarSesion() {
    if (confirm("¿Deseas cerrar tu sesión actual?")) {
        localStorage.removeItem(obtenerClaveCarrito());
        localStorage.removeItem("usuarioElectroHogar");
        fetch('logout.php').catch(() => {}).finally(() => window.location.reload());
    }
}

function procesarLogin(evento) {
    evento.preventDefault();
    let btn = document.querySelector("button[type='submit']");
    if(btn) { btn.disabled = true; btn.innerText = "⏳ Verificando..."; }

    let emailInput = document.getElementById("email").value.trim();
    let passInput = document.getElementById("password").value.trim();
    let formData = new FormData();
    formData.append("email", emailInput);
    formData.append("password", passInput);

    fetch('login.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(datos => {
            if (datos.exito) {
                localStorage.setItem("usuarioElectroHogar", JSON.stringify({
                    id: datos.usuario.id,
                    nombre: datos.usuario.nombre,
                    email: datos.usuario.email,
                    rol: datos.usuario.rol,
                    dni: datos.usuario.dni
                }));

                if (datos.usuario.carrito && datos.usuario.rol !== 'admin') {
                    localStorage.setItem("carrito_" + datos.usuario.email, datos.usuario.carrito);
                }

                cargarCarritoUsuario();
                mostrarToast(`¡Bienvenido/a, ${datos.usuario.nombre}!`);

                let redireccionPendiente = localStorage.getItem("redireccionPostLogin");
                setTimeout(() => {
                    if (redireccionPendiente) {
                        localStorage.removeItem("redireccionPostLogin");
                        window.location.href = redireccionPendiente;
                    } else if (datos.usuario.rol === 'admin') {
                        window.location.href = "panel_admin.html";
                    } else {
                        window.location.href = "index.html";
                    }
                }, 600);
            } else {
                mostrarToast("❌ " + datos.error, true);
                if(btn) { btn.disabled = false; btn.innerText = "Ingresar"; }
            }
        })
        .catch(() => {
            mostrarToast("Error de red al intentar iniciar sesión.", true);
            if(btn) { btn.disabled = false; btn.innerText = "Ingresar"; }
        });
}

document.addEventListener("DOMContentLoaded", () => {
    aplicarTemaGuardado();
    verificarEstadoSesion();
    cargarCarritoUsuario();
    configurarBuscadorGlobal();
    if (document.getElementsByClassName("slide").length > 0) mostrarSlides();
    if (document.getElementById("seccion-checkout")) inicializarCheckout();
    if (document.querySelector(".grilla-productos")) cargarProductos();
    if (document.getElementById("detalle-producto-contenedor")) renderizarDetalleProducto();
});

function finalizarCompra() {
    if (carrito.length === 0) {
        mostrarToast("Tu carrito está vacío. Agrega productos antes de continuar.", true);
        return;
    }

    let usuarioActivo = obtenerUsuarioActivo();
    if (!usuarioActivo) {
        mostrarToast("⚠️ Inicia sesión como cliente para continuar al pago.", true);
        localStorage.setItem("redireccionPostLogin", "checkout.html");
        setTimeout(() => { window.location.href = "login.html"; }, 800);
        return;
    }
    if (usuarioActivo.rol === 'admin') {
        mostrarToast("Las cuentas de administrador no realizan compras; usa una cuenta Cliente.", true);
        return;
    }

    window.location.href = "checkout.html";
}

function bloquearDatosClienteCheckout(usuarioParam) {
    let usuarioActivo = usuarioParam || obtenerUsuarioActivo();
    if (!usuarioActivo || usuarioActivo.rol === 'admin') return;

    const camposBloqueados = [
        { id: "pago-nombre", valor: usuarioActivo.nombre || "" },
        { id: "pago-email",  valor: usuarioActivo.email || "" },
        { id: "pago-dni",    valor: usuarioActivo.dni || "" }
    ];

    camposBloqueados.forEach(({ id, valor }) => {
        let input = document.getElementById(id);
        if (!input) return;

        input.value = valor;
        input.readOnly = true;
        input.setAttribute("readonly", "readonly");
        input.tabIndex = -1;
        input.classList.add("input-bloqueado");
        input.title = "Dato verificado del titular de la cuenta (no editable)";

        if (!input.dataset.lockBound) {
            const restaurarValor = (e) => {
                if (e && e.cancelable) e.preventDefault();
                let u = obtenerUsuarioActivo();
                if (u && u.rol !== 'admin') {
                    input.value = (id === "pago-nombre") ? (u.nombre || "")
                                : (id === "pago-email")  ? (u.email || "")
                                : (u.dni || "");
                }
            };
            ["keydown", "paste", "drop", "input", "change"].forEach(ev => {
                input.addEventListener(ev, restaurarValor);
            });
            input.dataset.lockBound = "1";
        }
    });
}

function inicializarCheckout() {
    let usuarioActivo = obtenerUsuarioActivo();

    if (!usuarioActivo || usuarioActivo.rol === 'admin') {
        mostrarToast("⚠️ Inicia sesión con tu cuenta de cliente para finalizar la compra.", true);
        localStorage.setItem("redireccionPostLogin", "checkout.html");
        setTimeout(() => { window.location.href = "login.html"; }, 800);
        return;
    }

    bloquearDatosClienteCheckout(usuarioActivo);
    actualizarResumenCheckout();
}

function actualizarResumenCheckout() {
    let listaMini = document.getElementById("lista-miniatura-checkout");
    let subtotalEl = document.getElementById("checkout-subtotal");
    let totalEl = document.getElementById("checkout-total");
    let textoCuotasEl = document.getElementById("checkout-texto-cuotas");
    let selectCuotas = document.getElementById("cuotas-pago");

    if (!listaMini || !subtotalEl || !totalEl || !textoCuotasEl) return;

    listaMini.innerHTML = "";
    let subtotal = 0;

    carrito.forEach(item => {
        let subItem = item.precio * item.cantidad;
        subtotal += subItem;
        listaMini.innerHTML += `
            <div class="item-mini-checkout">
                <span>${item.cantidad}x ${escaparHTML(item.nombre)}</span>
                <span>$${subItem.toLocaleString('es-AR')}</span>
            </div>
        `;
    });

    let cuotas = selectCuotas ? parseInt(selectCuotas.value) : 1;
    let totalFinal = (cuotas === 12) ? Math.round(subtotal * 1.15) : subtotal;
    let valorCuota = Math.round(totalFinal / cuotas);

    subtotalEl.innerText = "$" + subtotal.toLocaleString('es-AR');
    totalEl.innerText = "$" + totalFinal.toLocaleString('es-AR');
    textoCuotasEl.innerText = `${cuotas}x $${valorCuota.toLocaleString('es-AR')}`;
}

function alternarMetodoPago() {
    let metodo = document.querySelector('input[name="metodo-pago"]:checked').value;
    let bloqueTarjeta = document.getElementById("bloque-tarjeta");
    let bloqueTransf = document.getElementById("bloque-transferencia");
    let inputsTarjeta = bloqueTarjeta.querySelectorAll("input");

    if (metodo === "tarjeta") {
        bloqueTarjeta.style.display = "block";
        bloqueTransf.style.display = "none";
        inputsTarjeta.forEach(inp => inp.required = true);
    } else {
        bloqueTarjeta.style.display = "none";
        bloqueTransf.style.display = "block";
        inputsTarjeta.forEach(inp => inp.required = false);
        document.getElementById("cuotas-pago").value = "1";
        actualizarResumenCheckout();
    }
}

function formatearTarjeta(input) {
    let valor = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = valor.replace(/(\d{4})(?=\d)/g, '$1 ');
}

function alternarTipoEntrega() {
    let tipo = document.querySelector('input[name="tipo-entrega"]:checked').value;
    let bloqueRetiro = document.getElementById("bloque-retiro-sucursal");
    let bloqueEnvio = document.getElementById("bloque-envio-domicilio");
    let inputDir = document.getElementById("pago-direccion");
    let inputLoc = document.getElementById("pago-localidad");

    if (tipo === "retiro") {
        bloqueRetiro.style.display = "block";
        bloqueEnvio.style.display = "none";
        inputDir.required = false;
        inputLoc.required = false;
    } else {
        bloqueRetiro.style.display = "none";
        bloqueEnvio.style.display = "block";
        inputDir.required = true;
        inputLoc.required = true;
    }
}

function procesarPagoFicticio(evento) {
    evento.preventDefault();

    let usuarioActivo = obtenerUsuarioActivo();
    if (!usuarioActivo || usuarioActivo.rol === 'admin') {
        mostrarToast("⚠️ Debes iniciar sesión como cliente para finalizar tu compra.", true);
        localStorage.setItem("redireccionPostLogin", "checkout.html");
        setTimeout(() => { window.location.href = "login.html"; }, 900);
        return;
    }
    if (carrito.length === 0) {
        mostrarToast("Tu carrito está vacío.", true);
        return;
    }

    let btn = document.getElementById("btn-submit-pago");
    btn.disabled = true;
    btn.innerText = "⏳ Procesando pago y guardando orden...";
    bloquearDatosClienteCheckout(usuarioActivo);
    let nombreCliente = (usuarioActivo.nombre || document.getElementById("pago-nombre").value).trim();
    let emailCliente = (usuarioActivo.email || document.getElementById("pago-email").value).trim();
    let dniCliente = (usuarioActivo.dni || document.getElementById("pago-dni").value).trim();
    let tipoEntrega = document.querySelector('input[name="tipo-entrega"]:checked').value;
    let selectSucursal = document.getElementById("sucursal-elegida");
    let nombreSucursal = selectSucursal.options[selectSucursal.selectedIndex].text;
    let direccionCliente = document.getElementById("pago-direccion").value;
    let localidadCliente = document.getElementById("pago-localidad").value;
    let detalleCuotas = document.getElementById("checkout-texto-cuotas").innerText;
    let cuotas = parseInt(document.getElementById("cuotas-pago").value) || 1;

    function restaurarBoton() {
        btn.disabled = false;
        btn.innerText = "Confirmar y Pagar";
    }

    fetch('procesar_compra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            nombre_cliente: nombreCliente,
            email_cliente: emailCliente,
            dni_cliente: dniCliente,
            cuotas: cuotas,
            id_sede: parseInt(selectSucursal.value),
            metodo_envio: tipoEntrega,
            direccion: tipoEntrega === "envio" ? direccionCliente + ", " + localidadCliente : "",
            productos: carrito.map(i => ({ id: i.id, cantidad: i.cantidad }))
        })
    })
    .then(res => res.json())
    .then(datos => {
        if (!datos.exito) {
            mostrarToast("❌ " + datos.error, true);
            restaurarBoton();
            return;
        }

        let nroOrden = datos.id_orden;
        let codigoSeguimiento = datos.codigo_seguimiento || ('#EH-' + nroOrden);
        let totalPagadoTexto = "$" + Number(datos.total).toLocaleString('es-AR');
        let detalleEntregaHTML = "";
        let datosParaQR = "";

        if (tipoEntrega === "retiro") {
            detalleEntregaHTML = `<p><strong>Modalidad:</strong> ${escaparHTML(nombreSucursal)}</p>`;
            datosParaQR = `ELECTROHOGAR|ORDEN:${codigoSeguimiento}|CLIENTE:${nombreCliente}|DNI:${dniCliente}|RETIRO:${nombreSucursal}|TOTAL:${totalPagadoTexto}`;
        } else {
            detalleEntregaHTML = `<p><strong>Envío a domicilio:</strong> ${escaparHTML(direccionCliente)}, ${escaparHTML(localidadCliente)}</p>`;
            datosParaQR = `ELECTROHOGAR|ORDEN:${codigoSeguimiento}|CLIENTE:${nombreCliente}|DNI:${dniCliente}|ENVIO:${direccionCliente}|TOTAL:${totalPagadoTexto}`;
        }

        let urlQR = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(datosParaQR)}`;

        carrito = [];
        sincronizarCarritoDB();
        actualizarContador();
        mostrarToast(`🎉 ¡Orden ${codigoSeguimiento} registrada con éxito!`);

        document.getElementById("seccion-checkout").innerHTML = `
            <div class="comprobante-exito">
                <h2 style="color: #28a745; margin-top: 0;">✅ ¡COMPRA CONFIRMADA!</h2>
                <p>Presenta este código QR y tu DNI para retirar o recibir tu pedido.</p>
                <p>Se envio a tu correo esta confirmación.</p>

                <hr class="separador-checkout">

                <div style="text-align: left; line-height: 1.8;">
                    <p><strong>Código de Orden:</strong> ${escaparHTML(codigoSeguimiento)}</p>
                    <p><strong>Titular / Retira:</strong> ${escaparHTML(nombreCliente)} (DNI: ${escaparHTML(dniCliente)})</p>
                    ${detalleEntregaHTML}
                    <p><strong>Total Abonado:</strong> ${totalPagadoTexto} (${escaparHTML(detalleCuotas)})</p>
                </div>

                <div style="margin: 20px 0;">
                    <img src="${urlQR}" alt="Código QR de Retiro" width="180" height="180" style="border:1px solid #ddd; padding:8px; border-radius:8px; background:#fff;">
                    <p style="font-size:13px; color:#555; margin-top:8px;">Voucher verificado y descontado del inventario de la sucursal</p>
                </div>

                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                    <button type="button" class="btn-volver" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
                    <button type="button" class="btn-carrito-add" style="width: auto; padding: 12px 24px; background:#000; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;" onclick="window.location.href='index.html'">
                        Volver a la Tienda
                    </button>
                </div>
            </div>
        `;
    })
    .catch(error => {
        console.error("Error de red:", error);
        mostrarToast("No pudimos conectar con el servidor. Intenta de nuevo.", true);
        restaurarBoton();
    });
}

function mostrarSlides() {
    let slides = document.getElementsByClassName("slide");
    if (slides.length === 0) return;

    for (let i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";  
    }
    
    slideIndex++;
    if (slideIndex > slides.length) {
        slideIndex = 1;
    }    
    
    slides[slideIndex - 1].style.display = "flex";  
    setTimeout(mostrarSlides, 4500);
}

function cargarProductos() {
    let contenedor = document.querySelector(".grilla-productos");
    if (!contenedor) return;

    let categoriaAttr = contenedor.getAttribute("data-categoria") || "todos";
    fetch('obtener_productos.php?categoria=' + encodeURIComponent(categoriaAttr))
        .then(respuesta => respuesta.json())
        .then(listaProductos => {
            let esPaginaOfertas = window.location.pathname.includes("ofertas.html") || categoriaAttr === "ofertas";

            if (esPaginaOfertas) {
                listaProductos = listaProductos.filter(prod => (parseFloat(prod.precio_anterior) || 0) > 0 || Number(prod.en_oferta) === 1);
            }
            let categoriaPagina = contenedor.getAttribute("data-categoria");
            if (!esPaginaOfertas && categoriaPagina && categoriaPagina !== "todos") {
                listaProductos = listaProductos.filter(prod =>
                    prod.categoria && prod.categoria.toLowerCase() === categoriaPagina.toLowerCase()
                );
            }

            productosCargados = listaProductos;

            if (listaProductos.length === 0) {
                contenedor.innerHTML = "<p style='text-align:center; width:100%; font-size: 18px; grid-column: 1 / -1; padding: 30px;'>No hay productos disponibles en esta categoría.</p>";
                return;
            }

            let html = "";
            listaProductos.forEach(prod => {
                let precioActual = parseFloat(prod.precio) || 0;
                let precioViejo = parseFloat(prod.precio_anterior) || 0;
                let stockTotal = prod.stock_total !== undefined ? Number(prod.stock_total) : 0;
                let sinStock = stockTotal <= 0;

                let htmlPrecios = "";
                if (precioViejo > 0) {
                    htmlPrecios = `
                        <p class="precio-viejo" style="text-decoration: line-through; color: #999; font-size: 14px; margin: 0;">$${precioViejo.toLocaleString('es-AR')}</p>
                        <p class="precio" style="color: #e53935; font-size: 22px; font-weight: bold; margin: 5px 0;">$${precioActual.toLocaleString('es-AR')}</p>
                    `;
                } else {
                    htmlPrecios = `
                        <p class="precio" style="font-size: 20px; font-weight: bold; margin: 5px 0;">$${precioActual.toLocaleString('es-AR')}</p>
                    `;
                }

                let htmlBoton = sinStock
                    ? `<button type="button" class="btn-carrito-add" disabled style="width: 100%; padding: 10px; background: #999; color: #fff; border: none; border-radius: 4px; cursor: not-allowed; margin-top: 15px;">Sin stock</button>`
                    : `<button type="button" class="btn-carrito-add" onclick="agregarAlCarrito(${prod.id})" style="width: 100%; padding: 10px; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px;">
                            🛒 Añadir al Carrito
                        </button>`;

                html += `
                    <article class="producto">
                        <div>
                            <img src="${escaparHTML(prod.imagen)}" onerror="this.onerror=null;this.src='img/productos/placeholder.jpg';" alt="${escaparHTML(prod.nombre)}" onclick="window.location.href='producto.html?id=${prod.id}'">

                            <h3 onclick="window.location.href='producto.html?id=${prod.id}'" style="font-size: 16px; margin: 10px 0; cursor: pointer;">${escaparHTML(prod.nombre)}</h3>

                            ${htmlPrecios}
                            <small class="stock-badge" style="color:#666; display:block; margin-top:4px;">Stock disponible: ${stockTotal} unid.</small>
                        </div>

                        ${htmlBoton}
                    </article>
                `;
            });
            contenedor.innerHTML = html;
        })
        .catch(error => {
            console.error(error);
            contenedor.innerHTML = "<p style='text-align:center; width:100%; grid-column: 1 / -1; color: red;'>Hubo un problema al cargar los productos. Revisa tu conexión.</p>";
        });
}

function renderizarDetalleProducto() {
    let contenedor = document.getElementById("detalle-producto-contenedor");
    if (!contenedor) return;

    let parametros = new URLSearchParams(window.location.search);
    let idProducto = parametros.get("id");

    if (!idProducto) {
        contenedor.innerHTML = "<p style='text-align:center;'>No se seleccionó ningún producto.</p>";
        return;
    }

    fetch(`obtener_producto.php?id=${encodeURIComponent(idProducto)}`)
        .then(respuesta => respuesta.json())
        .then(prod => {

            if (prod.error) {
                contenedor.innerHTML = `<div style="grid-column:1/-1; text-align: center; padding: 40px;"><p>El producto no existe.</p><button class="btn-volver" onclick="window.location.href='index.html'">Volver</button></div>`;
                return;
            }

            productosCargados = [prod];

            let htmlImagen = prod.imagen ? `<img src="${escaparHTML(prod.imagen)}" onerror="this.onerror=null;this.src='img/productos/placeholder.jpg';" alt="${escaparHTML(prod.nombre)}">` : `<div>[ Sin imagen ]</div>`;

            let precioFinal = Number(prod.precio) || 0;
            let precioViejo = Number(prod.precio_anterior) || 0;
            let stockTotal = prod.stock_total !== undefined ? Number(prod.stock_total) : 0;
            let sinStock = stockTotal <= 0;
            let htmlPrecioViejo = precioViejo > 0
                ? `<p style="text-decoration: line-through; color: #999; margin: 5px 0 0 0;">$${precioViejo.toLocaleString('es-AR')}</p>`
                : "";
            let descripcion = prod.descripcion ? escaparHTML(prod.descripcion) : "Sin descripción disponible.";
            let catTexto = (prod.categoria || "GENERAL").toUpperCase();

            contenedor.innerHTML = `
                <div class="detalle-imagen-caja">
                    ${htmlImagen}
                </div>
                <div class="detalle-info-caja">
                    <span style="background: #000; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; display: inline-block; width: fit-content;">Garantía Oficial • ${escaparHTML(catTexto)}</span>
                    <h2>${escaparHTML(prod.nombre)}</h2>
                    <p class="detalle-descripcion">${descripcion}</p>
                    ${htmlPrecioViejo}
                    <p class="detalle-precio" style="margin: 12px 0;">$${precioFinal.toLocaleString('es-AR')}</p>
                    ${Array.isArray(prod.stock_sedes) && prod.stock_sedes.length > 0 ? `
                        <div class="caja-stock-sedes">
                            <strong>📍 Disponibilidad por sucursal (Total: ${stockTotal} unid.):</strong>
                            <ul style="list-style: none; padding: 0; margin: 8px 0 0 0;">
                                ${prod.stock_sedes.map(s => `<li style="margin: 4px 0;">• ${escaparHTML(s.nombre)}: <strong>${s.cantidad > 0 ? s.cantidad + ' unid.' : 'Sin stock'}</strong></li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    ${sinStock
                        ? `<button type="button" class="btn-comprar-grande" disabled style="background: #999; border-color: #999; cursor: not-allowed;">Sin stock</button>`
                        : `<button type="button" class="btn-comprar-grande" onclick="agregarAlCarrito(${prod.id})">
                        🛒 Añadir al Carrito
                    </button>`}
                </div>
            `;
        })
        .catch(error => {
            console.error(error);
            contenedor.innerHTML = "<p>Error de conexión al cargar los detalles.</p>";
        });
}

function configurarBuscadorGlobal() {
    let input = document.getElementById("buscador-productos");
    if (!input) return;
    let contenedorResultados = document.getElementById("lista-resultados-busqueda");
    if (!contenedorResultados) {
        contenedorResultados = document.createElement("div");
        contenedorResultados.id = "lista-resultados-busqueda";
        contenedorResultados.style.position = "absolute";
        contenedorResultados.style.top = "100%";
        contenedorResultados.style.left = "0";
        contenedorResultados.style.background = "#fff";
        contenedorResultados.style.width = "280px";
        contenedorResultados.style.maxHeight = "350px";
        contenedorResultados.style.overflowY = "auto";
        contenedorResultados.style.boxShadow = "0px 6px 12px rgba(0,0,0,0.15)";
        contenedorResultados.style.zIndex = "9999";
        contenedorResultados.style.display = "none";
        contenedorResultados.style.borderRadius = "0 0 8px 8px";
        contenedorResultados.style.border = "1px solid #ddd";
        contenedorResultados.style.borderTop = "none";
        input.parentNode.style.position = "relative";
        input.parentNode.insertBefore(contenedorResultados, input.nextSibling);
    }
    fetch('obtener_productos.php')
        .then(res => res.json())
        .then(data => {
            if (Array.isArray(data)) {
                catalogoGlobal = data.map(p => ({
                    nombre: p.nombre,
                    categoria: (p.categoria || "General").toUpperCase(),
                    url: `producto.html?id=${p.id}`
                }));
            }
        })
        .catch(err => console.error("Error al iniciar buscador:", err));

    document.addEventListener("click", (e) => {
        if (e.target !== input && !contenedorResultados.contains(e.target)) {
            contenedorResultados.style.display = "none";
        }
    });

    input.addEventListener("keydown", (evento) => {
        if (evento.key === "Enter") {
            let filtro = input.value.toLowerCase().trim();
            if (filtro === "") return;
            
            let coincidencia = catalogoGlobal.find(p => 
                p.nombre.toLowerCase().includes(filtro) || 
                p.categoria.toLowerCase().includes(filtro)
            );
            
            if (coincidencia) {
                window.location.href = coincidencia.url;
            } else {
                mostrarToast("No encontramos productos que coincidan con tu búsqueda.", true);
            }
        }
    });
}

function filtrarProductos() {
    let input = document.getElementById("buscador-productos");
    let contenedorResultados = document.getElementById("lista-resultados-busqueda");
    if (!input || !contenedorResultados) return;

    let filtro = input.value.toLowerCase().trim();
    contenedorResultados.innerHTML = "";

    if (filtro.length < 2) {
        contenedorResultados.style.display = "none";
        return;
    }

    let coincidencias = catalogoGlobal.filter(p => 
        p.nombre.toLowerCase().includes(filtro) || 
        p.categoria.toLowerCase().includes(filtro)
    );

    if (coincidencias.length > 0) {
        coincidencias.forEach(item => {
            let opcion = document.createElement("div");
            opcion.style.padding = "10px 14px";
            opcion.style.cursor = "pointer";
            opcion.style.borderBottom = "1px solid #eee";
            opcion.style.display = "flex";
            opcion.style.justifyContent = "space-between";
            opcion.style.alignItems = "center";
            
            opcion.innerHTML = `
                <span style="font-weight: 600; color: #222; font-size: 13px;">🔍 ${escaparHTML(item.nombre)}</span>
                <span style="font-size: 10px; background: #000; color: #fff; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">${escaparHTML(item.categoria)}</span>
            `;

            opcion.onmouseover = () => opcion.style.background = "#f0f4f8";
            opcion.onmouseout = () => opcion.style.background = "#fff";
            opcion.onclick = () => window.location.href = item.url;
            
            contenedorResultados.appendChild(opcion);
        });
        contenedorResultados.style.display = "block";
    } else {
        contenedorResultados.innerHTML = `<div style="padding: 14px; color: #777; text-align: center; font-size: 13px; cursor: default;">Sin resultados para "${escaparHTML(filtro)}"</div>`;
        contenedorResultados.style.display = "block";
    }
}
