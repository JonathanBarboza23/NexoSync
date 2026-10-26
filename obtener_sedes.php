<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

echo json_encode(["exito" => true, "sedes" => listar_sedes($conexion)]);
$conexion->close();
