<?php
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

try {
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true);
    if (!is_array($datos)) {
        $datos = $_POST;
    }

    $nombre = trim($datos['nombre'] ?? '');
    $apellido = trim($datos['apellido'] ?? '');
    $dni = trim($datos['dni'] ?? '');
    $email = trim($datos['email'] ?? '');
    $password_plana = trim($datos['password'] ?? '');

    if ($nombre !== '' && $apellido === '' && strpos($nombre, ' ') !== false) {
        $partes = preg_split('/\s+/', $nombre, 2);
        $nombre = trim($partes[0]);
        $apellido = trim($partes[1] ?? '');
    }

    if ($nombre === '' || $apellido === '' || $dni === '' || $email === '' || $password_plana === '') {
        echo json_encode(["exito" => false, "error" => "Todos los campos son obligatorios (Nombre, Apellido, DNI, Email y Contraseña)."]);
        exit;
    }

    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]+$/u", $nombre) || strlen($nombre) < 2) {
        echo json_encode(["exito" => false, "error" => "Carácter inválido, por favor intente nuevamente."]);
        exit;
    }

    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]+$/u", $apellido) || strlen($apellido) < 2) {
        echo json_encode(["exito" => false, "error" => "Carácter inválido, por favor intente nuevamente."]);
        exit;
    }

    if (!preg_match("/^\d{6,11}$/", $dni)) {
        echo json_encode(["exito" => false, "error" => "El DNI debe contener únicamente números (entre 6 y 11 dígitos)."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["exito" => false, "error" => "El formato del correo electrónico no es válido."]);
        exit;
    }

    if (strlen($password_plana) < 4) {
        echo json_encode(["exito" => false, "error" => "La contraseña debe tener al menos 4 caracteres."]);
        exit;
    }

    if (!columna_existe($conexion, 'usuarios', 'apellido')) {
        try {
            $conexion->query("ALTER TABLE usuarios ADD COLUMN apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre");
        } catch (Throwable $e) {}
    }

    $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE email = ? OR dni = ?");
    $stmt_check->bind_param("ss", $email, $dni);
    $stmt_check->execute();
    $resultado = $stmt_check->get_result();

    if ($resultado->num_rows > 0) {
        echo json_encode(["exito" => false, "error" => "El correo electrónico o el DNI ya se encuentran registrados."]);
        $stmt_check->close();
        $conexion->close();
        exit;
    }
    $stmt_check->close();

    $rol = "cliente";
    $password_hash = password_hash($password_plana, PASSWORD_DEFAULT);

    if (columna_existe($conexion, 'usuarios', 'apellido')) {
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, apellido, dni, email, password, rol) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $nombre, $apellido, $dni, $email, $password_hash, $rol);
    } else {
        $nombre_completo = trim($nombre . ' ' . $apellido);
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, dni, email, password, rol) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nombre_completo, $dni, $email, $password_hash, $rol);
    }

    if ($stmt->execute()) {
        echo json_encode(["exito" => true]);
    } else {
        echo json_encode(["exito" => false, "error" => "Error al registrar usuario en la base de datos."]);
    }
    $stmt->close();
} catch (Throwable $e) {
    echo json_encode(["exito" => false, "error" => "No se pudo completar el registro: " . $e->getMessage()]);
}

$conexion->close();
?>