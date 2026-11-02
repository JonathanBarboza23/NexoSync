<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

$datos = json_decode(file_get_contents("php://input"), true);
$token = trim($datos['token'] ?? '');
$email = trim($datos['email'] ?? '');
$password = trim($datos['password'] ?? '');

if ($token === '' || $email === '' || $password === '') {
    echo json_encode(["exito" => false, "error" => "Faltan parámetros obligatorios."]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(["exito" => false, "error" => "La contraseña debe tener al menos 6 caracteres."]);
    exit;
}

// Buscar usuario con dicho token y verificar expiración si existe la columna
$sql = "SELECT id, nombre, email";
if (tiene_columna($conexion, 'usuarios', 'token_expiracion')) {
    $sql .= ", token_expiracion";
}
$sql .= " FROM usuarios WHERE email = ? AND token_recuperacion = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $email, $token);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    echo json_encode(["exito" => false, "error" => "El enlace de recuperación es inválido o ya ha sido utilizado."]);
    exit;
}

if (!empty($usuario['token_expiracion'])) {
    if (strtotime($usuario['token_expiracion']) < time()) {
        echo json_encode(["exito" => false, "error" => "El enlace de recuperación ha expirado. Por favor solicita uno nuevo."]);
        exit;
    }
}

// Hashear nueva contraseña
$passHash = password_hash($password, PASSWORD_BCRYPT);

// Actualizar contraseña y limpiar token
$stmt_upd = $conexion->prepare("UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE id = ?");
$stmt_upd->bind_param("si", $passHash, $usuario['id']);
$ok = $stmt_upd->execute();
$stmt_upd->close();
$conexion->close();

if ($ok) {
    echo json_encode(["exito" => true, "mensaje" => "Contraseña actualizada correctamente."]);
} else {
    echo json_encode(["exito" => false, "error" => "No se pudo actualizar la contraseña en el servidor."]);
}
?>
