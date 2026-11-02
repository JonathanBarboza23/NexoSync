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

// 1) Validación estricta de Nombre y Apellido: solo letras, espacios, acentos y '
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'-]{2,100}$/u', $nombre)) {
    echo json_encode(["exito" => false, "error" => "El nombre y apellido solo puede contener letras, espacios, acentos y comillas simples (')."]);
    exit;
}

// Validación de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["exito" => false, "error" => "El correo electrónico no es válido."]);
    exit;
}

// Validación de DNI
if (!preg_match('/^\d{6,11}$/', $dni)) {
    echo json_encode(["exito" => false, "error" => "El DNI debe contener solo números (6 a 11 dígitos)."]);
    exit;
}

// Validación de contraseña
if (strlen($password) < 6) {
    echo json_encode(["exito" => false, "error" => "La contraseña debe tener al menos 6 caracteres."]);
    exit;
}

// 3) Validar DNI único en la base de datos (datos únicos por usuario)
$stmt_dni = $conexion->prepare("SELECT id FROM usuarios WHERE dni = ?");
$stmt_dni->bind_param("s", $dni);
$stmt_dni->execute();
$stmt_dni->store_result();
if ($stmt_dni->num_rows > 0) {
    echo json_encode(["exito" => false, "error" => "El DNI ingresado ya se encuentra registrado con otro usuario. Debe ser único."]);
    exit;
}
$stmt_dni->close();

// Validar correo único
$stmt_email = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt_email->bind_param("s", $email);
$stmt_email->execute();
$stmt_email->store_result();
if ($stmt_email->num_rows > 0) {
    echo json_encode(["exito" => false, "error" => "El correo electrónico ya está registrado."]);
    exit;
}
$stmt_email->close();

$password_encriptada = password_hash($password, PASSWORD_BCRYPT);
$carrito_vacio = '[]';

$stmt = $conexion->prepare(
    "INSERT INTO usuarios (nombre, dni, email, password, rol, carrito) VALUES (?, ?, ?, ?, 'cliente', ?)"
);
$stmt->bind_param("sssss", $nombre, $dni, $email, $password_encriptada, $carrito_vacio);

if ($stmt->execute()) {
    // Sincronizar también con la tabla `clientes` de electrohogar.sql
    obtener_o_crear_cliente($conexion, $nombre, $dni, $email);
    echo json_encode(["exito" => true, "mensaje" => "Usuario registrado correctamente."]);
} else {
    echo json_encode(["exito" => false, "error" => "No se pudo registrar el usuario en la base de datos."]);
}

$stmt->close();
$conexion->close();
?>
