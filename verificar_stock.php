<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

$datos = json_decode(file_get_contents("php://input"), true);
$ids = array_map('intval', $datos['productos'] ?? []);
$ids = array_values(array_unique(array_filter($ids, function($i) { return $i > 0; })));

$t_stock = nombre_tabla_stock($conexion);
$tiene_tabla_stock = tabla_existe($conexion, $t_stock);

$stock = [];
if (count($ids) > 0) {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    if ($tiene_tabla_stock) {
        $sql = "SELECT p.id, COALESCE(SUM(s.cantidad), 0) AS stock
                FROM productos p
                LEFT JOIN `$t_stock` s ON s.id_producto = p.id
                WHERE p.id IN ($marcadores)
                GROUP BY p.id";
    } else {
        $sql = "SELECT p.id, COALESCE(p.stock, 0) AS stock
                FROM productos p
                WHERE p.id IN ($marcadores)";
    }
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $stock[$fila['id']] = (int)$fila['stock'];
    }
    $stmt->close();
}

echo json_encode(["exito" => true, "stock" => $stock]);
$conexion->close();
?>
