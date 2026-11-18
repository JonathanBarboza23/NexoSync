<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
asegurar_stock_inicial($conexion);

$datos = json_decode(file_get_contents("php://input"), true);

foreach (['nombre_cliente', 'email_cliente', 'metodo_envio', 'productos'] as $campo) {
    if (empty($datos[$campo])) {
        echo json_encode(["exito" => false, "error" => "Faltan datos obligatorios para procesar la orden."]);
        exit;
    }
}

$nombre = trim($datos['nombre_cliente']);
$email = trim($datos['email_cliente']);
$dni = trim($datos['dni_cliente'] ?? '');
$metodo = $datos['metodo_envio'];
$direccion = trim($datos['direccion'] ?? '');
$cuotas = intval($datos['cuotas'] ?? 1);
$id_sede_pedida = intval($datos['id_sede'] ?? 0);
$id_usuario = $_SESSION['usuario_id'] ?? null;

if ($id_usuario) {
    $tiene_apellido = columna_existe($conexion, 'usuarios', 'apellido');
    $sql_u = $tiene_apellido
        ? "SELECT id, nombre, apellido, email, dni FROM usuarios WHERE id = ?"
        : "SELECT id, nombre, '' AS apellido, email, dni FROM usuarios WHERE id = ?";
    $stmt_u = $conexion->prepare($sql_u);
    $stmt_u->bind_param("i", $id_usuario);
    $stmt_u->execute();
    $u_db = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();
    if ($u_db) {
        $n_solo = trim($u_db['nombre'] ?? '');
        $a_solo = trim($u_db['apellido'] ?? '');
        $n_completo = trim($n_solo . ($a_solo !== '' ? ' ' . $a_solo : ''));
        if ($n_completo !== '')      $nombre = $n_completo;
        if (!empty($u_db['email']))  $email  = trim($u_db['email']);
        if (!empty($u_db['dni']))    $dni    = trim($u_db['dni']);
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["exito" => false, "error" => "El correo electrónico no es válido."]);
    exit;
}
if (!in_array($metodo, ['retiro', 'envio'], true)) {
    echo json_encode(["exito" => false, "error" => "Modalidad de entrega inválida."]);
    exit;
}
if ($metodo === 'envio' && $direccion === '') {
    echo json_encode(["exito" => false, "error" => "Debes ingresar la dirección de entrega a domicilio."]);
    exit;
}
if (!is_array($datos['productos']) || empty($datos['productos'])) {
    echo json_encode(["exito" => false, "error" => "El carrito de compras está vacío o es inválido."]);
    exit;
}

$metodo_pago = trim($datos['metodo_pago'] ?? 'tarjeta');
$venc_tarjeta = trim($datos['vencimiento_tarjeta'] ?? '');

if ($metodo_pago === 'tarjeta' && $venc_tarjeta !== '') {
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $venc_tarjeta, $matches)) {
        echo json_encode(["exito" => false, "error" => "El formato de vencimiento de la tarjeta es inválido (debe ser MM/AA)."]);
        exit;
    }
    $mes = intval($matches[1]);
    $anio = 2000 + intval($matches[2]);
    $anioActual = intval(date('Y'));
    $mesActual = intval(date('n'));

    if ($anio < $anioActual || ($anio === $anioActual && $mes < $mesActual)) {
        echo json_encode(["exito" => false, "error" => "La tarjeta ingresada se encuentra vencida. Por favor, utiliza un medio de pago vigente."]);
        exit;
    }
    if ($anio > $anioActual + 20) {
        echo json_encode(["exito" => false, "error" => "El año de vencimiento de la tarjeta no es válido."]);
        exit;
    }
}

$sedes_validas = ids_sedes($conexion);
$lista_sedes_info = listar_sedes($conexion);
$mapa_nombres_sedes = [];
foreach ($lista_sedes_info as $s_info) {
    $mapa_nombres_sedes[(int)$s_info['id']] = $s_info['nombre'];
}

$t_stock = nombre_tabla_stock($conexion);

