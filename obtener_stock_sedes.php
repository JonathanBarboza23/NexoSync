<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

asegurar_stock_inicial($conexion);

$t_stock = nombre_tabla_stock($conexion);
$stock = [];
if (tabla_existe($conexion, $t_stock)) {
    try {
        $res = $conexion->query("SELECT id_producto, id_sede, cantidad FROM `$t_stock`");
        if ($res) {
            while ($f = $res->fetch_assoc()) {
                $stock[$f['id_producto']][$f['id_sede']] = (int)$f['cantidad'];
            }
        }
    } catch (Throwable $e) {}
}

echo json_encode(["exito" => true, "sedes" => listar_sedes($conexion), "stock" => (object)$stock]);
$conexion->close();
?>
