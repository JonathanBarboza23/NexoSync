<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$id_orden = intval($_POST['id_orden'] ?? 0);
$id_estado = intval($_POST['id_estado'] ?? 0);
$nombre_estado = trim($_POST['nombre_estado'] ?? '');

if ($id_orden <= 0) {
    echo json_encode(["exito" => false, "error" => "ID de orden inválido."]);
    exit;
}

if ($id_estado <= 0 && $nombre_estado !== '') {
    $id_estado = obtener_id_estado_por_nombre($conexion, $nombre_estado, 1);
}

if ($id_estado <= 0) {
    echo json_encode(["exito" => false, "error" => "Estado seleccionado inválido."]);
    exit;
}

try {
    $stmt = $conexion->prepare("UPDATE ordenes SET id_estado = ? WHERE id_orden = ?");
    $stmt->bind_param("ii", $id_estado, $id_orden);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(["exito" => $ok, "id_orden" => $id_orden, "id_estado" => $id_estado]);
} catch (Throwable $e) {
    echo json_encode(["exito" => false, "error" => "No se pudo actualizar el estado: " . $e->getMessage()]);
}

$conexion->close();
?>