<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

try {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $raw = file_get_contents("php://input");
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                if ($email === '') $email = trim($json['email'] ?? '');
                if ($password === '') $password = trim($json['password'] ?? '');
            }
        }
    }

    if ($email === '' || $password === '') {
        echo json_encode(["exito" => false, "error" => "Completa correo y contraseña."]);
        exit;
    }

    $tiene_apellido = columna_existe($conexion, 'usuarios', 'apellido');
    $tiene_carrito = columna_existe($conexion, 'usuarios', 'carrito');
    $campo_apellido = $tiene_apellido ? "apellido" : "'' AS apellido";
    $campo_carrito = $tiene_carrito ? "carrito" : "'[]' AS carrito";

    $sql_select = "SELECT id, nombre, $campo_apellido, email, dni, password, rol, $campo_carrito FROM usuarios WHERE email = ?";

    $stmt = $conexion->prepare($sql_select);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($fila = $resultado->fetch_assoc()) {
        $hash_guardado = $fila['password'];
        $login_valido = false;

        if (password_verify($password, $hash_guardado)) {
            $login_valido = true;
        } elseif ($password === $hash_guardado) {
            $login_valido = true;
            $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param("si", $nuevo_hash, $fila['id']);
                $upd->execute();
                $upd->close();
            }
        }

        if ($login_valido) {
            $nombre_solo = trim($fila['nombre'] ?? '');
            $apellido_solo = trim($fila['apellido'] ?? '');
            $nombre_completo = trim($nombre_solo . ($apellido_solo !== '' ? ' ' . $apellido_solo : ''));

            $_SESSION['usuario_id'] = (int)$fila['id'];
            $_SESSION['nombre'] = $nombre_completo;
            $_SESSION['rol'] = $fila['rol'];

            echo json_encode([
                "exito" => true,
                "usuario" => [
                    "id" => (int)$fila['id'],
                    "nombre" => $nombre_completo,
                    "nombre_pila" => $nombre_solo,
                    "apellido" => $apellido_solo,
                    "email" => $fila['email'],
                    "dni" => $fila['dni'],
                    "rol" => $fila['rol'],
                    "carrito" => !empty($fila['carrito']) ? $fila['carrito'] : '[]'
                ]
            ]);
            $stmt->close();
            $conexion->close();
            exit;
        }
    }

    $stmt->close();
    echo json_encode(["exito" => false, "error" => "Correo o contraseña incorrectos."]);
} catch (Throwable $e) {
    echo json_encode(["exito" => false, "error" => "Error en el servidor al iniciar sesión: " . $e->getMessage()]);
}

$conexion->close();
?>