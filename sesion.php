<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$logueado = isset($_SESSION['usuario_id']);
$nombre = $_SESSION['nombre'] ?? null;
$email = $_SESSION['email'] ?? null;
$dni = $_SESSION['dni'] ?? null;

if ($logueado && ($nombre === null || $email === null || $dni === null)) {
    @include_once 'conexion.php';
    if (isset($conexion) && !$conexion->connect_error) {
        $uid = (int)$_SESSION['usuario_id'];
        $stmt = $conexion->prepare("SELECT nombre, email, dni, rol FROM usuarios WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $nombre = $_SESSION['nombre'] = $row['nombre'];
                $email  = $_SESSION['email']  = $row['email'];
                $dni    = $_SESSION['dni']    = $row['dni'];
                $_SESSION['rol'] = $row['rol'];
            }
            $stmt->close();
        }
    }
}

echo json_encode([
    "logueado"   => $logueado,
    "admin"      => ($_SESSION['rol'] ?? '') === 'admin',
    "usuario_id" => $_SESSION['usuario_id'] ?? null,
    "rol"        => $_SESSION['rol'] ?? null,
    "nombre"     => $nombre,
    "email"      => $email,
    "dni"        => $dni
]);
?>
