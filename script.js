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
    let usuarioActivo = obtenerUsuarioActivo();
    if (!usuarioActivo) {
        mostrarToast("⚠️ Debes iniciar sesión con tu cuenta de cliente para agregar productos al carrito.", true);
        localStorage.setItem("redireccionPostLogin", window.location.href);
        setTimeout(() => {
            window.location.href = "login.html";
        }, 900);
        return;
    }
    if (usuarioActivo.rol === 'admin') {
        mostrarToast("Las cuentas de administrador no realizan compras; usa una cuenta Cliente.", true);
        return;
    }

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

function actualizarDisponibilidadRetiroSedes() {
    const avisoEnvio = document.getElementById("aviso-envio-domicilio-sugerido");
    const cajaSedes = document.getElementById("caja-disponibilidad-retiro-sedes");
    if (!avisoEnvio) return;

    if (carrito.length === 0) {
        if (cajaSedes) cajaSedes.style.display = "none";
        avisoEnvio.style.display = "none";
        return;
    }

    fetch('obtener_stock_sedes.php')
        .then(r => r.json())
        .then(data => {
            if (!data.exito || !data.stock || !data.sedes) {
                if (cajaSedes) cajaSedes.style.display = "none";
                avisoEnvio.style.display = "none";
                return;
            }

            const stockData = data.stock;
            const sedes = data.sedes;
            let algunaSedeDisponible = false;

            carrito.forEach(item => {
                const divItemStock = document.getElementById(`stock-sedes-item-${item.id}`);
                if (divItemStock) {
                    const stItem = stockData[item.id] || {};
                    const badgesSedes = sedes.map(sede => {
                        const disp = stItem[sede.id] !== undefined ? Number(stItem[sede.id]) : 0;
                        const ok = disp >= item.cantidad;
                        const bg = ok ? "#e8f5e9" : "#fff8e1";
                        const bd = ok ? "#c8e6c9" : "#ffe082";
                        const col = ok ? "#2e7d32" : "#e65100";
                        const ico = ok ? "✅" : "⚠️";
                        return `<span style="display:inline-block; background:${bg}; border:1px solid ${bd}; color:${col}; padding:3px 8px; border-radius:4px; font-size:11.5px; margin-right:6px; margin-top:4px;">${ico} ${escaparHTML(sede.nombre)}: <strong>${disp} disp.</strong> (pedís ${item.cantidad})</span>`;
                    }).join("");
                    divItemStock.innerHTML = `<div style="margin-top:6px;">${badgesSedes}</div>`;
                }
            });

            sedes.forEach(sede => {
                let alcanza = true;
                carrito.forEach(item => {
                    const stItem = stockData[item.id] || {};
                    const dispEnSede = stItem[sede.id] !== undefined ? Number(stItem[sede.id]) : 0;
                    if (item.cantidad > dispEnSede) {
                        alcanza = false;
                    }
                });
                if (alcanza) {
                    algunaSedeDisponible = true;
                }
            });

            if (cajaSedes) cajaSedes.style.display = "block";
            avisoEnvio.style.display = "block";

            if (!algunaSedeDisponible) {
                avisoEnvio.innerHTML = "🚚 <strong>Aviso antes de ir a pagar:</strong> Ninguna sucursal cuenta con stock suficiente por sí sola para cubrir la cantidad solicitada en un único retiro. Podés continuar al pago y elegir <strong>Envío a Domicilio</strong> para abastecer tu compra con el stock consolidado de todas las sucursales.";
            } else {
                avisoEnvio.innerHTML = "🏪 <strong>Aviso antes de ir a pagar:</strong> Verificá arriba qué sucursales cuentan con stock disponible según la cantidad que querés comprar si preferís retirar en tienda, o elegí <strong>Envío a Domicilio</strong> en el siguiente paso.";
            }
        })
        .catch(() => {
            if (cajaSedes) cajaSedes.style.display = "none";
            avisoEnvio.style.display = "none";
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

    let usuarioActivo = obtenerUsuarioActivo();
    if (!usuarioActivo) {
        contenedor.innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <p style="font-size: 16px; color: #555; margin-bottom: 20px;">⚠️ Debes iniciar sesión con tu cuenta de cliente para gestionar tu carrito y agregar productos.</p>
                <button type="button" class="btn-comprar-grande" style="width: auto; padding: 12px 28px; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;" onclick="window.location.href='login.html'">👤 Iniciar Sesión</button>
            </div>
        `;
        mostrarTotales(0);
        if (btnPagar) { btnPagar.disabled = true; btnPagar.innerText = "INICIA SESIÓN PARA COMPRAR"; }
        return;
    }

    if (carrito.length === 0) {
        contenedor.innerHTML = "<p style='padding:20px; font-size:15px;'>Tu carrito está vacío. Explora el catálogo para agregar productos.</p>";
        mostrarTotales(0);
        if (btnPagar) { btnPagar.disabled = true; btnPagar.innerText = "FINALIZAR COMPRA"; }
        actualizarDisponibilidadRetiroSedes();
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
                    : `<p style="color: #e53935; font-weight: bold; font-size: 13px; margin-top: 5px;">⚠️ Solo quedan ${stockReal} unidades en total - Reduce la cantidad</p>`;
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
                            <div id="stock-sedes-item-${item.id}"></div>
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
        actualizarDisponibilidadRetiroSedes();
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

function formatearVencimientoTarjeta(input) {
    let v = input.value.replace(/\D/g, "");
    if (v.length > 4) v = v.substring(0, 4);
    if (v.length > 2) {
        input.value = v.substring(0, 2) + "/" + v.substring(2);
    } else {
        input.value = v;
    }
}

function validarVencimientoTarjeta(valor) {
    if (!valor || !valor.trim()) {
        return { valido: false, error: "Debes ingresar la fecha de vencimiento de la tarjeta." };
    }
    const partes = valor.trim().split("/");
    if (partes.length !== 2 || partes[0].length !== 2 || partes[1].length !== 2) {
        return { valido: false, error: "Formato de vencimiento inválido. Utiliza MM/AA (ej: 08/29)." };
    }
    const mes = parseInt(partes[0], 10);
    const anio = 2000 + parseInt(partes[1], 10);

    if (isNaN(mes) || mes < 1 || mes > 12) {
        return { valido: false, error: "El mes de vencimiento debe estar entre 01 y 12." };
    }
    if (isNaN(anio)) {
        return { valido: false, error: "El año de vencimiento no es válido." };
    }

    const ahora = new Date();
    const anioActual = ahora.getFullYear();
    const mesActual = ahora.getMonth() + 1;

    if (anio < anioActual || (anio === anioActual && mes < mesActual)) {
        return { valido: false, error: "❌ La tarjeta ingresada se encuentra vencida. Por favor, utiliza un medio de pago vigente." };
    }
    if (anio > anioActual + 20) {
        return { valido: false, error: "❌ La fecha de vencimiento no es válida (año demasiado lejano)." };
    }

    return { valido: true, mes, anio };
}

function validarVencimientoEnInput(input) {
    if (!input || !input.value.trim()) return;
    let res = validarVencimientoTarjeta(input.value.trim());
    let errEl = document.getElementById("error-venc-tarjeta");
    if (!res.valido) {
        input.style.borderColor = "#dc3545";
        if (errEl) {
            errEl.innerText = res.error;
            errEl.style.display = "block";
        }
    } else {
        input.style.borderColor = "#28a745";
        if (errEl) {
            errEl.style.display = "none";
        }
    }
}

function formatearTarjeta(input) {
    let valor = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = valor.replace(/(\d{4})(?=\d)/g, '$1 ');
}

function cancelarCompra() {
    mostrarToast("Compra cancelada.");
    setTimeout(() => {
        window.location.href = "carrito.html";
    }, 400);
}

function bloquearDatosClienteCheckout(usuarioParam) {
    let usuarioActivo = usuarioParam || obtenerUsuarioActivo();
    if (!usuarioActivo || usuarioActivo.rol === 'admin') return;

    let nombreBase = (usuarioActivo.nombre_pila || usuarioActivo.nombre || "").trim();
    let apellidoBase = (usuarioActivo.apellido || "").trim();
    let nombreCompletoTitular = nombreBase;
    if (apellidoBase !== "" && !nombreBase.toLowerCase().endsWith(apellidoBase.toLowerCase())) {
        nombreCompletoTitular = (nombreBase + " " + apellidoBase).trim();
    }

    const camposBloqueados = [
        { id: "pago-nombre", valor: nombreCompletoTitular },
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
                    let nB = (u.nombre_pila || u.nombre || "").trim();
                    let aB = (u.apellido || "").trim();
                    let nComp = nB;
                    if (aB !== "" && !nB.toLowerCase().endsWith(aB.toLowerCase())) {
                        nComp = (nB + " " + aB).trim();
                    }
                    input.value = (id === "pago-nombre") ? nComp
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

let sedesConStockCheckout = [];

function inicializarCheckout() {
    let usuarioActivo = obtenerUsuarioActivo();

    if (!usuarioActivo || usuarioActivo.rol === 'admin') {
        mostrarToast("⚠️ Inicia sesión con tu cuenta de cliente para finalizar la compra.", true);
        localStorage.setItem("redireccionPostLogin", "checkout.html");
        setTimeout(() => { window.location.href = "login.html"; }, 800);
        return;
    }

    if (!carrito || carrito.length === 0) {
        try {
            let guardado = JSON.parse(localStorage.getItem(obtenerClaveCarrito())) || [];
            carrito = guardado.filter(item => item && (item.id || item.nombre));
        } catch (e) {
            carrito = [];
        }
    }

    bloquearDatosClienteCheckout(usuarioActivo);

    fetch('sesion.php?accion=estado')
        .then(r => r.json())
        .then(d => {
            if (d && d.logueado && d.usuario && d.usuario.rol !== 'admin') {
                let actualizado = Object.assign({}, usuarioActivo, {
                    nombre: d.usuario.nombre || usuarioActivo.nombre,
                    nombre_pila: d.usuario.nombre_pila || "",
                    apellido: d.usuario.apellido || "",
                    email: d.usuario.email || usuarioActivo.email,
                    dni: d.usuario.dni || usuarioActivo.dni
                });
                localStorage.setItem("usuarioElectroHogar", JSON.stringify(actualizado));
                bloquearDatosClienteCheckout(actualizado);
            }
        })
        .catch(() => {});

    actualizarResumenCheckout();
    verificarStockCheckout();
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

function alternarTipoEntrega() {
    let radioChecked = document.querySelector('input[name="tipo-entrega"]:checked');
    let tipo = radioChecked ? radioChecked.value : "retiro";
    let bloqueRetiro = document.getElementById("bloque-retiro-sucursal");
    let bloqueEnvio = document.getElementById("bloque-envio-domicilio");
    let inputDir = document.getElementById("pago-direccion");
    let inputLoc = document.getElementById("pago-localidad");
    let selectSuc = document.getElementById("sucursal-elegida");

    if (tipo === "retiro") {
        if (bloqueRetiro) bloqueRetiro.style.display = "block";
        if (bloqueEnvio) bloqueEnvio.style.display = "none";
        if (inputDir) inputDir.required = false;
        if (inputLoc) inputLoc.required = false;
        if (selectSuc) selectSuc.required = true;
    } else {
        if (bloqueRetiro) bloqueRetiro.style.display = "none";
        if (bloqueEnvio) bloqueEnvio.style.display = "block";
        if (inputDir) inputDir.required = true;
        if (inputLoc) inputLoc.required = true;
        if (selectSuc) selectSuc.required = false;
    }
}

function verificarStockCheckout() {
    let selectSuc = document.getElementById("sucursal-elegida");
    let avisoStock = document.getElementById("aviso-stock-modalidad");
    let radioRetiro = document.querySelector('input[name="tipo-entrega"][value="retiro"]');
    let radioEnvio = document.querySelector('input[name="tipo-entrega"][value="envio"]');
    if (!selectSuc || !avisoStock) return;

    if (!carrito || carrito.length === 0) {
        try {
            let guardado = JSON.parse(localStorage.getItem(obtenerClaveCarrito())) || [];
            carrito = guardado.filter(item => item && (item.id || item.nombre));
        } catch (e) {}
    }
    if (!carrito || carrito.length === 0) return;

    const etiquetasSedes = {
        1: "📍 Sucursal Central - Av. Corrientes 1234 (CABA)",
        2: "📍 Sucursal Zona Norte - Av. Pres. Perón 1500, San Miguel",
        3: "📍 Sucursal Zona Sur - Av. Cabildo 2040, Quilmes"
    };

    let valorPrevio = parseInt(selectSuc.value) || 0;

    fetch('obtener_stock_sedes.php')
        .then(r => r.json())
        .then(data => {
            if (!data.exito || !data.stock) return;

            let stockPorSedeGlobal = data.stock;
            let listaSedes = Array.isArray(data.sedes) && data.sedes.length > 0 ? data.sedes : [
                { id: 1, nombre: "Sucursal Central (CABA)" },
                { id: 2, nombre: "Sucursal Zona Norte (San Miguel)" },
                { id: 3, nombre: "Sucursal Zona Sur (Quilmes)" }
            ];

            let sedesConStock = [];
            let sedesSinStock = [];

            listaSedes.forEach(sede => {
                let sId = Number(sede.id);
                let alcanzaEnSede = true;
                for (let item of carrito) {
                    let stItem = stockPorSedeGlobal[item.id] || stockPorSedeGlobal[String(item.id)] || {};
                    let rawCant = stItem[sId] !== undefined ? stItem[sId] : stItem[String(sId)];
                    let cantSede = rawCant !== undefined ? Number(rawCant) : 0;
                    if (Number(item.cantidad) > cantSede) {
                        alcanzaEnSede = false;
                        break;
                    }
                }
                if (alcanzaEnSede) {
                    sedesConStock.push(sede);
                } else {
                    sedesSinStock.push(sede);
                }
            });

            sedesConStockCheckout = sedesConStock;

            if (sedesConStock.length > 0) {
                selectSuc.innerHTML = sedesConStock.map((sede, idx) => {
                    let sId = Number(sede.id);
                    let texto = etiquetasSedes[sId] || ("📍 " + sede.nombre);
                    let esElegida = sedesConStock.some(s => Number(s.id) === valorPrevio) ? (sId === valorPrevio) : (idx === 0);
                    return `<option value="${sId}" ${esElegida ? 'selected' : ''}>${escaparHTML(texto)}</option>`;
                }).join("");

                let idActivo = sedesConStock.some(s => Number(s.id) === valorPrevio) ? valorPrevio : Number(sedesConStock[0].id);
                selectSuc.value = String(idActivo);

                if (radioRetiro) {
                    radioRetiro.disabled = false;
                    let lblRetiro = radioRetiro.closest("label");
                    if (lblRetiro) {
                        lblRetiro.style.opacity = "1";
                        lblRetiro.style.cursor = "pointer";
                    }
                }

                if (sedesSinStock.length > 0) {
                    let nombresOcultas = sedesSinStock.map(s => `<strong>${escaparHTML(s.nombre)}</strong>`).join(", ");
                    avisoStock.style.display = "block";
                    avisoStock.innerHTML = `ℹ️ Se muestran únicamente las sucursales con stock disponible para tu compra. (${nombresOcultas} fue ocultada del selector por no contar con stock suficiente).`;
                } else {
                    avisoStock.style.display = "none";
                }
            } else {
                selectSuc.innerHTML = `<option value="" disabled selected>Sin sucursales con stock completo disponible</option>`;
                avisoStock.style.display = "block";
                avisoStock.innerHTML = `
                    <strong>ℹ️ Sin disponibilidad para retiro en una única sucursal:</strong><br>
                    Ninguna de las 3 sucursales cuenta por sí sola con el stock completo para las cantidades solicitadas. Tu compra se procesará mediante <strong>🚚 Envío a Domicilio</strong> utilizando el stock total consolidado.
                `;

                if (radioRetiro) {
                    radioRetiro.checked = false;
                    radioRetiro.disabled = true;
                    let lblRetiro = radioRetiro.closest("label");
                    if (lblRetiro) {
                        lblRetiro.style.opacity = "0.55";
                        lblRetiro.style.cursor = "not-allowed";
                    }
                }
                if (radioEnvio) {
                    radioEnvio.checked = true;
                    alternarTipoEntrega();
                }
            }
        })
        .catch(() => {});
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

    function restaurarBoton() {
        btn.disabled = false;
        btn.innerText = "Confirmar y Pagar";
    }

    let metodoPago = document.querySelector('input[name="metodo-pago"]:checked').value;
    let vencTarjeta = "";

    if (metodoPago === "tarjeta") {
        let inputNum = document.getElementById("num-tarjeta");
        let numTarjeta = inputNum ? inputNum.value.replace(/\s+/g, '') : "";
        if (numTarjeta.length < 15) {
            mostrarToast("❌ El número de tarjeta debe tener al menos 15 dígitos.", true);
            if (inputNum) inputNum.focus();
            restaurarBoton();
            return;
        }

        let inputVenc = document.getElementById("venc-tarjeta");
        vencTarjeta = inputVenc ? inputVenc.value.trim() : "";
        let resVenc = validarVencimientoTarjeta(vencTarjeta);
        if (!resVenc.valido) {
            mostrarToast(resVenc.error, true);
            if (inputVenc) {
                inputVenc.focus();
                inputVenc.style.borderColor = "#dc3545";
                let errEl = document.getElementById("error-venc-tarjeta");
                if (errEl) { errEl.innerText = resVenc.error; errEl.style.display = "block"; }
            }
            restaurarBoton();
            return;
        } else {
            let errEl = document.getElementById("error-venc-tarjeta");
            if (errEl) errEl.style.display = "none";
            if (inputVenc) inputVenc.style.borderColor = "#ced4da";
        }

        let inputCvv = document.getElementById("cvv-tarjeta");
        let cvvTarjeta = inputCvv ? inputCvv.value.trim() : "";
        if (cvvTarjeta.length < 3) {
            mostrarToast("❌ El código de seguridad (CVV) debe tener 3 o 4 dígitos.", true);
            if (inputCvv) inputCvv.focus();
            restaurarBoton();
            return;
        }
    }

    bloquearDatosClienteCheckout(usuarioActivo);
    let nombreCliente = (document.getElementById("pago-nombre").value || usuarioActivo.nombre || "").trim();
    let emailCliente = (usuarioActivo.email || document.getElementById("pago-email").value).trim();
    let dniCliente = (usuarioActivo.dni || document.getElementById("pago-dni").value).trim();
    let tipoEntrega = document.querySelector('input[name="tipo-entrega"]:checked').value;
    let selectSucursal = document.getElementById("sucursal-elegida");
    let idSedeEnviar = selectSucursal ? parseInt(selectSucursal.value) : 0;
    if ((!idSedeEnviar || isNaN(idSedeEnviar)) && sedesConStockCheckout.length > 0) {
        idSedeEnviar = Number(sedesConStockCheckout[0].id);
    }
    let optSeleccionada = selectSucursal && selectSucursal.selectedIndex >= 0 ? selectSucursal.options[selectSucursal.selectedIndex] : null;
    let nombreSucursal = optSeleccionada ? optSeleccionada.text : "Sucursal ElectroHogar";
    let direccionCliente = document.getElementById("pago-direccion").value;
    let localidadCliente = document.getElementById("pago-localidad").value;
    let detalleCuotas = document.getElementById("checkout-texto-cuotas").innerText;
    let cuotas = parseInt(document.getElementById("cuotas-pago").value) || 1;

    fetch('procesar_compra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            nombre_cliente: nombreCliente,
            email_cliente: emailCliente,
            dni_cliente: dniCliente,
            cuotas: cuotas,
            metodo_pago: metodoPago,
            vencimiento_tarjeta: vencTarjeta,
            id_sede: idSedeEnviar || 1,
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
        if (datos.nombre_sede && (!optSeleccionada || !selectSucursal.value)) {
            nombreSucursal = datos.nombre_sede;
        }

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
            <div class="comprobante-exito" style="background:#fff; border:1px solid #ddd; padding:30px; border-radius:8px; text-align:center;">
                <h2 style="color: #28a745; margin-top: 0;">✅ ¡COMPRA CONFIRMADA!</h2>
                <p>Presenta este código QR y tu DNI para retirar o recibir tu pedido.</p>
                <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
                <div style="text-align: left; line-height: 1.8; max-width:500px; margin:0 auto;">
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
                    <button type="button" onclick="window.print()" style="padding:10px 18px; border:1px solid #ccc; background:#fff; border-radius:4px; font-weight:bold; cursor:pointer;">🖨️ Imprimir / Guardar PDF</button>
                    <button type="button" onclick="window.location.href='index.html'" style="padding:10px 20px; background:#000; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Volver a la Tienda</button>
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

function procesarRegistro(evento) {
    evento.preventDefault();
    let btn = document.getElementById("btn-submit-registro");
    if (btn) { btn.disabled = true; btn.innerText = "Registrando..."; }

    let nombreVal = document.getElementById("reg-nombre").value.trim();
    let inputApellido = document.getElementById("reg-apellido");
    let apellidoVal = inputApellido ? inputApellido.value.trim() : "";
    let dniVal = document.getElementById("reg-dni").value.trim();
    let emailVal = document.getElementById("reg-email").value.trim();
    let passVal = document.getElementById("reg-password").value.trim();

    let regexNombre = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]+$/;
    if (!regexNombre.test(nombreVal) || nombreVal.length < 2) {
        mostrarToast("❌ El nombre solo puede contener letras, espacios, acentos y comillas simples (').", true);
        if (btn) { btn.disabled = false; btn.innerText = "Registrarme"; }
        return;
    }
    if (!regexNombre.test(apellidoVal) || apellidoVal.length < 2) {
        mostrarToast("❌ El apellido solo puede contener letras, espacios, acentos y comillas simples (').", true);
        if (btn) { btn.disabled = false; btn.innerText = "Registrarme"; }
        return;
    }
    if (!/^\d{6,11}$/.test(dniVal)) {
        mostrarToast("❌ El DNI debe contener solo números (6 a 11 dígitos).", true);
        if (btn) { btn.disabled = false; btn.innerText = "Registrarme"; }
        return;
    }

    let datos = { nombre: nombreVal, apellido: apellidoVal, dni: dniVal, email: emailVal, password: passVal };

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

function procesarLogin(evento) {
    evento.preventDefault();
    let btn = document.querySelector("button[type='submit']");
    if (btn) { btn.disabled = true; btn.innerText = "⏳ Verificando..."; }

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
                    nombre_pila: datos.usuario.nombre_pila || "",
                    apellido: datos.usuario.apellido || "",
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
                if (btn) { btn.disabled = false; btn.innerText = "Ingresar"; }
            }
        })
        .catch(() => {
            mostrarToast("Error de red al intentar iniciar sesión.", true);
            if (btn) { btn.disabled = false; btn.innerText = "Ingresar"; }
        });
}

function verificarEstadoSesionUI() {
    let usuarioActivo = obtenerUsuarioActivo();
    let btnLogin = document.getElementById("btn-login");
    let btnAdmin = document.getElementById("btn-panel-admin");
    let btnCarrito = document.getElementById("btn-carrito");
    let btnMisCompras = document.getElementById("btn-mis-compras");

    if (usuarioActivo) {
        let primerNombre = (usuarioActivo.nombre || "Usuario").split(" ")[0];
        if (btnLogin) {
            btnLogin.innerHTML = `🚪 Salir (${escaparHTML(primerNombre)})`;
            btnLogin.onclick = cerrarSesion;
        }
        if (usuarioActivo.rol === 'admin') {
            if (btnAdmin) btnAdmin.style.display = "inline-flex";
            if (btnCarrito) btnCarrito.style.display = "none";
            if (btnMisCompras) btnMisCompras.style.display = "none";
        } else {
            if (btnAdmin) btnAdmin.style.display = "none";
            if (btnCarrito) btnCarrito.style.display = "inline-flex";

            if (btnMisCompras) btnMisCompras.style.display = "inline-flex";
        }
    } else {
        if (btnLogin) {
            btnLogin.innerHTML = "👤 Iniciar Sesión";
            btnLogin.onclick = () => window.location.href = 'login.html';
        }
        if (btnAdmin) btnAdmin.style.display = "none";
        if (btnCarrito) btnCarrito.style.display = "inline-flex";
        if (btnMisCompras) btnMisCompras.style.display = "none";
    }
}

function cerrarSesion() {
    localStorage.removeItem(obtenerClaveCarrito());
    localStorage.removeItem("usuarioElectroHogar");
    fetch('logout.php').catch(() => {}).finally(() => window.location.reload());
}

function cargarProductos() {
    let contenedor = document.querySelector(".grilla-productos");
    if (!contenedor) return;

    let categoriaAttr = contenedor.getAttribute("data-categoria") || "todos";
    fetch('obtener_productos.php?categoria=' + encodeURIComponent(categoriaAttr))
        .then(respuesta => respuesta.json())
        .then(listaProductos => {
            productosCargados = listaProductos;
            if (listaProductos.length === 0) {
                contenedor.innerHTML = "<p style='text-align:center; width:100%; font-size: 16px; grid-column: 1 / -1; padding: 30px;'>No hay productos disponibles en esta categoría.</p>";
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
                        <p class="precio-viejo">$${precioViejo.toLocaleString('es-AR')}</p>
                        <p class="precio" style="color: #e53935;">$${precioActual.toLocaleString('es-AR')}</p>
                    `;
                } else {
                    htmlPrecios = `
                        <p class="precio">$${precioActual.toLocaleString('es-AR')}</p>
                    `;
                }

                let htmlBoton = sinStock
                    ? `<button type="button" class="btn-carrito-add" disabled style="background: #999; cursor: not-allowed;">Sin stock</button>`
                    : `<button type="button" class="btn-carrito-add" onclick="agregarAlCarrito(${prod.id})">🛒 Añadir al Carrito</button>`;

                html += `
                    <article class="producto">
                        <div>
                            <img src="${escaparHTML(prod.imagen)}" onerror="this.onerror=null;this.src='img/productos/placeholder.jpg';" alt="${escaparHTML(prod.nombre)}" onclick="window.location.href='producto.html?id=${prod.id}'">
                            <h3 onclick="window.location.href='producto.html?id=${prod.id}'">${escaparHTML(prod.nombre)}</h3>
                            ${htmlPrecios}
                            <small style="color:#666; display:block; margin-top:4px;">Stock disponible: ${stockTotal} unid.</small>
                        </div>
                        ${htmlBoton}
                    </article>
                `;
            });
            contenedor.innerHTML = html;
        })
        .catch(error => {
            console.error(error);
            contenedor.innerHTML = "<p style='text-align:center; width:100%; grid-column: 1 / -1; color: red;'>Hubo un problema al cargar los productos.</p>";
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
                contenedor.innerHTML = `<p style='text-align:center;'>El producto no existe.</p>`;
                return;
            }
            productosCargados = [prod];

            let precioFinal = Number(prod.precio) || 0;
            let precioViejo = Number(prod.precio_anterior) || 0;
            let stockTotal = prod.stock_total !== undefined ? Number(prod.stock_total) : 0;
            let sinStock = stockTotal <= 0;

            contenedor.innerHTML = `
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; align-items:start; background:#fff; padding:30px; border-radius:8px; border:1px solid #eee;">
                    <div>
                        <img src="${escaparHTML(prod.imagen)}" onerror="this.onerror=null;this.src='img/productos/placeholder.jpg';" style="width:100%; max-height:420px; object-fit:contain; border-radius:8px; background:#fafafa;">
                    </div>
                    <div>
                        <span style="background:#000; color:#fff; font-size:12px; font-weight:bold; padding:4px 8px; border-radius:4px; text-transform:uppercase;">${escaparHTML(prod.categoria)}</span>
                        <h2 style="font-size:24px; margin:15px 0 10px 0;">${escaparHTML(prod.nombre)}</h2>
                        <p style="color:#666; font-size:14.5px; line-height:1.5;">${escaparHTML(prod.descripcion)}</p>
                        ${precioViejo > 0 ? `<p style="text-decoration:line-through; color:#888; margin:10px 0 0 0;">$${precioViejo.toLocaleString('es-AR')}</p>` : ''}
                        <p style="font-size:28px; font-weight:bold; color:#111; margin:5px 0 15px 0;">$${precioFinal.toLocaleString('es-AR')}</p>

                        <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:15px; margin-bottom:20px;">
                            <strong>📍 Stock disponible por sucursal (Total: ${stockTotal} unid.):</strong>
                            <ul style="list-style:none; padding:0; margin:8px 0 0 0;">
                                ${Array.isArray(prod.stock_sedes) ? prod.stock_sedes.map(s => `<li style="margin:4px 0; font-size:13.5px;">• ${escaparHTML(s.nombre)}: <strong>${s.cantidad > 0 ? s.cantidad + ' unid.' : 'Sin stock'}</strong></li>`).join('') : ''}
                            </ul>
                        </div>

                        ${sinStock ? `<button type="button" class="btn-comprar-grande" disabled style="background:#999; cursor:not-allowed;">Sin stock</button>`
                                   : `<button type="button" class="btn-comprar-grande" onclick="agregarAlCarrito(${prod.id})">🛒 Añadir al Carrito</button>`}
                    </div>
                </div>
            `;
        })
        .catch(err => console.error(err));
}

