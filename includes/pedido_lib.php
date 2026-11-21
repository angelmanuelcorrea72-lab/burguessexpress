<?php
require_once __DIR__ . '/functions.php';
/** Calcula un pedido desde cero con datos de la base (nunca confía en precios del navegador). */
function armar_pedido(array $in, int $uid): array {
    $tipo = $in['tipo'] ?? '';
    if (!in_array($tipo, ['delivery', 'retiro'], true)) return ['error' => 'Elige delivery o retiro.'];
    $lineas = [];
    foreach (($in['items'] ?? []) as $it) {
        $id = (int)($it['id'] ?? 0); $q = max(1, min(50, (int)($it['q'] ?? 0)));
        if ($id) $lineas[] = ['id' => $id, 'q' => $q, 'op' => (array)($it['op'] ?? [])];
    }
    if (!$lineas) return ['error' => 'Tu pedido está vacío.'];
    $ids = array_values(array_unique(array_column($lineas, 'id')));
    $st = db()->prepare("SELECT * FROM productos WHERE disponible = 1 AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
    $st->execute($ids);
    $prods = [];
    foreach ($st->fetchAll() as $p) $prods[$p['id']] = $p;
    $items = []; $sub = 0;
    foreach ($lineas as $l) {
        $p = $prods[$l['id']] ?? null;
        if (!$p) return ['error' => 'Algún producto ya no está disponible. Revisa tu pedido.'];
        $r = calc_opciones((int)$p['id'], $l['op']);
        if (is_string($r)) return ['error' => $p['nombre'] . ': ' . $r];
        $unit = precio_unit($p) + $r[0];
        $sub += $unit * $l['q'];
        $items[] = ['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'precio' => $unit, 'q' => $l['q'], 'opciones' => $r[1]];
    }
    $envio = 0; $zona = null;
    $min = (int)cfg($tipo === 'delivery' ? 'tiempo_delivery' : 'tiempo_retiro', '30');
    if ($tipo === 'delivery') {
        $zs = db()->query("SELECT * FROM zonas WHERE activa = 1")->fetchAll(PDO::FETCH_UNIQUE);
        if ($zs) {
            $zid = (int)($in['zona_id'] ?? 0);
            if (!isset($zs[$zid])) return ['error' => 'Elige tu zona de entrega.'];
            $zona = $zid; $envio = (int)$zs[$zid]['costo']; $min += (int)$zs[$zid]['minutos_extra'];
        } else $envio = (int)cfg('costo_delivery', '0');
    }
    $desc = 0; $cupon = null; $msgC = '';
    $cod = trim($in['cupon'] ?? '');
    if ($cod !== '') {
        $v = validar_cupon($cod, $sub, $tipo, $envio, $uid);
        if (!$v['ok']) return ['error' => $v['msg'], 'cupon_error' => true];
        $desc = $v['descuento']; $cupon = $v['cupon']['codigo']; $msgC = $v['msg'];
    }
    return ['tipo' => $tipo, 'items' => $items, 'subtotal' => $sub, 'envio' => $envio, 'descuento' => $desc,
            'total' => max(0, $sub + $envio - $desc), 'minutos' => $min, 'zona_id' => $zona, 'cupon' => $cupon, 'msg_cupon' => $msgC];
}
