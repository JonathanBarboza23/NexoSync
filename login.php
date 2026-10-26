<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["exito" => false, "error" => "Método no permitido."]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(["exito" => false, "error" => "Completa el correo y la contraseña."]);
    exit;
}

$stmt = $conexion->prepare(
    "SELECT id, nombre, email, password, rol, dni, carrito FROM usuarios WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($usuario && password_verify($password, $usuario['password'])) {
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['nombre'] = $usuario['nombre'] ?? '';
    $_SESSION['email'] = $usuario['email'] ?? '';
    $_SESSION['dni'] = $usuario['dni'] ?? '';
    unset($usuario['password']);
    echo json_encode(["exito" => true, "usuario" => $usuario]);
} else {
    echo json_encode(["exito" => false, "error" => "Correo o contraseña incorrectos."]);
}

$conexion->close();
