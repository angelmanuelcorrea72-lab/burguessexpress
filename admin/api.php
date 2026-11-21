<?php
require_once __DIR__ . '/../includes/functions.php';
$u = user();
if (!$u || !in_array($u['rol'], ['admin', 'empleado'], true)) json_out(['error' => 'No autorizado'], 401);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!hash_equals($_SESSION['csrf'] ?? '', $in['csrf'] ?? '')) json_out(['error' => 'Sesión expirada'], 419);
    $id = (int)($in['id'] ?? 0);
    $act = $in['action'] ?? '';
    if ($act === 'estado') {
        if (!isset(ESTADOS[$in['estado'] ?? ''])) json_out(['error' => 'Estado inválido'], 422);
        db()->prepare("UPDATE pedidos SET estado = ?, entregado_en = IF(? = 'entregado', COALESCE(entregado_en, NOW()), NULL) WHERE id = ?")->execute([$in['estado'], $in['estado'], $id]);
    } elseif ($act === 'minutos') {
        $m = max(1, min(240, (int)($in['minutos'] ?? 30)));
        db()->prepare("UPDATE pedidos SET minutos = ? WHERE id = ?")->execute([$m, $id]);
    } elseif ($act === 'repartidor') {
        $r = (int)($in['repartidor_id'] ?? 0) ?: null;
        // asignado_en va primero: MySQL evalúa de izquierda a derecha y necesita ver el repartidor anterior
        db()->prepare("UPDATE pedidos SET asignado_en = IF(? IS NULL, NULL, IF(repartidor_id <=> ?, asignado_en, NOW())), repartidor_id = ? WHERE id = ? AND tipo = 'delivery'")->execute([$r, $r, $r, $id]);
    } elseif ($act === 'pagado') {   // confirmar a mano una transferencia/pago en línea
        db()->prepare("UPDATE pedidos SET pago_estado = 'pagado' WHERE id = ? AND pago IN ('transferencia','bancard')")->execute([$id]);
    } else json_out(['error' => 'Acción desconocida'], 422);
    json_out(['ok' => true]);
}

// Los pedidos con pago en línea pendiente o fallido no llegan a cocina hasta que se paguen.
$visible = "(p.pago <> 'bancard' OR p.pago_estado = 'pagado')";
$vista = $_GET['vista'] ?? 'activos';
$where = $vista === 'terminados'
    ? "p.estado IN ('entregado','cancelado') AND DATE(p.creado) = CURDATE()"
    : "p.estado NOT IN ('entregado','cancelado')";
$orders = db()->query("SELECT p.*, u.nombre AS cliente, u.telefono, z.nombre AS zona, r.nombre AS rep_nombre, r.telefono AS rep_tel, UNIX_TIMESTAMP(p.creado) AS t0
    FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id LEFT JOIN zonas z ON z.id = p.zona_id LEFT JOIN repartidores r ON r.id = p.repartidor_id
    WHERE $where AND $visible ORDER BY p.id DESC LIMIT 100")->fetchAll();
if ($orders) {
    $ids = implode(',', array_map('intval', array_column($orders, 'id')));
    $by = [];
    foreach (db()->query("SELECT * FROM pedido_items WHERE pedido_id IN ($ids)")->fetchAll() as $i)
        $by[$i['pedido_id']][] = ['n' => $i['nombre'], 'q' => (int)$i['cantidad'], 'o' => $i['opciones']];
    foreach ($orders as &$o) {
        $o['items'] = $by[$o['id']] ?? []; $o['estado_txt'] = ESTADOS[$o['estado']];
        $o['wa'] = wa_numero($o['telefono']);
        $o['wa_rep'] = '';
        if ($o['rep_tel']) {
            $txt = "Nuevo pedido #{$o['id']} para entregar\nCliente: {$o['cliente']} ({$o['telefono']})\nDirección: " . ($o['direccion'] ?: 'ver mapa') .
                   ($o['lat'] !== null ? "\nMapa: " . url_mapa($o) : '') . "\nTotal: " . money($o['total']) . " (" . $o['pago'] . ")";
            $o['wa_rep'] = 'https://wa.me/' . wa_numero($o['rep_tel']) . '?text=' . rawurlencode($txt);
        }
        $o['wa_msg'] = rawurlencode('Hola ' . $o['cliente'] . ', tu pedido #' . $o['id'] . ' de ' . cfg('nombre_local') . ': ' . ESTADOS[$o['estado']] . '.');
    }
    unset($o);
}
$s = db()->query("SELECT COUNT(*) n, COALESCE(SUM(total),0) v FROM pedidos p WHERE DATE(creado) = CURDATE() AND estado <> 'cancelado' AND $visible")->fetch();
$pend = db()->query("SELECT COUNT(*) FROM pedidos p WHERE estado = 'pendiente' AND $visible")->fetchColumn();
$reps = db()->query("SELECT id, nombre FROM repartidores WHERE activo = 1 ORDER BY nombre")->fetchAll();
json_out(['orders' => $orders, 'reps' => $reps, 'stats' => ['hoy' => (int)$s['n'], 'ventas' => money($s['v']), 'pendientes' => (int)$pend]]);
