<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

asegurar_stock_inicial($conexion);

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["error" => "ID de producto inválido."]);
    exit;
}

$stmt = $conexion->prepare("SELECT p.*, COALESCE(SUM(s.cantidad), 0) AS stock_total FROM productos p LEFT JOIN stock_sede s ON s.id_producto = p.id WHERE p.id = ? GROUP BY p.id");
$stmt->bind_param("i", $id);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prod) {
    echo json_encode(["error" => "Producto no encontrado."]);
    exit;
}

$stmt_s = $conexion->prepare("SELECT se.id_sede, se.nombre, COALESCE(st.cantidad, 0) AS cantidad FROM sedes se LEFT JOIN stock_sede st ON st.id_sede = se.id_sede AND st.id_producto = ? ORDER BY se.id_sede ASC");
$stmt_s->bind_param("i", $id);
$stmt_s->execute();
$res_s = $stmt_s->get_result();
$stock_sedes = [];
while ($r = $res_s->fetch_assoc()) {
    $stock_sedes[] = [
        "id" => (int)$r['id_sede'],
        "nombre" => $r['nombre'],
        "cantidad" => (int)$r['cantidad']
    ];
}
$stmt_s->close();

$prod["stock_sedes"] = $stock_sedes;
echo json_encode($prod);
$conexion->close();
?>