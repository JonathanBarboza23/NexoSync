<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["exito" => false, "error" => "Debes iniciar sesión para consultar tus compras."]);
    exit;
}

$id_usuario = (int)$_SESSION['usuario_id'];

$sql = "SELECT o.id_orden, o.codigo_seguimiento, o.total, o.tipo_entrega, o.direccion, o.fecha,
               COALESCE(e.nombre_estado, 'Pendiente') AS estado_nombre,
               COALESCE(s.nombre, 'Sucursal Central') AS nombre_sede
        FROM ordenes o
        LEFT JOIN estado e ON e.id_estado = o.id_estado
        LEFT JOIN sedes s ON s.id_sede = o.id_sede_origen
        WHERE o.id_cliente = ?
        ORDER BY o.id_orden DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$res = $stmt->get_result();

$ordenes = [];
$stmt_det = $conexion->prepare(
    "SELECT d.id_detalle, d.cantidad, d.precio_unitario, p.nombre, p.imagen, p.id AS id_producto
     FROM detalle_ordenes d
     LEFT JOIN productos p ON p.id = d.id_producto
     WHERE d.id_orden = ?"
);

while ($o = $res->fetch_assoc()) {
    $id_ord = (int)$o['id_orden'];
    $stmt_det->bind_param("i", $id_ord);
    $stmt_det->execute();
    $res_det = $stmt_det->get_result();

    $items = [];
    while ($it = $res_det->fetch_assoc()) {
        $items[] = [
            "id_producto" => (int)$it['id_producto'],
            "nombre" => $it['nombre'] ?? 'Producto ElectroHogar',
            "imagen" => $it['imagen'] ?? 'img/productos/placeholder.jpg',
            "cantidad" => (int)$it['cantidad'],
            "precio_unitario" => (float)$it['precio_unitario'],
            "subtotal" => (float)($it['cantidad'] * $it['precio_unitario'])
        ];
    }

    $codigo = $o['codigo_seguimiento'] ?: ('#EH-' . $o['id_orden']);
    $datosQR = "ELECTROHOGAR|ORDEN:{$codigo}|CLIENTE_ID:{$id_usuario}|TOTAL:{$o['total']}";
    $urlQR = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($datosQR);

    $ordenes[] = [
        "id_orden" => $id_ord,
        "codigo_seguimiento" => $codigo,
        "total" => (float)$o['total'],
        "tipo_entrega" => $o['tipo_entrega'],
        "direccion" => $o['direccion'],
        "nombre_sede" => $o['nombre_sede'],
        "estado_nombre" => $o['estado_nombre'],
        "fecha" => $o['fecha'],
        "url_qr" => $urlQR,
        "items" => $items
    ];
}

$stmt_det->close();
$stmt->close();
$conexion->close();

echo json_encode(["exito" => true, "ordenes" => $ordenes]);
?>