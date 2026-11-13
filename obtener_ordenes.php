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

$sql = "SELECT o.id_orden, o.codigo_seguimiento, o.total, o.tipo_entrega, o.fecha, o.id_estado,
               COALESCE(e.nombre_estado, 'En proceso') AS estado_nombre,
               u.nombre AS nombre_cliente, u.email AS email_cliente, u.dni AS dni_cliente,
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
        $ordenes[] = $f;
    }
}

echo json_encode(["exito" => true, "ordenes" => $ordenes, "estados" => $estados]);
$conexion->close();
?>