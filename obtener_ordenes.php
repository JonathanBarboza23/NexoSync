<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$estados = [];
$t_est = nombre_tabla_estado($conexion);
if (tabla_existe($conexion, $t_est)) {
    try {
        $res_e = $conexion->query("SELECT id_estado, nombre_estado FROM `$t_est` ORDER BY id_estado ASC");
        if ($res_e) {
            while ($e = $res_e->fetch_assoc()) {
                $estados[] = [
                    "id_estado" => (int)$e['id_estado'],
                    "nombre_estado" => $e['nombre_estado']
                ];
            }
        }
    } catch (Throwable $ex) {}
}

if (empty($estados)) {
    $estados = [
        ["id_estado" => 1, "nombre_estado" => "En proceso"],
        ["id_estado" => 2, "nombre_estado" => "Confirmada"],
        ["id_estado" => 3, "nombre_estado" => "Lista para retiro"],
        ["id_estado" => 4, "nombre_estado" => "Completado"],
        ["id_estado" => 5, "nombre_estado" => "Cancelado"]
    ];
}

$expr_nombre_cliente = columna_existe($conexion, 'usuarios', 'apellido')
    ? "TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')))"
    : "u.nombre";

$sql = "SELECT o.id_orden, o.codigo_seguimiento, o.total, o.tipo_entrega, o.direccion, o.fecha, o.id_estado,
               COALESCE(e.nombre_estado, 'En proceso') AS estado_nombre,
               $expr_nombre_cliente AS nombre_cliente, u.email AS email_cliente, u.dni AS dni_cliente,
               s.nombre AS nombre_sede
        FROM ordenes o
        LEFT JOIN usuarios u ON u.id = o.id_cliente
        LEFT JOIN estado e ON e.id_estado = o.id_estado
        LEFT JOIN sedes s ON s.id_sede = o.id_sede_origen
        ORDER BY o.id_orden DESC";

$res = $conexion->query($sql);
$ordenes = [];
if ($res) {
    while ($f = $res->fetch_assoc()) {
        $id_ord = (int)$f['id_orden'];
        $f['items'] = [];
        $ordenes[$id_ord] = $f;
    }
}

if (!empty($ordenes)) {
    try {
        $sql_det = "SELECT d.id_detalle, d.id_orden, d.id_producto, d.cantidad, d.precio_unitario,
                           COALESCE(p.nombre, 'Producto ElectroHogar') AS nombre,
                           COALESCE(p.categoria, 'general') AS categoria,
                           COALESCE(p.imagen, 'img/productos/placeholder.jpg') AS imagen
                    FROM detalle_ordenes d
                    LEFT JOIN productos p ON p.id = d.id_producto
                    ORDER BY d.id_detalle ASC";
        $res_det = $conexion->query($sql_det);
        if ($res_det) {
            while ($d = $res_det->fetch_assoc()) {
                $id_o = (int)$d['id_orden'];
                if (isset($ordenes[$id_o])) {
                    $cant = (int)$d['cantidad'];
                    $precio_u = (float)$d['precio_unitario'];
                    $ordenes[$id_o]['items'][] = [
                        "id_detalle" => (int)$d['id_detalle'],
                        "id_producto" => (int)$d['id_producto'],
                        "nombre" => $d['nombre'],
                        "categoria" => $d['categoria'],
                        "imagen" => $d['imagen'],
                        "cantidad" => $cant,
                        "precio_unitario" => $precio_u,
                        "subtotal" => round($cant * $precio_u, 2)
                    ];
                }
            }
        }
    } catch (Throwable $ex_det) {}
}

$ordenes_lista = array_values($ordenes);

$estadisticas_categorias = [];
try {
    $sql_stats = "SELECT p.id, p.nombre, LOWER(TRIM(p.categoria)) AS categoria, p.precio,
                         COALESCE(SUM(CASE WHEN LOWER(COALESCE(e.nombre_estado, '')) LIKE '%cancel%' THEN 0 ELSE d.cantidad END), 0) AS total_vendido,
                         COALESCE(SUM(CASE WHEN LOWER(COALESCE(e.nombre_estado, '')) LIKE '%cancel%' THEN 0 ELSE (d.cantidad * d.precio_unitario) END), 0) AS monto_vendido
                  FROM productos p
                  LEFT JOIN detalle_ordenes d ON d.id_producto = p.id
                  LEFT JOIN ordenes o ON o.id_orden = d.id_orden
                  LEFT JOIN estado e ON e.id_estado = o.id_estado
                  GROUP BY p.id, p.nombre, p.categoria, p.precio";
    $res_stats = $conexion->query($sql_stats);
    $por_cat = [];
    if ($res_stats) {
        while ($row = $res_stats->fetch_assoc()) {
            $cat = $row['categoria'] ?: 'otros';
            if (!isset($por_cat[$cat])) {
                $por_cat[$cat] = [];
            }
            $por_cat[$cat][] = [
                "id" => (int)$row['id'],
                "nombre" => $row['nombre'],
                "categoria" => $cat,
                "precio" => (float)$row['precio'],
                "total_vendido" => (int)$row['total_vendido'],
                "monto_vendido" => (float)$row['monto_vendido']
            ];
        }
    }

    foreach ($por_cat as $cat => $lista) {
        $mas_vendidos = $lista;
        usort($mas_vendidos, function($a, $b) {
            if ($b['total_vendido'] !== $a['total_vendido']) {
                return $b['total_vendido'] <=> $a['total_vendido'];
            }
            return $a['id'] <=> $b['id'];
        });

        $menos_vendidos = $lista;
        usort($menos_vendidos, function($a, $b) {
            if ($a['total_vendido'] !== $b['total_vendido']) {
                return $a['total_vendido'] <=> $b['total_vendido'];
            }
            return $a['id'] <=> $b['id'];
        });

        $total_unidades_cat = array_sum(array_column($lista, 'total_vendido'));
        $total_monto_cat = array_sum(array_column($lista, 'monto_vendido'));

        $estadisticas_categorias[$cat] = [
            "categoria" => $cat,
            "total_unidades" => (int)$total_unidades_cat,
            "total_facturado" => (float)$total_monto_cat,
            "top_mas_vendidos" => array_slice($mas_vendidos, 0, 3),
            "top_menos_vendidos" => array_slice($menos_vendidos, 0, 3)
        ];
    }
} catch (Throwable $ex_st) {}

echo json_encode([
    "exito" => true,
    "ordenes" => $ordenes_lista,
    "estados" => $estados,
    "estadisticas_categorias" => $estadisticas_categorias
]);
$conexion->close();
?>