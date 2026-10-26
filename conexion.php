<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = "sql305.infinityfree.com";
$user = "if0_43204352";
$pass = "Electro1604";
$dbname = "if0_43204352_electrohogar";
try {
    $conexion = new mysqli($host, $user, $pass, $dbname);
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(["exito" => false, "error" => "Error de conexión a la base de datos: " . $e->getMessage()]);
    exit;
}
?>
