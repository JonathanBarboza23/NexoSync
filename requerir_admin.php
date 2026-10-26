<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["exito" => false, "error" => "Acceso denegado."]);
    exit;
}
