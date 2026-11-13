<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

asegurar_stock_inicial($conexion);

$categoria = $_GET['categoria'] ?? 'todos';
$sql = "SELECT p.*, COALESCE(SUM(s.cantidad), 0) AS stock_total
        FROM productos p
        LEFT JOIN stock_sede s ON s.id_producto = p.id ";

$params = [];
$types = "";

if ($categoria === 'ofertas') {
    $sql .= "WHERE p.en_oferta = 1 OR (p.precio_anterior IS NOT NULL AND p.precio_anterior > p.precio) ";
} elseif ($categoria !== 'todos' && $categoria !== '') {
    $sql .= "WHERE LOWER(p.categoria) = LOWER(?) ";
    $params[] = $categoria;
    $types .= "s";
}

$sql .= "GROUP BY p.id ORDER BY p.id ASC";

$stmt = $conexion->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$productos = [];
while ($row = $res->fetch_assoc()) {
    $productos[] = $row;
}

echo json_encode($productos);
$conexion->close();
?>