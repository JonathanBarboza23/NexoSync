<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

asegurar_stock_inicial($conexion);

$stock = [];
$res = $conexion->query("SELECT id_producto, id_sede, cantidad FROM stock_sede");
if ($res) {
    while ($f = $res->fetch_assoc()) {
        $stock[$f['id_producto']][$f['id_sede']] = (int)$f['cantidad'];
    }
}

echo json_encode([
    "exito" => true,
    "sedes" => listar_sedes($conexion),
    "stock" => (object)$stock
]);
$conexion->close();
?>