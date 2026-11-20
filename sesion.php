<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

if (!function_exists('columna_existe')) {
    function columna_existe($conexion, $tabla, $columna) {
        $t = $conexion->real_escape_string($tabla);
        $c = $conexion->real_escape_string($columna);
        $res = $conexion->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $res && $res->num_rows > 0;
    }
}

$accion = $_GET['accion'] ?? 'estado';

if ($accion === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    echo json_encode(["exito" => true]);
    exit;
}

if (!empty($_SESSION['usuario_id'])) {
    $id = intval($_SESSION['usuario_id']);
    $tiene_apellido = columna_existe($conexion, 'usuarios', 'apellido');
    $sql_select = $tiene_apellido
        ? "SELECT id, nombre, apellido, email, dni, rol, carrito FROM usuarios WHERE id = ?"
        : "SELECT id, nombre, '' AS apellido, email, dni, rol, carrito FROM usuarios WHERE id = ?";

    $stmt = $conexion->prepare($sql_select);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($u = $res->fetch_assoc()) {
        $nombre_solo = trim($u['nombre'] ?? '');
        $apellido_solo = trim($u['apellido'] ?? '');
        $nombre_completo = trim($nombre_solo . ($apellido_solo !== '' ? ' ' . $apellido_solo : ''));

        $u['id'] = (int)$u['id'];
        $u['nombre_pila'] = $nombre_solo;
        $u['apellido'] = $apellido_solo;
        $u['nombre'] = $nombre_completo;
        $u['carrito'] = $u['carrito'] ?: '[]';
        echo json_encode(["logueado" => true, "usuario" => $u]);
        $stmt->close();
        $conexion->close();
        exit;
    }
    $stmt->close();
}

echo json_encode(["logueado" => false]);
$conexion->close();
?>