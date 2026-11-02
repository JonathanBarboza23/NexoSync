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

// Si el cliente tiene sesión iniciada, forzar estrictamente sus datos registrados en la base de datos
if ($id_usuario) {
    $stmt_u = $conexion->prepare("SELECT nombre, email, dni FROM usuarios WHERE id = ?");
    $stmt_u->bind_param("i", $id_usuario);
    $stmt_u->execute();
    $u_db = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();
    if ($u_db) {
        if (!empty($u_db['nombre'])) $nombre = trim($u_db['nombre']);
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

$sedes_validas = ids_sedes($conexion);
if ($metodo === 'retiro') {
    if (!in_array($id_sede_pedida, $sedes_validas, true)) {
        echo json_encode(["exito" => false, "error" => "Sucursal de retiro inválida."]);
        exit;
    }
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

    // 2) Validación de stock según modalidad:
    // Retiro en sucursal: debe haber stock suficiente en la sede elegida.
    // Si supera el stock de la sede pero hay stock total consolidado, se debe comprar con Envío a Domicilio.
    if ($metodo === 'retiro') {
        $stmt_stock = $conexion->prepare("SELECT cantidad FROM `$t_stock` WHERE id_producto = ? AND id_sede = ?");
        foreach ($lineas as $l) {
            $stmt_stock->bind_param("ii", $l['id'], $id_sede_pedida);
            $stmt_stock->execute();
            $st = $stmt_stock->get_result()->fetch_assoc();
            $stock_sede_actual = $st ? (int)$st['cantidad'] : 0;

            if ($stock_sede_actual < $l['cant']) {
                // Consultar stock total consolidado
                $res_tot = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) AS tot FROM `$t_stock` WHERE id_producto = " . intval($l['id']));
                $stock_total = $res_tot ? (int)$res_tot->fetch_assoc()['tot'] : 0;

                if ($stock_total >= $l['cant']) {
                    throw new Exception("La cantidad solicitada de '{$l['nombre']}' ({$l['cant']} unid.) supera el stock disponible en la sucursal seleccionada ({$stock_sede_actual} unid.). Puedes adquirirla con el stock total ({$stock_total} unid.) seleccionando Envío a Domicilio.");
                } else {
                    throw new Exception("No hay stock suficiente para '{$l['nombre']}' (disponible en todas las sedes: {$stock_total} unid.).");
                }
            }
        }
        $stmt_stock->close();
        $id_sede_elegida = $id_sede_pedida;

    } else {
        // Modalidad: Envío a Domicilio -> puede comprar hasta el stock total consolidado
        foreach ($lineas as $l) {
            $res_tot = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) AS tot FROM `$t_stock` WHERE id_producto = " . intval($l['id']));
            $stock_total = $res_tot ? (int)$res_tot->fetch_assoc()['tot'] : 0;

            if ($stock_total < $l['cant']) {
                throw new Exception("Stock total insuficiente para '{$l['nombre']}'. Solicitadas: {$l['cant']} unid., Disponibles en total: {$stock_total} unid.");
            }
        }
        // Asignar sede cabecera para el registro de la orden
        $id_sede_elegida = in_array($id_sede_pedida, $sedes_validas, true) ? $id_sede_pedida : $sedes_validas[0];
    }

    // Sincronizar u obtener el id_cliente en la tabla `clientes`
    $id_cliente = obtener_o_crear_cliente($conexion, $nombre, $dni, $email);
    $id_estado_inicial = obtener_id_estado_por_nombre($conexion, 'proceso', 4);
    $codigo_seguimiento = 'EH-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

    // Construir INSERT dinámico compatible con `ordenes` de electrohogar.sql
    $cols_ord = [];
    $vals_ord = [];
    $types_ord = '';
    $params_ord = [];

    if (tiene_columna($conexion, 'ordenes', 'codigo_seguimiento')) {
        $cols_ord[] = 'codigo_seguimiento';
        $vals_ord[] = '?';
        $types_ord .= 's';
        $params_ord[] = $codigo_seguimiento;
    }
    if (tiene_columna($conexion, 'ordenes', 'id_cliente')) {
        $cols_ord[] = 'id_cliente';
        $vals_ord[] = '?';
        $types_ord .= 'i';
        $params_ord[] = $id_cliente;
    }
    if (tiene_columna($conexion, 'ordenes', 'id_usuario')) {
        $cols_ord[] = 'id_usuario';
        $vals_ord[] = '?';
        $types_ord .= 'i';
        $params_ord[] = $id_usuario;
    }
    if (tiene_columna($conexion, 'ordenes', 'nombre_cliente')) {
        $cols_ord[] = 'nombre_cliente';
        $vals_ord[] = '?';
        $types_ord .= 's';
        $params_ord[] = $nombre;
    }
    if (tiene_columna($conexion, 'ordenes', 'email_cliente')) {
        $cols_ord[] = 'email_cliente';
        $vals_ord[] = '?';
        $types_ord .= 's';
        $params_ord[] = $email;
    }
    if (tiene_columna($conexion, 'ordenes', 'total')) {
        $cols_ord[] = 'total';
        $vals_ord[] = '?';
        $types_ord .= 'd';
        $params_ord[] = $total;
    }

    // Columna de sede
    foreach (['id_sede_origen', 'id_sede', 'id_sede_retiro'] as $c_sede) {
        if (tiene_columna($conexion, 'ordenes', $c_sede)) {
            $cols_ord[] = $c_sede;
            $vals_ord[] = '?';
            $types_ord .= 'i';
            $params_ord[] = $id_sede_elegida;
            break;
        }
    }

    // Columna de entrega
    if (tiene_columna($conexion, 'ordenes', 'tipo_entrega')) {
        $cols_ord[] = 'tipo_entrega';
        $vals_ord[] = '?';
        $types_ord .= 's';
        if ($metodo === 'envio' && !tiene_columna($conexion, 'ordenes', 'direccion')) {
            $params_ord[] = 'Envio: ' . $direccion;
        } else {
            $params_ord[] = $metodo;
        }
    }
    if (tiene_columna($conexion, 'ordenes', 'direccion') && $metodo === 'envio') {
        $cols_ord[] = 'direccion';
        $vals_ord[] = '?';
        $types_ord .= 's';
        $params_ord[] = $direccion;
    }

    // Columna de estado
    if (tiene_columna($conexion, 'ordenes', 'id_estado')) {
        $cols_ord[] = 'id_estado';
        $vals_ord[] = '?';
        $types_ord .= 'i';
        $params_ord[] = $id_estado_inicial;
    } elseif (tiene_columna($conexion, 'ordenes', 'estado')) {
        $cols_ord[] = 'estado';
        $vals_ord[] = '?';
        $types_ord .= 's';
        $params_ord[] = 'En proceso';
    }

    $sql_ord = "INSERT INTO ordenes (" . implode(', ', $cols_ord) . ") VALUES (" . implode(', ', $vals_ord) . ")";
    $stmt = $conexion->prepare($sql_ord);
    $stmt->bind_param($types_ord, ...$params_ord);
    $stmt->execute();
    $id_orden = $conexion->insert_id;
    $stmt->close();

    // Insertar detalle_ordenes
    $stmt_det = $conexion->prepare(
        "INSERT INTO detalle_ordenes (id_orden, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)"
    );
    foreach ($lineas as $l) {
        $stmt_det->bind_param("iiid", $id_orden, $l['id'], $l['cant'], $l['precio']);
        $stmt_det->execute();
    }
    $stmt_det->close();

    // 2) Descontar stock:
    // Si es retiro, descuenta de la sede elegida.
    // Si es envío a domicilio y supera una sede, descuenta entre las sedes que tengan stock disponible.
    if ($metodo === 'retiro') {
        $stmt_upd = $conexion->prepare("UPDATE `$t_stock` SET cantidad = cantidad - ? WHERE id_producto = ? AND id_sede = ?");
        foreach ($lineas as $l) {
            $stmt_upd->bind_param("iii", $l['cant'], $l['id'], $id_sede_elegida);
            $stmt_upd->execute();
        }
        $stmt_upd->close();
    } else {
        // Envío a domicilio: deducción distribuida multi-sede
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

    // Vaciar carrito en base de datos si el usuario está registrado
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
        "id_sede" => $id_sede_elegida
    ]);

} catch (mysqli_sql_exception $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "No se pudo registrar la orden: " . $e->getMessage()]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => $e->getMessage()]);
}

$conexion->close();
?>