let carruselTimer = null;

function inicializarCarrusel() {
    slideIndex = 1;
    mostrarSlide(slideIndex);
    iniciarAutoCarrusel();
    const contenedor = document.getElementById("carrusel-banner") || document.querySelector(".carrusel-contenedor");
    if (contenedor) {
        contenedor.addEventListener("mouseenter", detenerAutoCarrusel);
        contenedor.addEventListener("mouseleave", iniciarAutoCarrusel);
    }
}

function iniciarAutoCarrusel() {
    detenerAutoCarrusel();
    carruselTimer = setInterval(() => {
        cambiarSlide(1);
    }, 4500);
}

function detenerAutoCarrusel() {
    if (carruselTimer) {
        clearInterval(carruselTimer);
        carruselTimer = null;
    }
}

function cambiarSlide(n) {
    mostrarSlide(slideIndex += n);
    iniciarAutoCarrusel();
}

function irASlide(n) {
    mostrarSlide(slideIndex = n);
    iniciarAutoCarrusel();
}

function plusSlides(n) {
    cambiarSlide(n);
}

function currentSlide(n) {
    irASlide(n);
}

function mostrarSlide(n) {
    const slides = document.querySelectorAll(".carrusel-slide, .slide");
    const dots = document.querySelectorAll(".carrusel-dot, .dot");
    if (slides.length === 0) return;
    if (n > slides.length) slideIndex = 1;
    if (n < 1) slideIndex = slides.length;
    slides.forEach(s => s.style.display = "none");
    dots.forEach(d => d.classList.remove("activo"));
    if (slides[slideIndex - 1]) slides[slideIndex - 1].style.display = "block";
    if (dots[slideIndex - 1]) dots[slideIndex - 1].classList.add("activo");
}