try {
    $conexion->begin_transaction();

    $stmt_prod = $conexion->prepare("SELECT nombre, precio FROM productos WHERE id = ?");
    $lineas = [];
    $total = 0;
    $factor_cuotas = ($cuotas === 12) ? 1.15 : 1.0;

    foreach ($datos['productos'] as $p) {
        $id = intval($p['id'] ?? 0);
        $cant = intval($p['cantidad'] ?? 0);
        if ($id <= 0 || $cant <= 0) throw new Exception("Producto o cantidad inválidos.");

        $stmt_prod->bind_param("i", $id);
        $stmt_prod->execute();
        $prod = $stmt_prod->get_result()->fetch_assoc();
        if (!$prod) throw new Exception("Uno de los productos seleccionados ya no existe en el catálogo.");

        $precio_unit = round((float)$prod['precio'] * $factor_cuotas, 2);
        $total += $precio_unit * $cant;
        $lineas[] = ['id' => $id, 'cant' => $cant, 'precio' => $precio_unit, 'nombre' => $prod['nombre']];
    }
    $stmt_prod->close();
    $total = round($total, 2);

    $id_sede_elegida = null;

    if ($metodo === 'retiro') {
        $stmt_stock = $conexion->prepare("SELECT cantidad FROM `$t_stock` WHERE id_producto = ? AND id_sede = ?");
        $sedes_aptas_retiro = [];

        foreach ($sedes_validas as $s_id_eval) {
            $apta = true;
            foreach ($lineas as $l) {
                $stmt_stock->bind_param("ii", $l['id'], $s_id_eval);
                $stmt_stock->execute();
                $st = $stmt_stock->get_result()->fetch_assoc();
                $disp_sede = $st ? (int)$st['cantidad'] : 0;
                if ($disp_sede < $l['cant']) {
                    $apta = false;
                    break;
                }
            }
            if ($apta) {
                $sedes_aptas_retiro[] = (int)$s_id_eval;
            }
        }

        if (in_array($id_sede_pedida, $sedes_aptas_retiro, true)) {
            $id_sede_elegida = $id_sede_pedida;
        } elseif (!empty($sedes_aptas_retiro)) {
            $id_sede_elegida = $sedes_aptas_retiro[0];
        } else {
            foreach ($lineas as $l) {
                $stmt_stock->bind_param("ii", $l['id'], $id_sede_pedida);
                $stmt_stock->execute();
                $st = $stmt_stock->get_result()->fetch_assoc();
                $stock_sede_actual = $st ? (int)$st['cantidad'] : 0;

                if ($stock_sede_actual < $l['cant']) {
                    $res_tot = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) AS tot FROM `$t_stock` WHERE id_producto = " . intval($l['id']));
                    $stock_total = $res_tot ? (int)$res_tot->fetch_assoc()['tot'] : 0;
                    $stmt_stock->close();

                    if ($stock_total >= $l['cant']) {
                        throw new Exception("Ninguna sucursal individual cuenta con {$l['cant']} unidades de '{$l['nombre']}'. Puedes adquirir la totalidad con el stock consolidado ({$stock_total} unid.) seleccionando Envío a Domicilio.");
                    } else {
                        throw new Exception("No hay stock suficiente para '{$l['nombre']}' (disponible en todas las sedes: {$stock_total} unid.).");
                    }
                }
            }
            $stmt_stock->close();
            throw new Exception("No hay sucursales con stock disponible para retiro completo.");
        }
        $stmt_stock->close();

    } else {

        foreach ($lineas as $l) {
            $res_tot = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) AS tot FROM `$t_stock` WHERE id_producto = " . intval($l['id']));
            $stock_total = $res_tot ? (int)$res_tot->fetch_assoc()['tot'] : 0;

            if ($stock_total < $l['cant']) {
                throw new Exception("Stock total insuficiente para '{$l['nombre']}'. Solicitadas: {$l['cant']} unid., Disponibles en total: {$stock_total} unid.");
            }
        }
        $id_sede_elegida = in_array($id_sede_pedida, $sedes_validas, true) ? $id_sede_pedida : $sedes_validas[0];
    }

    $id_cliente_usuario = $id_usuario ? $id_usuario : obtener_o_crear_usuario_cliente($conexion, $nombre, $dni, $email);
    $id_estado_inicial = obtener_id_estado_por_nombre($conexion, 'proceso', 4);
    $codigo_seguimiento = 'EH-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

    $stmt_ord = $conexion->prepare(
        "INSERT INTO ordenes (codigo_seguimiento, id_cliente, id_sede_origen, tipo_entrega, direccion, total, id_estado) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt_ord->bind_param("siissdi", $codigo_seguimiento, $id_cliente_usuario, $id_sede_elegida, $metodo, $direccion, $total, $id_estado_inicial);
    $stmt_ord->execute();
    $id_orden = $conexion->insert_id;
    $stmt_ord->close();

    $stmt_det = $conexion->prepare(
        "INSERT INTO detalle_ordenes (id_orden, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)"
    );
    foreach ($lineas as $l) {
        $stmt_det->bind_param("iiid", $id_orden, $l['id'], $l['cant'], $l['precio']);
        $stmt_det->execute();
    }
    $stmt_det->close();

    if ($metodo === 'retiro') {
        $stmt_upd = $conexion->prepare("UPDATE `$t_stock` SET cantidad = cantidad - ? WHERE id_producto = ? AND id_sede = ?");
        foreach ($lineas as $l) {
            $stmt_upd->bind_param("iii", $l['cant'], $l['id'], $id_sede_elegida);
            $stmt_upd->execute();
        }
        $stmt_upd->close();
    } else {

        foreach ($lineas as $l) {
            $restante = $l['cant'];
            foreach ($sedes_validas as $s_id) {
                if ($restante <= 0) break;
                $st_res = $conexion->query("SELECT cantidad FROM `$t_stock` WHERE id_producto = {$l['id']} AND id_sede = $s_id");
                $disp = $st_res ? (int)$st_res->fetch_assoc()['cantidad'] : 0;
                if ($disp > 0) {
                    $desc = min($disp, $restante);
                    $conexion->query("UPDATE `$t_stock` SET cantidad = cantidad - $desc WHERE id_producto = {$l['id']} AND id_sede = $s_id");
                    $restante -= $desc;
                }
            }
        }
    }

    if ($id_usuario) {
        $carrito_vacio = '[]';
        $stmt_c = $conexion->prepare("UPDATE usuarios SET carrito = ? WHERE id = ?");
        $stmt_c->bind_param("si", $carrito_vacio, $id_usuario);
        $stmt_c->execute();
        $stmt_c->close();
    }

    foreach ($lineas as $l) sincronizar_stock_total($conexion, $l['id']);

    $conexion->commit();
    echo json_encode([
        "exito" => true,
        "id_orden" => $id_orden,
        "codigo_seguimiento" => $codigo_seguimiento,
        "total" => $total,
        "id_sede" => $id_sede_elegida,
        "nombre_sede" => $mapa_nombres_sedes[(int)$id_sede_elegida] ?? "Sucursal ElectroHogar"
    ]);

} catch (mysqli_sql_exception $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "No se pudo registrar la orden (restricción referencial activa): " . $e->getMessage()]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => $e->getMessage()]);
}

$conexion->close();
?>