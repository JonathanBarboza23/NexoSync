<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$precio = floatval($_POST['precio'] ?? 0);
$categoria = trim($_POST['categoria'] ?? 'cocina');
$imagen = trim($_POST['imagen'] ?? 'img/productos/placeholder.jpg');
$stocks = json_decode($_POST['stocks'] ?? '{}', true);

if ($nombre === '' || $precio <= 0) {
    echo json_encode(["exito" => false, "error" => "Completa el nombre y un precio válido mayor a 0."]);
    exit;
}

try {
    $conexion->begin_transaction();
    $stmt = $conexion->prepare("INSERT INTO productos (nombre, descripcion, precio, categoria, imagen, url_pagina) VALUES (?, ?, ?, ?, ?, ?)");
    $url = $categoria . ".html";
    $stmt->bind_param("ssdsss", $nombre, $descripcion, $precio, $categoria, $imagen, $url);
    $stmt->execute();
    $id_nuevo = $conexion->insert_id;
    $stmt->close();

    if (is_array($stocks)) {
        foreach ($stocks as $id_sede => $cant) {
            fijar_stock_sede($conexion, $id_nuevo, (int)$id_sede, intval($cant));
        }
    } else {
        foreach (ids_sedes($conexion) as $id_sede) {
            fijar_stock_sede($conexion, $id_nuevo, (int)$id_sede, 20);
        }
    }

    $conexion->commit();
    echo json_encode(["exito" => true, "id" => $id_nuevo, "mensaje" => "Producto agregado con éxito."]);
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["exito" => false, "error" => "Error al guardar producto: " . $e->getMessage()]);
}
$conexion->close();
?>