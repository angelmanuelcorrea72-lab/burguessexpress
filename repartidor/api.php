<?php
require_once __DIR__ . '/../includes/functions.php';
$rep = require_courier(true);
$RID = (int)$rep['id'];
$visible = "(p.pago <> 'bancard' OR p.pago_estado = 'pagado')";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!hash_equals($_SESSION['csrf'] ?? '', $in['csrf'] ?? '')) json_out(['error' => 'Sesión expirada. Recarga la página.'], 419);
    $id = (int)($in['id'] ?? 0); $nuevo = $in['estado'] ?? '';
    $q = db()->prepare("SELECT estado FROM pedidos WHERE id = ? AND repartidor_id = ? AND tipo = 'delivery'");   // solo SUS pedidos
    $q->execute([$id, $RID]);
    $actual = $q->fetchColumn();
    if ($actual === false) json_out(['error' => 'Ese pedido no está asignado a ti.'], 403);
    $permitido = ['en_camino' => ['preparando', 'listo'], 'entregado' => ['preparando', 'listo', 'en_camino']];
    if (!isset($permitido[$nuevo])) json_out(['error' => 'Acción no permitida.'], 422);
    if (!in_array($actual, $permitido[$nuevo], true)) json_out(['error' => 'El pedido está en estado «' . ESTADOS[$actual] . '» y no se puede cambiar así.'], 409);
    db()->prepare("UPDATE pedidos SET estado = ?, entregado_en = IF(? = 'entregado', COALESCE(entregado_en, NOW()), entregado_en) WHERE id = ? AND repartidor_id = ?")
        ->execute([$nuevo, $nuevo, $id, $RID]);
    json_out(['ok' => true]);
}

$st = db()->prepare("SELECT p.*, u.nombre AS cliente, u.telefono, z.nombre AS zona, UNIX_TIMESTAMP(p.asignado_en) AS ta
    FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id LEFT JOIN zonas z ON z.id = p.zona_id
    WHERE p.repartidor_id = ? AND p.tipo = 'delivery' AND p.estado IN ('pendiente','preparando','listo','en_camino') AND $visible
    ORDER BY FIELD(p.estado,'en_camino','listo','preparando','pendiente'), p.id");
$st->execute([$RID]);
$orders = $st->fetchAll();
if ($orders) {
    $ids = implode(',', array_map('intval', array_column($orders, 'id')));
    $by = [];
    foreach (db()->query("SELECT * FROM pedido_items WHERE pedido_id IN ($ids) ORDER BY id")->fetchAll() as $i)
        $by[$i['pedido_id']][] = ['n' => $i['nombre'], 'q' => (int)$i['cantidad'], 'o' => $i['opciones']];
    foreach ($orders as &$o) {
        $o['items'] = $by[$o['id']] ?? [];
        $o['estado_txt'] = ESTADOS[$o['estado']];
        $o['wa'] = wa_numero($o['telefono']);
        $o['wa_msg'] = rawurlencode('Hola ' . $o['cliente'] . ', soy ' . $rep['nombre'] . ' de ' . cfg('nombre_local') . '. Llevo tu pedido #' . $o['id'] . ($o['estado'] === 'en_camino' ? ' y ya estoy en camino.' : '.'));
        $o['wa_local'] = rawurlencode('Hola, soy ' . $rep['nombre'] . '. Consulta por el pedido #' . $o['id'] . ': ');
        $o['mapa'] = url_mapa($o);
        $o['tel'] = preg_replace('/[^\d+]/', '', $o['telefono']);
        $o['total_txt'] = money($o['total']);
        $o['hace'] = $o['ta'] ? max(0, (int)floor((time() - $o['ta']) / 60)) : null;
        if ($o['pago'] === 'efectivo') $o['cobro'] = ['t' => 'COBRAR EN EFECTIVO: ' . money($o['total']), 'c' => 'cash'];
        elseif ($o['pago'] === 'tarjeta') $o['cobro'] = ['t' => 'Cobrar con tarjeta al entregar: ' . money($o['total']), 'c' => 'cash'];
        elseif ($o['pago_estado'] === 'pagado') $o['cobro'] = ['t' => 'Ya está pagado: no cobres nada', 'c' => 'paid'];
        else $o['cobro'] = ['t' => 'Transferencia por confirmar: consulta al local antes de entregar', 'c' => 'warn'];
        $o['accion'] = in_array($o['estado'], ['preparando', 'listo']) ? 'salir' : ($o['estado'] === 'en_camino' ? 'entregar' : null);
        unset($o['lat_x']);
    }
    unset($o);
}
$h = db()->prepare("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN pago = 'efectivo' THEN total END), 0) ef, COALESCE(SUM(envio), 0) env
    FROM pedidos WHERE repartidor_id = ? AND estado = 'entregado' AND DATE(entregado_en) = CURDATE()");
$h->execute([$RID]);
$h = $h->fetch();
json_out(['orders' => $orders, 'hoy' => ['entregados' => (int)$h['n'], 'efectivo' => money($h['ef']), 'envios' => money($h['env'])], 'wa_local' => wa_link()]);
