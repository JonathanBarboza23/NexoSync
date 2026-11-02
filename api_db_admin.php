<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    include_once 'conexion.php';
    include_once 'stock_helpers.php';
} catch (Throwable $e) {
    echo json_encode(["exito" => false, "error" => "Error al conectar con la base de datos: " . $e->getMessage()]);
    exit;
}

$accion = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($accion === 'login') {
    $rawInput = file_get_contents("php://input");
    $datos = json_decode($rawInput, true) ?? $_POST;
    $usuario = trim($datos['usuario'] ?? '');
    $password = trim($datos['password'] ?? '');

    if (($usuario === 'admin' && $password === 'admin') || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin')) {
        $_SESSION['db_admin_auth'] = true;
        $_SESSION['rol'] = 'admin';
        $_SESSION['usuario_id'] = 1;
        $_SESSION['nombre'] = 'Administrador';
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
    try {
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
    } catch (Throwable $e) {
        echo json_encode(["exito" => false, "error" => "Error al listar tablas: " . $e->getMessage()]);
    }
    exit;
}

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? ($_POST['tabla'] ?? ''));

if ($tabla === '' || !tabla_existe($conexion, $tabla)) {
    echo json_encode(["exito" => false, "error" => "Tabla inválida o no encontrada."]);
    exit;
}

function obtener_info_columnas($conexion, $tabla) {
    $cols = [];
    $pks = [];
    $res = $conexion->query("SHOW COLUMNS FROM `$tabla`");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $cols[] = $col;
            if ($col['Key'] === 'PRI') {
                $pks[] = $col['Field'];
            }
        }
    }
    if (empty($pks) && count($cols) > 0) {
        $pks[] = $cols[0]['Field'];
    }
    return [$cols, $pks];
}

if ($accion === 'estructura') {
    try {
        list($cols, $pks) = obtener_info_columnas($conexion, $tabla);
        echo json_encode(["exito" => true, "columnas" => $cols, "pks" => $pks, "pk" => $pks[0] ?? '']);
    } catch (Throwable $e) {
        echo json_encode(["exito" => false, "error" => $e->getMessage()]);
    }
    exit;
}

if ($accion === 'listar_filas') {
    try {
        list($cols, $pks) = obtener_info_columnas($conexion, $tabla);
        $pk_orden = !empty($pks) ? $pks[0] : $cols[0]['Field'];
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

        $sql = "SELECT * FROM `$tabla` $where ORDER BY `$pk_orden` DESC LIMIT $limite";
        $res = $conexion->query($sql);
        $filas = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $filas[] = $r;
            }
        }

        echo json_encode([
            "exito" => true,
            "tabla" => $tabla,
            "columnas" => $cols,
            "pks" => $pks,
            "pk" => $pk_orden,
            "filas" => $filas
        ]);
    } catch (Throwable $e) {
        echo json_encode(["exito" => false, "error" => "Error al consultar filas: " . $e->getMessage()]);
    }
    exit;
}

if ($accion === 'crear_registro') {
    try {
        $rawInput = file_get_contents("php://input");
        $datos = json_decode($rawInput, true) ?? $_POST;
        list($cols, $pks) = obtener_info_columnas($conexion, $tabla);

        $campos_insert = [];
        $valores_insert = [];

        foreach ($cols as $c) {
            $campo = $c['Field'];
            if ($c['Extra'] === 'auto_increment' && (empty($datos[$campo]) || $datos[$campo] === '')) {
                continue;
            }
            if (array_key_exists($campo, $datos)) {
                $val = $datos[$campo];
                if ($campo === 'password' && $tabla === 'usuarios' && strlen($val) < 50 && $val !== '') {
                    $val = password_hash($val, PASSWORD_BCRYPT);
                }
                $campos_insert[] = "`$campo`";
                if ($val === null || $val === '' && $c['Null'] === 'YES') {
                    $valores_insert[] = "NULL";
                } else {
                    $valores_insert[] = "'" . $conexion->real_escape_string((string)$val) . "'";
                }
            }
        }

        if (empty($campos_insert)) {
            echo json_encode(["exito" => false, "error" => "No se enviaron campos válidos para insertar."]);
            exit;
        }

        $sql = "INSERT INTO `$tabla` (" . implode(', ', $campos_insert) . ") VALUES (" . implode(', ', $valores_insert) . ")";
        $ok = $conexion->query($sql);
        if ($ok) {
            $nuevo_id = $conexion->insert_id;
            echo json_encode(["exito" => true, "mensaje" => "Registro agregado correctamente.", "insert_id" => $nuevo_id]);
        } else {
            echo json_encode(["exito" => false, "error" => "Error al insertar en la base de datos: " . $conexion->error]);
        }
    } catch (Throwable $e) {
        echo json_encode(["exito" => false, "error" => "Excepción al guardar registro: " . $e->getMessage()]);
    }
    exit;
}

