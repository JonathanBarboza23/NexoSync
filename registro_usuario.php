<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

$datos = json_decode(file_get_contents("php://input"), true);

if (!isset($datos['nombre'], $datos['email'], $datos['dni'], $datos['password'])) {
    echo json_encode(["exito" => false, "error" => "Faltan datos obligatorios."]);
    exit;
}

$nombre = trim($datos['nombre']);
$email = trim($datos['email']);
$dni = trim($datos['dni']);
$password = $datos['password'];

if ($nombre === '' || strlen($nombre) > 100) {
    echo json_encode(["exito" => false, "error" => "Nombre inválido."]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["exito" => false, "error" => "El correo electrónico no es válido."]);
    exit;
}
if (!preg_match('/^\d{6,11}$/', $dni)) {
    echo json_encode(["exito" => false, "error" => "El DNI/CUIT debe tener solo números (6 a 11 dígitos)."]);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(["exito" => false, "error" => "La contraseña debe tener al menos 6 caracteres."]);
    exit;
}

$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(["exito" => false, "error" => "El correo ya está registrado."]);
    exit;
}
$stmt->close();

$password_encriptada = password_hash($password, PASSWORD_BCRYPT);
$carrito_vacio = '[]';

$stmt = $conexion->prepare(
    "INSERT INTO usuarios (nombre, dni, email, password, rol, carrito) VALUES (?, ?, ?, ?, 'cliente', ?)"
);
$stmt->bind_param("sssss", $nombre, $dni, $email, $password_encriptada, $carrito_vacio);

if ($stmt->execute()) {
    obtener_o_crear_cliente($conexion, $nombre, $dni, $email);
    echo json_encode(["exito" => true, "mensaje" => "Usuario registrado correctamente."]);
} else {
    echo json_encode(["exito" => false, "error" => "No se pudo registrar el usuario."]);
}

$stmt->close();
$conexion->close();
?>
