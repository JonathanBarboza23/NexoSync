<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id > 0) {
    try {
        $conexion->begin_transaction();
        $t_stock = nombre_tabla_stock($conexion);
        if (tabla_existe($conexion, $t_stock)) {
            $stmt_s = $conexion->prepare("DELETE FROM `$t_stock` WHERE id_producto = ?");
            $stmt_s->bind_param("i", $id);
            $stmt_s->execute();
            $stmt_s->close();
        }
        $stmt = $conexion->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conexion->commit();
        echo json_encode(["exito" => true]);
    } catch (Throwable $e) {
        $conexion->rollback();
        echo json_encode(["exito" => false, "error" => "No se pudo eliminar el producto (puede tener órdenes/ventas asociadas)."]);
    }
} else {
    echo json_encode(["exito" => false, "error" => "ID inválido."]);
}

$conexion->close();
?>