if ($accion === 'actualizar_registro') {
    try {
        $rawInput = file_get_contents("php://input");
        $datos = json_decode($rawInput, true) ?? $_POST;
        list($cols, $pks) = obtener_info_columnas($conexion, $tabla);

        $where_pks = [];
        if (!empty($datos['_pks']) && is_array($datos['_pks'])) {
            foreach ($datos['_pks'] as $pk_field => $pk_v) {
                $where_pks[] = "`$pk_field` = '" . $conexion->real_escape_string((string)$pk_v) . "'";
            }
        } elseif (isset($datos['_pk_val']) && !empty($pks)) {
            $pk_main = $pks[0];
            $where_pks[] = "`$pk_main` = '" . $conexion->real_escape_string((string)$datos['_pk_val']) . "'";
        } else {
            foreach ($pks as $pk_field) {
                if (isset($datos[$pk_field])) {
                    $where_pks[] = "`$pk_field` = '" . $conexion->real_escape_string((string)$datos[$pk_field]) . "'";
                }
            }
        }

        if (empty($where_pks)) {
            echo json_encode(["exito" => false, "error" => "No se pudo identificar la clave primaria del registro a editar."]);
            exit;
        }

        $sets = [];
        foreach ($cols as $c) {
            $campo = $c['Field'];

            if (array_key_exists($campo, $datos)) {
                $val = $datos[$campo];
                if ($campo === 'password' && $tabla === 'usuarios' && strlen($val) < 50 && $val !== '') {
                    $val = password_hash($val, PASSWORD_BCRYPT);
                }
                if ($val === null || ($val === '' && $c['Null'] === 'YES')) {
                    $sets[] = "`$campo` = NULL";
                } else {
                    $sets[] = "`$campo` = '" . $conexion->real_escape_string((string)$val) . "'";
                }
            }
        }

        if (empty($sets)) {
            echo json_encode(["exito" => false, "error" => "No hay campos con modificaciones para guardar."]);
            exit;
        }

        $sql = "UPDATE `$tabla` SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $where_pks);
        $ok = $conexion->query($sql);

        if ($ok) {
            echo json_encode(["exito" => true, "mensaje" => "Registro modificado con éxito en `$tabla`."]);
        } else {
            echo json_encode(["exito" => false, "error" => "Error al actualizar registro: " . $conexion->error]);
        }
    } catch (Throwable $e) {
        echo json_encode(["exito" => false, "error" => "Excepción al actualizar: " . $e->getMessage()]);
    }
    exit;
}

if ($accion === 'eliminar_registro') {
    try {
        $rawInput = file_get_contents("php://input");
        $datos = json_decode($rawInput, true) ?? $_POST;
        list($cols, $pks) = obtener_info_columnas($conexion, $tabla);

        $where_pks = [];
        if (!empty($datos['_pks']) && is_array($datos['_pks'])) {
            foreach ($datos['_pks'] as $pk_field => $pk_v) {
                $where_pks[] = "`$pk_field` = '" . $conexion->real_escape_string((string)$pk_v) . "'";
            }
        } elseif (isset($datos['_pk_val']) && !empty($pks)) {
            $pk_main = $pks[0];
            $where_pks[] = "`$pk_main` = '" . $conexion->real_escape_string((string)$datos['_pk_val']) . "'";
        }

        if (empty($where_pks)) {
            echo json_encode(["exito" => false, "error" => "Falta la clave primaria para identificar el registro a eliminar."]);
            exit;
        }

        $sql = "DELETE FROM `$tabla` WHERE " . implode(' AND ', $where_pks);
        $ok = $conexion->query($sql);

        if ($ok) {
            echo json_encode(["exito" => true, "mensaje" => "Registro eliminado correctamente de `$tabla`."]);
        } else {
            echo json_encode(["exito" => false, "error" => "Error al eliminar registro: " . $conexion->error]);
        }
    } catch (Throwable $e) {
        echo json_encode(["exito" => false, "error" => "Excepción al eliminar: " . $e->getMessage()]);
    }
    exit;
}

echo json_encode(["exito" => false, "error" => "Acción desconocida solicitada."]);
$conexion->close();
?>
