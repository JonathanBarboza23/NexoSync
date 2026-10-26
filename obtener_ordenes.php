<?php
header('Content-Type: application/json; charset=utf-8');
include 'requerir_admin.php';
include 'conexion.php';
include 'stock_helpers.php';

$filtro = $_GET['filtro'] ?? 'historico';
$where = '';
if (tiene_columna($conexion, 'ordenes', 'fecha')) {
    if ($filtro === 'hoy')        $where = "WHERE DATE(o.fecha) = CURDATE()";
    elseif ($filtro === 'semana') $where = "WHERE o.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    elseif ($filtro === 'mes')    $where = "WHERE o.fecha >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}

$pk_ord = tiene_columna($conexion, 'ordenes', 'id_orden') ? 'id_orden' : 'id';
$t_est = nombre_tabla_estado($conexion);
$tiene_tabla_est = tabla_existe($conexion, $t_est);
$pk_est = tiene_columna($conexion, $t_est, 'id_estado') ? 'id_estado' : 'id';
$col_nom_est = tiene_columna($conexion, $t_est, 'nombre_estado') ? 'nombre_estado' : 'nombre';

$tiene_clientes = tabla_existe($conexion, 'clientes') && tiene_columna($conexion, 'ordenes', 'id_cliente');
$t_sedes = nombre_tabla_sedes($conexion);
$pk_sed = pk_sedes($conexion);
$col_sede_ord = tiene_columna($conexion, 'ordenes', 'id_sede_origen') ? 'id_sede_origen' : (tiene_columna($conexion, 'ordenes', 'id_sede') ? 'id_sede' : null);

$select_total = tiene_columna($conexion, 'ordenes', 'total')
    ? "o.total"
    : "(SELECT COALESCE(SUM(d.cantidad * d.precio_unitario), 0) FROM detalle_ordenes d WHERE d.id_orden = o.`$pk_ord`) AS total";

if ($tiene_clientes) {
    $join_cli = "LEFT JOIN clientes c ON o.id_cliente = c.id_cliente";
    $select_cli = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(c.nombre,''), ' ', CASE WHEN c.apellido = '-' THEN '' ELSE COALESCE(c.apellido,'') END)), ''), CONCAT('Cliente #', o.id_cliente)) AS nombre_cliente, COALESCE(c.email, '') AS email_cliente";
} else {
    $join_cli = "";
    $select_cli = "o.nombre_cliente, o.email_cliente";
}

if ($col_sede_ord && tabla_existe($conexion, $t_sedes)) {
    $join_sede = "LEFT JOIN `$t_sedes` s ON o.`$col_sede_ord` = s.`$pk_sed`";
    $col_entrega = tiene_columna($conexion, 'ordenes', 'tipo_entrega') ? 'o.tipo_entrega' : 'o.metodo_envio';
    $select_envio = "CONCAT($col_entrega, CASE WHEN s.nombre IS NOT NULL THEN CONCAT(' (', s.nombre, ')') ELSE '' END) AS metodo_envio";
} else {
    $join_sede = "";
    $select_envio = (tiene_columna($conexion, 'ordenes', 'tipo_entrega') ? "o.tipo_entrega" : "o.metodo_envio") . " AS metodo_envio";
}

if ($tiene_tabla_est) {
    $join_est = "LEFT JOIN `$t_est` e ON o.id_estado = e.`$pk_est`";
    $select_est = "COALESCE(e.`$col_nom_est`, CASE WHEN o.id_estado IN (2, 5) THEN 'completado' WHEN o.id_estado IN (3, 6) THEN 'cancelado' ELSE 'en proceso' END) AS estado_nombre";
} else {
    $join_est = "";
    $select_est = "CASE WHEN o.id_estado IN (2, 5) THEN 'completado' WHEN o.id_estado IN (3, 6) THEN 'cancelado' ELSE 'en proceso' END AS estado_nombre";
}

$sql = "SELECT o.*, o.`$pk_ord` AS id, $select_total, $select_cli, $select_envio, $select_est
        FROM ordenes o
        $join_cli
        $join_sede
        $join_est
        $where
        ORDER BY o.`$pk_ord` DESC";

try {
    $resultado = $conexion->query($sql);
    $ordenes = [];
    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $ordenes[] = $fila;
        }
    }
    echo json_encode(["exito" => true, "ordenes" => $ordenes]);
} catch (Throwable $e) {
    echo json_encode(["exito" => false, "error" => "Error al consultar órdenes: " . $e->getMessage(), "ordenes" => []]);
}
$conexion->close();
?>
