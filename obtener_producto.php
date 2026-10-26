<?php
header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';
include 'stock_helpers.php';

asegurar_stock_inicial($conexion);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $t_stock = nombre_tabla_stock($conexion);
    $tiene_tabla_stock = tabla_existe($conexion, $t_stock);
    try {
        if ($tiene_tabla_stock) {
            $stmt = $conexion->prepare(
                "SELECT p.*, (SELECT COALESCE(SUM(s.cantidad), 0) FROM `$t_stock` s WHERE s.id_producto = p.id) AS stock_total
                 FROM productos p WHERE p.id = ?"
            );
        } else {
            $stmt = $conexion->prepare("SELECT p.*, COALESCE(p.stock, 0) AS stock_total FROM productos p WHERE p.id = ?");
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $prod = $resultado->fetch_assoc();
            $prod['imagen'] = resolver_imagen_producto($prod['imagen'] ?? '');
            $prod['stock_sedes'] = [];
            if ($tiene_tabla_stock) {
                $sedes = listar_sedes($conexion);
                $mapa_sedes = [];
                foreach ($sedes as $s) $mapa_sedes[$s['id']] = $s['nombre'];
                $stmt_s = $conexion->prepare("SELECT id_sede, cantidad FROM `$t_stock` WHERE id_producto = ? ORDER BY id_sede ASC");
                $stmt_s->bind_param("i", $id);
                $stmt_s->execute();
                $res_s = $stmt_s->get_result();
                while ($fila_s = $res_s->fetch_assoc()) {
                    $id_s = (int)$fila_s['id_sede'];
                    $prod['stock_sedes'][] = [
                        'id_sede' => $id_s,
                        'nombre' => $mapa_sedes[$id_s] ?? ("Sede " . $id_s),
                        'cantidad' => (int)$fila_s['cantidad']
                    ];
                }
                $stmt_s->close();
            }
            echo json_encode($prod);
        } else {
            echo json_encode(["error" => "Producto no encontrado"]);
        }
        $stmt->close();
    } catch (Throwable $e) {
        echo json_encode(["error" => "Error al consultar el producto"]);
    }
} else {
    echo json_encode(["error" => "ID inválido"]);
}

$conexion->close();
?>