function asegurarCamposRegistroSeparados() {
    const inputNombre = document.getElementById("reg-nombre");
    if (!inputNombre) return;

    const grupoNombre = inputNombre.closest(".form-group-registro");
    if (grupoNombre) {
        const lbl = grupoNombre.querySelector("label");
        if (lbl) lbl.textContent = "Nombre:";
        inputNombre.placeholder = "Ej: Juan";
    }

    if (!document.getElementById("reg-apellido") && grupoNombre) {
        const grupoApellido = document.createElement("div");
        grupoApellido.className = "form-group-registro";
        grupoApellido.innerHTML = `
            <label for="reg-apellido">Apellido:</label>
            <input type="text" id="reg-apellido" name="apellido" required placeholder="Ej: Pérez" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\\s'\\-]+$" title="Solo letras, espacios, acentos y apóstrofes (')">
        `;
        grupoNombre.insertAdjacentElement("afterend", grupoApellido);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    aplicarTemaGuardado();
    asegurarCamposRegistroSeparados();
    if (document.querySelector(".carrusel-slide") || document.querySelector(".slide")) inicializarCarrusel();
    verificarEstadoSesionUI();
    cargarCarritoUsuario();
    if (document.getElementById("seccion-checkout")) inicializarCheckout();
    if (document.querySelector(".grilla-productos")) cargarProductos();
    if (document.getElementById("detalle-producto-contenedor")) renderizarDetalleProducto();
});