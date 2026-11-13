<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["exito" => false, "error" => "No hay sesión iniciada."]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);
$carritoJSON = $datos['carrito'] ?? '[]';

$stmt = $conexion->prepare("UPDATE usuarios SET carrito = ? WHERE id = ?");
$stmt->bind_param("si", $carritoJSON, $_SESSION['usuario_id']);
$ok = $stmt->execute();
$stmt->close();
$conexion->close();

echo json_encode(["exito" => $ok]);
?>