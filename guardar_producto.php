<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$categorias_validas = ['cocina', 'lavado', 'tecnologia'];

$nombre = trim($_POST['nombre'] ?? '');
$precio = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;
$precio_anterior = isset($_POST['precio_anterior']) && $_POST['precio_anterior'] !== '' ? floatval($_POST['precio_anterior']) : null;
$descripcion = trim($_POST['descripcion'] ?? '');
$categoria = strtolower(trim($_POST['categoria'] ?? ''));
$stocks = json_decode($_POST['stocks'] ?? '{}', true);
$imagen = trim($_POST['imagen'] ?? '');
if ($imagen === '') {
    $imagen = "img/productos/placeholder.jpg";
}

if ($nombre === '' || $precio <= 0) {
    echo json_encode(["exito" => false, "error" => "Faltan datos requeridos (nombre y precio mayor a 0)."]);
    exit;
}
if (!in_array($categoria, $categorias_validas, true)) {
    echo json_encode(["exito" => false, "error" => "Categoría inválida."]);
    exit;
}
if (!is_array($stocks)) $stocks = [];
foreach ($stocks as $cantidad) {
    if (!is_numeric($cantidad) || intval($cantidad) < 0) {
        echo json_encode(["exito" => false, "error" => "El stock no puede ser negativo."]);
        exit;
    }
}

try {
    $conexion->begin_transaction();

    $cols = ['nombre', 'precio', 'categoria', 'imagen'];
    $vals = ['?', '?', '?', '?'];
    $types = 'sdss';
    $params = [$nombre, $precio, $categoria, $imagen];

    if (tiene_columna($conexion, 'productos', 'descripcion')) {
        $cols[] = 'descripcion';
        $vals[] = '?';
        $types .= 's';
        $params[] = $descripcion !== '' ? $descripcion : 'Garantía oficial de 12 meses. Entrega inmediata en sucursal o envío a domicilio.';
    }
    if (tiene_columna($conexion, 'productos', 'url_pagina')) {
        $cols[] = 'url_pagina';
        $vals[] = '?';
        $types .= 's';
        $params[] = $categoria . '.html';
    }
    if (tiene_columna($conexion, 'productos', 'stock')) {
        $cols[] = 'stock';
        $vals[] = '0';
    }
    if (tiene_columna($conexion, 'productos', 'precio_anterior') && $precio_anterior !== null && $precio_anterior > 0) {
        $cols[] = 'precio_anterior';
        $vals[] = '?';
        $types .= 'd';
        $params[] = $precio_anterior;
    }
    if (tiene_columna($conexion, 'productos', 'en_oferta')) {
        $cols[] = 'en_oferta';
        $vals[] = ($precio_anterior !== null && $precio_anterior > $precio) ? '1' : '0';
    }

    $sql = "INSERT INTO productos (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $id_producto = $conexion->insert_id;
    $stmt->close();

    if ($ok) {
        foreach (listar_sedes($conexion) as $sede) {
            $cantidad = intval($stocks[$sede['id']] ?? 0);
            if (!fijar_stock_sede($conexion, $id_producto, $sede['id'], $cantidad)) {
                $ok = false;
                break;
            }
        }
    }

    if ($ok) {
        sincronizar_stock_total($conexion, $id_producto);
        $conexion->commit();
        echo json_encode(["exito" => true, "id" => $id_producto]);
    } else {
        $conexion->rollback();
        echo json_encode(["exito" => false, "error" => "Error al registrar el stock del producto."]);
    }
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "Error SQL: " . $e->getMessage()]);
}

$conexion->close();
?>
