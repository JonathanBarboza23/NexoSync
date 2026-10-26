<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["exito" => false, "error" => "No hay sesión iniciada."]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);

if (isset($datos['carrito']) && is_string($datos['carrito'])) {
    $id_usuario = (int)$_SESSION['usuario_id'];
    $carrito = $datos['carrito'];

    $stmt = $conexion->prepare("UPDATE usuarios SET carrito = ? WHERE id = ?");
    $stmt->bind_param("si", $carrito, $id_usuario);
    echo json_encode(["exito" => $stmt->execute()]);
    $stmt->close();
} else {
    echo json_encode(["exito" => false, "error" => "Datos inválidos."]);
}

$conexion->close();
