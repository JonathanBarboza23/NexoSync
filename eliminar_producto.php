<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(["exito" => false, "error" => "ID de producto inválido."]);
    exit;
}

try {
    // 5) Verificar si el producto se encuentra en detalle_ordenes (vendido o en orden)
    if (tabla_existe($conexion, 'detalle_ordenes')) {
        $stmt_det = $conexion->prepare("SELECT COUNT(*) AS total FROM detalle_ordenes WHERE id_producto = ?");
        $stmt_det->bind_param("i", $id);
        $stmt_det->execute();
        $cant_ordenes = (int)$stmt_det->get_result()->fetch_assoc()['total'];
        $stmt_det->close();

        if ($cant_ordenes > 0) {
            echo json_encode([
                "exito" => false,
                "error" => "No es posible eliminar este producto porque ya cuenta con ventas y órdenes registradas ({$cant_ordenes} orden/es). Para darlo de baja de la tienda sin alterar el historial, pon su stock en 0."
            ]);
            exit;
        }
    }

    // 5) Verificar si el producto se encuentra en items_carrito
    if (tabla_existe($conexion, 'items_carrito')) {
        $stmt_car = $conexion->prepare("SELECT COUNT(*) AS total FROM items_carrito WHERE id_producto = ?");
        $stmt_car->bind_param("i", $id);
        $stmt_car->execute();
        $cant_carrito = (int)$stmt_car->get_result()->fetch_assoc()['total'];
        $stmt_car->close();

        if ($cant_carrito > 0) {
            echo json_encode([
                "exito" => false,
                "error" => "No se puede eliminar el producto porque se encuentra en el carrito de compras de un cliente."
            ]);
            exit;
        }
    }

    // 5) Verificar si está en carritos JSON de la tabla usuarios
    if (tiene_columna($conexion, 'usuarios', 'carrito')) {
        $p1 = '%"id":' . $id . ',%';
        $p2 = '%"id": ' . $id . ',%';
        $p3 = '%"id":' . $id . '}%';
        $p4 = '%"id": ' . $id . '}%';
        $stmt_uc = $conexion->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE carrito LIKE ? OR carrito LIKE ? OR carrito LIKE ? OR carrito LIKE ?");
        $stmt_uc->bind_param("ssss", $p1, $p2, $p3, $p4);
        $stmt_uc->execute();
        $cant_uc = (int)$stmt_uc->get_result()->fetch_assoc()['total'];
        $stmt_uc->close();

        if ($cant_uc > 0) {
            echo json_encode([
                "exito" => false,
                "error" => "No se puede eliminar el producto porque actualmente está seleccionado en el carrito de un cliente registrado."
            ]);
            exit;
        }
    }

    // Si pasa todas las validaciones, proceder con el borrado transaccional
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
    echo json_encode(["exito" => true, "mensaje" => "Producto eliminado exitosamente del catálogo e inventario."]);

} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "Error al intentar eliminar el producto: " . $e->getMessage()]);
}

$conexion->close();
?>
