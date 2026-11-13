<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';

$id_orden = intval($_GET['id_orden'] ?? 0);

$sql = "SELECT d.id_detalle, d.cantidad, d.precio_unitario, p.nombre, p.imagen
        FROM detalle_ordenes d
        LEFT JOIN productos p ON p.id = d.id_producto
        WHERE d.id_orden = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_orden);
$stmt->execute();
$res = $stmt->get_result();

$detalles = [];
while ($r = $res->fetch_assoc()) {
    $detalles[] = $r;
}
$stmt->close();
$conexion->close();

echo json_encode(["exito" => true, "detalles" => $detalles]);
?>