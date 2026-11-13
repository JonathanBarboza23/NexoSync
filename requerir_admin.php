<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SESSION['rol'] ?? '') !== 'admin' && empty($_SESSION['db_admin_auth'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["exito" => false, "error" => "Acceso denegado. Se requieren permisos de administrador."]);
    exit;
}
?>