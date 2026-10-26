<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$datos = json_decode(file_get_contents("php://input"), true);
$id = intval($datos['id'] ?? 0);
$id_estado = intval($datos['id_estado'] ?? 0);

if ($id > 0 && $id_estado > 0) {
    if ($id_estado === 2) {
        $id_estado = obtener_id_estado_por_nombre($conexion, 'completad', 5);
    }
    $pk = tiene_columna($conexion, 'ordenes', 'id_orden') ? 'id_orden' : 'id';
    $stmt = $conexion->prepare("UPDATE ordenes SET id_estado = ? WHERE `$pk` = ?");
    $stmt->bind_param("ii", $id_estado, $id);
    echo json_encode(["exito" => $stmt->execute()]);
    $stmt->close();
} else {
    echo json_encode(["exito" => false, "error" => "Datos inválidos."]);
}

$conexion->close();
?>
