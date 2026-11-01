<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

$accion = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($accion === 'login') {
    $datos = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $usuario = trim($datos['usuario'] ?? '');
    $password = trim($datos['password'] ?? '');

    if (($usuario === 'admin' && $password === 'admin') || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin')) {
        $_SESSION['db_admin_auth'] = true;
        echo json_encode(["exito" => true, "mensaje" => "Acceso total a base de datos concedido."]);
    } else {
        echo json_encode(["exito" => false, "error" => "Credenciales incorrectas. Ingresa con usuario 'admin' y contraseña 'admin'."]);
    }
    exit;
}

if ($accion === 'logout') {
    unset($_SESSION['db_admin_auth']);
    echo json_encode(["exito" => true]);
    exit;
}

if (empty($_SESSION['db_admin_auth']) && (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'admin')) {
    echo json_encode(["exito" => false, "error" => "No autorizado. Inicia sesión con usuario 'admin' y contraseña 'admin'."]);
    exit;
}

if ($accion === 'listar_tablas') {
    $tablas = [];
    $res = $conexion->query("SHOW TABLES");
    if ($res) {
        while ($f = $res->fetch_row()) {
            $tname = $f[0];
            $c_res = $conexion->query("SELECT COUNT(*) FROM `$tname`");
            $total_filas = $c_res ? (int)$c_res->fetch_row()[0] : 0;
            $tablas[] = ["nombre" => $tname, "filas" => $total_filas];
        }
    }
    echo json_encode(["exito" => true, "tablas" => $tablas]);
    exit;
}

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? ($_POST['tabla'] ?? ''));

if ($tabla === '' || !tabla_existe($conexion, $tabla)) {
    echo json_encode(["exito" => false, "error" => "Tabla inválida o no encontrada."]);
    exit;
}

function obtener_info_columnas($conexion, $tabla) {
    $cols = [];
    $pk = null;
    $res = $conexion->query("SHOW COLUMNS FROM `$tabla`");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $cols[] = $col;
            if ($col['Key'] === 'PRI' && $pk === null) {
                $pk = $col['Field'];
            }
        }
    }
    if ($pk === null && count($cols) > 0) {
        $pk = $cols[0]['Field'];
    }
    return [$cols, $pk];
}

if ($accion === 'estructura') {
    list($cols, $pk) = obtener_info_columnas($conexion, $tabla);
    echo json_encode(["exito" => true, "columnas" => $cols, "pk" => $pk]);
    exit;
}

if ($accion === 'listar_filas') {
    list($cols, $pk) = obtener_info_columnas($conexion, $tabla);
    $q = trim($_GET['q'] ?? '');
    $limite = intval($_GET['limit'] ?? 100);
    if ($limite <= 0 || $limite > 500) $limite = 100;

    $where = "";
    if ($q !== '') {
        $conds = [];
        $q_esc = $conexion->real_escape_string($q);
        foreach ($cols as $c) {
            $f = $c['Field'];
            $conds[] = "`$f` LIKE '%$q_esc%'";
        }
        if (!empty($conds)) {
            $where = " WHERE " . implode(' OR ', $conds);
        }
    }

    $sql = "SELECT * FROM `$tabla` $where ORDER BY `$pk` DESC LIMIT $limite";
    $res = $conexion->query($sql);
    $filas = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $filas[] = $r;
        }
    }

    echo json_encode(["exito" => true, "tabla" => $tabla, "columnas" => $cols, "pk" => $pk, "filas" => $filas]);
    exit;
}

if ($accion === 'crear_registro') {
    $datos = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    list($cols, $pk) = obtener_info_columnas($conexion, $tabla);

    $campos_insert = [];
    $valores_insert = [];
    $types = '';
    $params = [];

    foreach ($cols as $c) {
        $campo = $c['Field'];
        if ($c['Extra'] === 'auto_increment' && empty($datos[$campo])) {
            continue;
        }
        if (array_key_exists($campo, $datos)) {
            $val = $datos[$campo];
            if ($campo === 'password' && strlen($val) < 50 && $tabla === 'usuarios') {
                $val = password_hash($val, PASSWORD_BCRYPT);
            }
            $campos_insert[] = "`$campo`";
            $valores_insert[] = "?";
            $types .= 's';
            $params[] = $val;
        }
    }

    if (empty($campos_insert)) {
        echo json_encode(["exito" => false, "error" => "No se enviaron datos válidos para insertar."]);
        exit;
    }

    $sql = "INSERT INTO `$tabla` (" . implode(', ', $campos_insert) . ") VALUES (" . implode(', ', $valores_insert) . ")";
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $ins_id = $conexion->insert_id;
        $stmt->close();
        if ($ok) {
            echo json_encode(["exito" => true, "mensaje" => "Registro creado correctamente.", "insert_id" => $ins_id]);
        } else {
            echo json_encode(["exito" => false, "error" => "Error al insertar en la base de datos."]);
        }
    } else {
        echo json_encode(["exito" => false, "error" => "Error en la consulta: " . $conexion->error]);
    }
    exit;
}

if ($accion === 'actualizar_registro') {
    $datos = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    list($cols, $pk) = obtener_info_columnas($conexion, $tabla);

    $pk_val = $datos['_pk_val'] ?? ($datos[$pk] ?? null);
    if ($pk_val === null) {
        echo json_encode(["exito" => false, "error" => "Falta la clave primaria para identificar el registro a editar."]);
        exit;
    }

    $sets = [];
    $types = '';
    $params = [];

    foreach ($cols as $c) {
        $campo = $c['Field'];
        if ($campo === $pk) continue;
        if (array_key_exists($campo, $datos)) {
            $val = $datos[$campo];
            if ($campo === 'password' && $tabla === 'usuarios' && strlen($val) < 50 && $val !== '') {
                $val = password_hash($val, PASSWORD_BCRYPT);
            }
            $sets[] = "`$campo` = ?";
            $types .= 's';
            $params[] = $val;
        }
    }

    if (empty($sets)) {
        echo json_encode(["exito" => false, "error" => "No hay campos para actualizar."]);
        exit;
    }

    $sql = "UPDATE `$tabla` SET " . implode(', ', $sets) . " WHERE `$pk` = ?";
    $types .= 's';
    $params[] = $pk_val;

    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            echo json_encode(["exito" => true, "mensaje" => "Registro actualizado con éxito."]);
        } else {
            echo json_encode(["exito" => false, "error" => "Error al actualizar registro."]);
        }
    } else {
        echo json_encode(["exito" => false, "error" => "Error SQL: " . $conexion->error]);
    }
    exit;
}

if ($accion === 'eliminar_registro') {
    $datos = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    list($cols, $pk) = obtener_info_columnas($conexion, $tabla);

    $pk_val = $datos['_pk_val'] ?? ($datos[$pk] ?? null);
    if ($pk_val === null) {
        echo json_encode(["exito" => false, "error" => "Falta identificar el registro a eliminar."]);
        exit;
    }

    $stmt = $conexion->prepare("DELETE FROM `$tabla` WHERE `$pk` = ?");
    if ($stmt) {
        $stmt->bind_param("s", $pk_val);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            echo json_encode(["exito" => true, "mensaje" => "Registro eliminado correctamente de `$tabla`."]);
        } else {
            echo json_encode(["exito" => false, "error" => "No se pudo eliminar el registro."]);
        }
    } else {
        echo json_encode(["exito" => false, "error" => "Error SQL: " . $conexion->error]);
    }
    exit;
}

echo json_encode(["exito" => false, "error" => "Acción desconocida."]);
$conexion->close();
?>
