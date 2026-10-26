<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

asegurar_stock_inicial($conexion);

$categoria = $_GET['categoria'] ?? 'todos';
$t_stock = nombre_tabla_stock($conexion);
$tiene_tabla_stock = tabla_existe($conexion, $t_stock);
$tiene_en_oferta = tiene_columna($conexion, 'productos', 'en_oferta');
$tiene_precio_ant = tiene_columna($conexion, 'productos', 'precio_anterior');

if ($categoria === 'ofertas') {
    if ($tiene_en_oferta && $tiene_precio_ant) {
        $where = "WHERE (p.en_oferta = 1 OR p.precio_anterior > 0)";
    } elseif ($tiene_precio_ant) {
        $where = "WHERE p.precio_anterior > 0";
    } elseif ($tiene_en_oferta) {
        $where = "WHERE p.en_oferta = 1";
    } else {
        $where = "";
    }
} elseif ($categoria !== 'todos') {
    $where = "WHERE LOWER(p.categoria) = LOWER(?)";
} else {
    $where = "";
}

try {
    if ($tiene_tabla_stock) {
        $sql = "SELECT p.*, (SELECT COALESCE(SUM(s.cantidad), 0) FROM `$t_stock` s WHERE s.id_producto = p.id) AS stock_total FROM productos p $where ORDER BY p.id ASC";
    } else {
        $sql = "SELECT p.*, COALESCE(p.stock, 0) AS stock_total FROM productos p $where ORDER BY p.id ASC";
    }
    $stmt = $conexion->prepare($sql);
    if ($categoria !== 'todos' && $categoria !== 'ofertas') {
        $stmt->bind_param("s", $categoria);
    }
    $stmt->execute();
    $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($productos as &$prod) {
        $prod['imagen'] = resolver_imagen_producto($prod['imagen'] ?? '');
    }
    unset($prod);

    echo json_encode($productos);
} catch (Throwable $e) {
    echo json_encode([]);
}
$conexion->close();
?>
