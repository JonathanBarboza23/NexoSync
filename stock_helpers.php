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
    if (tabla_existe($conexion, 'STOCK_SEDE')) return 'STOCK_SEDE';
    return 'stock_sede';
}

function nombre_tabla_sedes($conexion) {
    if (tabla_existe($conexion, 'sedes')) return 'sedes';
    if (tabla_existe($conexion, 'SEDES')) return 'SEDES';
    return 'sedes';
}

function nombre_tabla_estado($conexion) {
    if (tabla_existe($conexion, 'estado')) return 'estado';
    if (tabla_existe($conexion, 'estados')) return 'estados';
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
    if (tiene_columna($conexion, $t_sedes, 'id')) return 'id';
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
    if (empty($ids)) {
        $t_stock = nombre_tabla_stock($conexion);
        if (tabla_existe($conexion, $t_stock)) {
            try {
                $res = $conexion->query("SELECT DISTINCT id_sede FROM `$t_stock` ORDER BY id_sede");
                if ($res) {
                    while ($f = $res->fetch_assoc()) $ids[] = (int)$f['id_sede'];
                }
            } catch (Throwable $e) {}
        }
    }
    if (empty($ids)) {
        $ids = [1, 2, 3];
    }
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
    $nombres_default = [
        1 => "Sucursal Central",
        2 => "Sucursal Norte",
        3 => "Sucursal Sur"
    ];
    foreach (ids_sedes($conexion) as $id) {
        $sedes[] = ["id" => $id, "nombre" => $nombres_default[$id] ?? ("Sede " . $id)];
    }
    return $sedes;
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
        if (tiene_columna($conexion, $t_stock, 'stock_minimo_alerta')) {
            $stmt = $conexion->prepare("INSERT INTO `$t_stock` (id_sede, id_producto, cantidad, stock_minimo_alerta) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $id_sede, $id_producto, $cantidad, $stock_minimo);
        } else {
            $stmt = $conexion->prepare("INSERT INTO `$t_stock` (id_sede, id_producto, cantidad) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $id_sede, $id_producto, $cantidad);
        }
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
            $tiene_min = tiene_columna($conexion, $t_stock, 'stock_minimo_alerta');
            $res_p = $conexion->query("SELECT id FROM productos");
            if ($res_p) {
                while ($p = $res_p->fetch_assoc()) {
                    $id_p = (int)$p['id'];
                    foreach ($sedes as $id_s) {
                        if ($tiene_min) {
                            $conexion->query("INSERT IGNORE INTO `$t_stock` (id_sede, id_producto, cantidad, stock_minimo_alerta) VALUES ($id_s, $id_p, 10, 5)");
                        } else {
                            $conexion->query("INSERT IGNORE INTO `$t_stock` (id_sede, id_producto, cantidad) VALUES ($id_s, $id_p, 10)");
                        }
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
    $pk = tiene_columna($conexion, $t_est, 'id_estado') ? 'id_estado' : 'id';
    $col_nom = tiene_columna($conexion, $t_est, 'nombre_estado') ? 'nombre_estado' : 'nombre';
    try {
        $like = "%" . $conexion->real_escape_string($busqueda) . "%";
        $res = $conexion->query("SELECT `$pk` AS id FROM `$t_est` WHERE LOWER(`$col_nom`) LIKE LOWER('$like') LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return (int)$res->fetch_assoc()['id'];
        }
    } catch (Throwable $e) {}
    return $default_id;
}

function obtener_o_crear_cliente($conexion, $nombre_completo, $dni, $email) {
    if (!tabla_existe($conexion, 'clientes')) return 1;
    $email = trim($email);
    $dni = trim($dni);
    $nombre_completo = trim($nombre_completo);

    try {
        if ($dni !== '') {
            $stmt = $conexion->prepare("SELECT id_cliente FROM clientes WHERE email = ? OR dni_cuit = ? LIMIT 1");
            $stmt->bind_param("ss", $email, $dni);
        } else {
            $stmt = $conexion->prepare("SELECT id_cliente FROM clientes WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $id_cli = (int)$res->fetch_assoc()['id_cliente'];
            $stmt->close();
            return $id_cli;
        }
        $stmt->close();

        $partes = preg_split('/\s+/', $nombre_completo, 2);
        $nombre = $partes[0] ?? 'Cliente';
        $apellido = isset($partes[1]) && trim($partes[1]) !== '' ? trim($partes[1]) : '-';
        $dni_val = $dni !== '' ? $dni : ('SD-' . strtoupper(substr(uniqid(), -7)));

        $stmt_ins = $conexion->prepare("INSERT INTO clientes (dni_cuit, nombre, apellido, email) VALUES (?, ?, ?, ?)");
        $stmt_ins->bind_param("ssss", $dni_val, $nombre, $apellido, $email);
        $stmt_ins->execute();
        $nuevo_id = (int)$conexion->insert_id;
        $stmt_ins->close();
        return $nuevo_id > 0 ? $nuevo_id : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

function resolver_imagen_producto($ruta) {
    $ruta = trim($ruta ?? '');
    if ($ruta === '') return 'img/productos/placeholder.jpg';
    if (file_exists(__DIR__ . '/' . $ruta)) return $ruta;
    $base = basename($ruta);
    if (file_exists(__DIR__ . '/img/productos/' . $base)) return 'img/productos/' . $base;
    if (file_exists(__DIR__ . '/img/' . $base)) return 'img/' . $base;
    return $ruta;
}
?>
