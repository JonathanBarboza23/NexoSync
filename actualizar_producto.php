<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$precio = isset($_POST['precio']) ? floatval($_POST['precio']) : -1;
$precio_anterior = isset($_POST['precio_anterior']) && $_POST['precio_anterior'] !== '' ? floatval($_POST['precio_anterior']) : null;
$stocks = json_decode($_POST['stocks'] ?? '{}', true);

if ($id <= 0 || $precio <= 0 || !is_array($stocks)) {
    echo json_encode(["exito" => false, "error" => "Datos inválidos (el precio debe ser mayor a 0)."]);
    exit;
}

$sedes_validas = ids_sedes($conexion);
foreach ($stocks as $id_sede => $cantidad) {
    if (!in_array((int)$id_sede, $sedes_validas, true)) {
        echo json_encode(["exito" => false, "error" => "Sede inválida."]);
        exit;
    }
    if (!is_numeric($cantidad) || intval($cantidad) < 0) {
        echo json_encode(["exito" => false, "error" => "Stock inválido: debe ser un número igual o mayor a 0."]);
        exit;
    }
}

try {
    $conexion->begin_transaction();
    $ok = true;

    if ($precio_anterior !== null && tiene_columna($conexion, 'productos', 'precio_anterior')) {
        $val_ant = $precio_anterior > 0 ? $precio_anterior : null;
        if (tiene_columna($conexion, 'productos', 'en_oferta')) {
            $en_of = ($val_ant !== null && $val_ant > $precio) ? 1 : 0;
            $stmt = $conexion->prepare("UPDATE productos SET precio = ?, precio_anterior = ?, en_oferta = ? WHERE id = ?");
            $stmt->bind_param("ddii", $precio, $val_ant, $en_of, $id);
        } else {
            $stmt = $conexion->prepare("UPDATE productos SET precio = ?, precio_anterior = ? WHERE id = ?");
            $stmt->bind_param("ddi", $precio, $val_ant, $id);
        }
    } else {
        $stmt = $conexion->prepare("UPDATE productos SET precio = ? WHERE id = ?");
        $stmt->bind_param("di", $precio, $id);
    }
    $ok = $stmt->execute();
    $stmt->close();

    foreach ($stocks as $id_sede => $cantidad) {
        if (!$ok) break;
        $ok = fijar_stock_sede($conexion, $id, (int)$id_sede, intval($cantidad));
    }

    if ($ok) {
        sincronizar_stock_total($conexion, $id);
        $conexion->commit();
        echo json_encode(["exito" => true]);
    } else {
        $conexion->rollback();
        echo json_encode(["exito" => false, "error" => "Error SQL al actualizar el producto."]);
    }
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "Error SQL: " . $e->getMessage()]);
}

$conexion->close();
?>
