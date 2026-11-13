<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$datos = json_decode(file_get_contents("php://input"), true);
$email = trim($datos['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["exito" => false, "error" => "Ingresa un correo electrónico válido."]);
    exit;
}
try {
    if (!tiene_columna($conexion, 'usuarios', 'token_recuperacion')) {
        @$conexion->query("ALTER TABLE usuarios ADD COLUMN token_recuperacion VARCHAR(100) DEFAULT NULL");
    }
    if (!tiene_columna($conexion, 'usuarios', 'token_expiracion')) {
        @$conexion->query("ALTER TABLE usuarios ADD COLUMN token_expiracion DATETIME DEFAULT NULL");
    }
} catch (Throwable $e) {}
$stmt = $conexion->prepare("SELECT id, nombre, email FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    echo json_encode(["exito" => false, "error" => "No existe ninguna cuenta registrada con este correo electrónico."]);
    exit;
}
$token = bin2hex(random_bytes(24));
$expiracion = date('Y-m-d H:i:s', time() + 3600);
$stmt_upd = $conexion->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id = ?");
$stmt_upd->bind_param("ssi", $token, $expiracion, $usuario['id']);
$stmt_upd->execute();
$stmt_upd->close();
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri = rtrim(dirname($_SERVER['REQUEST_URI'] ?? ''), '/\\');
$link_restablecer = "{$protocolo}{$host}{$uri}/restablecer_password.html?token={$token}&email=" . urlencode($email);
$mail = new PHPMailer(true);
$envio_ok = false;
$detalle_error = '';

try {
    $mail->CharSet = 'utf-8';
    $mail->isHTML(true);
    $mail->setFrom('no-reply@electrohogar.com', 'ElectroHogar Tienda');
    $mail->addAddress($email, $usuario['nombre']);
    $mail->Subject = 'Recuperación de contraseña - ElectroHogar';

    $cuerpoHtml = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <div style='background: #000; color: #fff; padding: 15px; text-align: center; border-radius: 6px;'>
            <h2 style='margin: 0;'>ElectroHogar</h2>
        </div>
        <div style='padding: 20px 10px;'>
            <h3 style='color: #333;'>Hola, " . htmlspecialchars($usuario['nombre']) . "</h3>
            <p style='color: #555; line-height: 1.6;'>Recibimos una solicitud para restablecer la contraseña de tu cuenta de cliente en <strong>ElectroHogar</strong>.</p>
            <p style='color: #555; line-height: 1.6;'>Para crear una nueva contraseña, haz clic en el siguiente botón:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$link_restablecer}' style='background: #007bff; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Restablecer mi Contraseña</a>
            </div>
            <p style='font-size: 13px; color: #777;'>O copia y pega este enlace en tu navegador:<br><a href='{$link_restablecer}'>{$link_restablecer}</a></p>
            <p style='font-size: 12px; color: #999; margin-top: 25px;'>Este enlace expirará en 60 minutos. Si tú no solicitaste este cambio, puedes ignorar este mensaje.</p>
        </div>
    </div>";

    $mail->Body = $cuerpoHtml;
    $mail->AltBody = "Hola {$usuario['nombre']}. Para restablecer tu contraseña en ElectroHogar, ingresa a: {$link_restablecer}";

    $envio_ok = $mail->send();
} catch (Exception $e) {
    $detalle_error = $mail->getErrorInfo();
}

$conexion->close();

echo json_encode([
    "exito" => true,
    "mensaje" => "Se ha generado la solicitud de recuperación para " . htmlspecialchars($email) . ".",
    "envio_correo" => $envio_ok,
    "link_simulado" => $link_restablecer
]);
?>
