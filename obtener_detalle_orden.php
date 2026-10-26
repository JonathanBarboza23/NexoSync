<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';

$id_orden = isset($_GET['id_orden']) ? intval($_GET['id_orden']) : 0;

if ($id_orden > 0) {
    $stmt = $conexion->prepare(
        "SELECT d.cantidad, d.precio_unitario, p.nombre
         FROM detalle_ordenes d
         LEFT JOIN productos p ON d.id_producto = p.id
         WHERE d.id_orden = ?"
    );
    $stmt->bind_param("i", $id_orden);
    $stmt->execute();
    $detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(["exito" => true, "detalles" => $detalles]);
} else {
    echo json_encode(["exito" => false, "error" => "ID de orden faltante."]);
}

$conexion->close();
