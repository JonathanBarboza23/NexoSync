<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$id_orden = intval($_GET['id_orden'] ?? 0);
$periodo = trim($_GET['periodo'] ?? 'completo');
$busqueda = trim($_GET['q'] ?? '');

if ($id_orden > 0) {
    $sql = "SELECT d.id_detalle, d.id_producto, d.cantidad, d.precio_unitario,
                   COALESCE(p.nombre, 'Producto ElectroHogar') AS nombre,
                   COALESCE(p.categoria, 'general') AS categoria,
                   COALESCE(p.imagen, 'img/productos/placeholder.jpg') AS imagen
            FROM detalle_ordenes d
            LEFT JOIN productos p ON p.id = d.id_producto
            WHERE d.id_orden = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_orden);
    $stmt->execute();
    $res = $stmt->get_result();

    $detalles = [];
    while ($r = $res->fetch_assoc()) {
        $r['subtotal'] = round(((int)$r['cantidad']) * ((float)$r['precio_unitario']), 2);
        $detalles[] = $r;
    }
    $stmt->close();
    $conexion->close();

    echo json_encode(["exito" => true, "detalles" => $detalles]);
    exit;
}

$condiciones = [];
if ($periodo === '7dias') {
    $condiciones[] = "o.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($periodo === 'mes') {
    $condiciones[] = "o.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$tiene_ap = columna_existe($conexion, 'usuarios', 'apellido');
$expr_nombre_cliente = $tiene_ap
    ? "TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')))"
    : "u.nombre";

if ($busqueda !== '') {
    $b_esc = $conexion->real_escape_string($busqueda);
    if ($tiene_ap) {
        $condiciones[] = "(o.codigo_seguimiento LIKE '%$b_esc%' OR CAST(o.id_orden AS CHAR) LIKE '%$b_esc%' OR u.nombre LIKE '%$b_esc%' OR u.apellido LIKE '%$b_esc%' OR CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) LIKE '%$b_esc%' OR u.email LIKE '%$b_esc%')";
    } else {
        $condiciones[] = "(o.codigo_seguimiento LIKE '%$b_esc%' OR CAST(o.id_orden AS CHAR) LIKE '%$b_esc%' OR u.nombre LIKE '%$b_esc%' OR u.email LIKE '%$b_esc%')";
    }
}

$where_sql = !empty($condiciones) ? ("WHERE " . implode(" AND ", $condiciones)) : "";

$sql_ord = "SELECT o.id_orden, o.codigo_seguimiento, o.total, o.tipo_entrega, o.direccion, o.fecha, o.id_estado,
                   COALESCE(e.nombre_estado, 'En proceso') AS estado_nombre,
                   $expr_nombre_cliente AS nombre_cliente, u.email AS email_cliente, u.dni AS dni_cliente,
                   s.nombre AS nombre_sede
            FROM ordenes o
            LEFT JOIN usuarios u ON u.id = o.id_cliente
            LEFT JOIN estado e ON e.id_estado = o.id_estado
            LEFT JOIN sedes s ON s.id_sede = o.id_sede_origen
            $where_sql
            ORDER BY o.id_orden DESC";

$res_ord = $conexion->query($sql_ord);
$ordenes = [];
if ($res_ord) {
    while ($f = $res_ord->fetch_assoc()) {
        $id_o = (int)$f['id_orden'];
        $f['items'] = [];
        $ordenes[$id_o] = $f;
    }
}

if (!empty($ordenes)) {
    $ids_str = implode(",", array_keys($ordenes));
    $sql_det = "SELECT d.id_detalle, d.id_orden, d.id_producto, d.cantidad, d.precio_unitario,
                       COALESCE(p.nombre, 'Producto ElectroHogar') AS nombre,
                       COALESCE(p.categoria, 'general') AS categoria,
                       COALESCE(p.imagen, 'img/productos/placeholder.jpg') AS imagen
                FROM detalle_ordenes d
                LEFT JOIN productos p ON p.id = d.id_producto
                WHERE d.id_orden IN ($ids_str)
                ORDER BY d.id_detalle ASC";
    $res_det = $conexion->query($sql_det);
    if ($res_det) {
        while ($d = $res_det->fetch_assoc()) {
            $id_o = (int)$d['id_orden'];
            if (isset($ordenes[$id_o])) {
                $cant = (int)$d['cantidad'];
                $precio_u = (float)$d['precio_unitario'];
                $ordenes[$id_o]['items'][] = [
                    "id_detalle" => (int)$d['id_detalle'],
                    "id_producto" => (int)$d['id_producto'],
                    "nombre" => $d['nombre'],
                    "categoria" => $d['categoria'],
                    "imagen" => $d['imagen'],
                    "cantidad" => $cant,
                    "precio_unitario" => $precio_u,
                    "subtotal" => round($cant * $precio_u, 2)
                ];
            }
        }
    }
}

echo json_encode(["exito" => true, "ordenes" => array_values($ordenes)]);
$conexion->close();
?>