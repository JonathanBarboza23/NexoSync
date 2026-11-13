<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$precio = isset($_POST['precio']) ? floatval($_POST['precio']) : -1;
$stocks = json_decode($_POST['stocks'] ?? '{}', true);

if ($id <= 0 || $precio <= 0 || !is_array($stocks)) {
    echo json_encode(["exito" => false, "error" => "Datos inválidos (el precio debe ser mayor a 0)."]);
    exit;
}

try {
    $conexion->begin_transaction();
    $stmt = $conexion->prepare("UPDATE productos SET precio = ? WHERE id = ?");
    $stmt->bind_param("di", $precio, $id);
    $stmt->execute();
    $stmt->close();

    foreach ($stocks as $id_sede => $cantidad) {
        fijar_stock_sede($conexion, $id, (int)$id_sede, intval($cantidad));
    }

    $conexion->commit();
    echo json_encode(["exito" => true, "mensaje" => "Producto y stock actualizados exitosamente."]);
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "Error SQL: " . $e->getMessage()]);
}
$conexion->close();
?>