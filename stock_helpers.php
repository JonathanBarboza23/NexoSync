<?php

function tabla_existe($conexion, $tabla) {
    try {
        $res = $conexion->query("SHOW TABLES LIKE '" . $conexion->real_escape_string($tabla) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function nombre_tabla_stock($conexion) {
    if (tabla_existe($conexion, 'stock_sede')) return 'stock_sede';
    return 'stock_sede';
}

function nombre_tabla_sedes($conexion) {
    if (tabla_existe($conexion, 'sedes')) return 'sedes';
    return 'sedes';
}

function nombre_tabla_estado($conexion) {
    if (tabla_existe($conexion, 'estado')) return 'estado';
    return 'estado';
}

function tiene_columna($conexion, $tabla, $columna) {
    try {
        if (!tabla_existe($conexion, $tabla)) return false;
        $res = $conexion->query("SHOW COLUMNS FROM `" . $tabla . "` LIKE '" . $conexion->real_escape_string($columna) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function pk_sedes($conexion) {
    $t_sedes = nombre_tabla_sedes($conexion);
    if (tiene_columna($conexion, $t_sedes, 'id_sede')) return 'id_sede';
    return 'id_sede';
}

function ids_sedes($conexion) {
    $ids = [];
    $t_sedes = nombre_tabla_sedes($conexion);
    if (tabla_existe($conexion, $t_sedes)) {
        $pk = pk_sedes($conexion);
        try {
            $res = $conexion->query("SELECT `$pk` AS id FROM `$t_sedes` ORDER BY `$pk`");
            if ($res) {
                while ($f = $res->fetch_assoc()) $ids[] = (int)$f['id'];
            }
        } catch (Throwable $e) {}
    }
    if (empty($ids)) $ids = [1, 2, 3];
    return $ids;
}

function listar_sedes($conexion) {
    $sedes = [];
    $t_sedes = nombre_tabla_sedes($conexion);
    if (tabla_existe($conexion, $t_sedes)) {
        $pk = pk_sedes($conexion);
        try {
            $res = $conexion->query("SELECT `$pk` AS id, nombre FROM `$t_sedes` ORDER BY `$pk`");
            if ($res && $res->num_rows > 0) {
                while ($f = $res->fetch_assoc()) {
                    $sedes[] = ["id" => (int)$f['id'], "nombre" => $f['nombre']];
                }
                return $sedes;
            }
        } catch (Throwable $e) {}
    }
    return [
        ["id" => 1, "nombre" => "Sucursal Central (CABA)"],
        ["id" => 2, "nombre" => "Sucursal Zona Norte (San Miguel)"],
        ["id" => 3, "nombre" => "Sucursal Zona Sur (Quilmes)"]
    ];
}

function fijar_stock_sede($conexion, $id_producto, $id_sede, $cantidad, $stock_minimo = 5) {
    $t_stock = nombre_tabla_stock($conexion);
    $stmt = $conexion->prepare("SELECT 1 FROM `$t_stock` WHERE id_producto = ? AND id_sede = ?");
    $stmt->bind_param("ii", $id_producto, $id_sede);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($existe) {
        $stmt = $conexion->prepare("UPDATE `$t_stock` SET cantidad = ? WHERE id_producto = ? AND id_sede = ?");
        $stmt->bind_param("iii", $cantidad, $id_producto, $id_sede);
    } else {
        $stmt = $conexion->prepare("INSERT INTO `$t_stock` (id_sede, id_producto, cantidad, stock_minimo_alerta) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $id_sede, $id_producto, $cantidad, $stock_minimo);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function asegurar_stock_inicial($conexion) {
    $t_stock = nombre_tabla_stock($conexion);
    if (!tabla_existe($conexion, $t_stock) || !tabla_existe($conexion, 'productos')) return;
    try {
        $res = $conexion->query("SELECT COUNT(*) AS c FROM `$t_stock`");
        $cnt = $res ? (int)$res->fetch_assoc()['c'] : 0;
        if ($cnt === 0) {
            $sedes = ids_sedes($conexion);
            $res_p = $conexion->query("SELECT id FROM productos");
            if ($res_p) {
                while ($p = $res_p->fetch_assoc()) {
                    $id_p = (int)$p['id'];
                    foreach ($sedes as $id_s) {
                        $conexion->query("INSERT IGNORE INTO `$t_stock` (id_sede, id_producto, cantidad, stock_minimo_alerta) VALUES ($id_s, $id_p, 20, 5)");
                    }
                }
            }
        }
    } catch (Throwable $e) {}
}

function sincronizar_stock_total($conexion, $id_producto) {
    if (!tiene_columna($conexion, 'productos', 'stock')) return;
    $t_stock = nombre_tabla_stock($conexion);
    if (!tabla_existe($conexion, $t_stock)) return;
    $stmt = $conexion->prepare(
        "UPDATE productos SET stock = (SELECT COALESCE(SUM(cantidad), 0) FROM `$t_stock` WHERE id_producto = ?) WHERE id = ?"
    );
    $stmt->bind_param("ii", $id_producto, $id_producto);
    $stmt->execute();
    $stmt->close();
}

function obtener_id_estado_por_nombre($conexion, $busqueda, $default_id) {
    $t_est = nombre_tabla_estado($conexion);
    if (!tabla_existe($conexion, $t_est)) return $default_id;
    try {
        $like = "%" . $conexion->real_escape_string($busqueda) . "%";
        $res = $conexion->query("SELECT id_estado AS id FROM `$t_est` WHERE LOWER(nombre_estado) LIKE LOWER('$like') LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return (int)$res->fetch_assoc()['id'];
        }
    } catch (Throwable $e) {}
    return $default_id;
}

function obtener_o_crear_usuario_cliente($conexion, $nombre, $dni, $email) {
    $email = trim($email);
    $dni = trim($dni);
    $nombre = trim($nombre);

    try {
        if ($dni !== '') {
            $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ? OR dni = ? LIMIT 1");
            $stmt->bind_param("ss", $email, $dni);
        } else {
            $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $id_u = (int)$res->fetch_assoc()['id'];
            $stmt->close();
            return $id_u;
        }
        $stmt->close();

        $passDefault = password_hash("123456", PASSWORD_BCRYPT);
        $stmt_ins = $conexion->prepare("INSERT INTO usuarios (nombre, dni, email, password, rol, carrito) VALUES (?, ?, ?, ?, 'cliente', '[]')");
        $stmt_ins->bind_param("ssss", $nombre, $dni, $email, $passDefault);
        $stmt_ins->execute();
        $nuevo_id = (int)$conexion->insert_id;
        $stmt_ins->close();
        return $nuevo_id > 0 ? $nuevo_id : 1;
    } catch (Throwable $e) {
        return 1;
    }
}
?>