<?php
require_once __DIR__ . '/functions.php';

/** @return array{0:string,1:string,2:string} [clave, desde, hasta] según ?r=7|30|mes|custom */
function rango_fechas(): array {
    $hoy = date('Y-m-d'); $r = $_GET['r'] ?? '7';
    $ok = fn($s) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s ?? '');
    if ($r === '30') { $d = date('Y-m-d', strtotime('-29 days')); $h = $hoy; }
    elseif ($r === 'mes') { $d = date('Y-m-01'); $h = $hoy; }
    elseif ($r === 'hoy') { $d = $h = $hoy; }
    elseif ($r === 'custom' && $ok($_GET['desde'] ?? '') && $ok($_GET['hasta'] ?? '')) { $d = $_GET['desde']; $h = $_GET['hasta']; }
    else { $r = '7'; $d = date('Y-m-d', strtotime('-6 days')); $h = $hoy; }
    if ($d > $h) [$d, $h] = [$h, $d];
    return [$r, $d, $h];
}
/** Botones de período + rango propio. $keep = otros filtros GET a conservar. */
function rango_form(string $r, string $desde, string $hasta, array $keep = []): void {
    $link = fn($k, $t) => '<a class="btn btn-small ' . ($r === $k ? 'btn-primary' : '') . '" href="?' . e(http_build_query($keep + ['r' => $k])) . '">' . $t . '</a>';
    echo '<form class="tabs" method="get">' . $link('hoy', 'Hoy') . $link('7', '7 días') . $link('30', '30 días') . $link('mes', 'Este mes');
    foreach ($keep as $k => $v) echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    echo '<input type="hidden" name="r" value="custom"><input type="date" name="desde" value="' . e($desde) . '" style="width:auto;margin:0" aria-label="Desde">' .
         '<input type="date" name="hasta" value="' . e($hasta) . '" style="width:auto;margin:0" aria-label="Hasta">' .
         '<button class="btn btn-small ' . ($r === 'custom' ? 'btn-primary' : '') . '">Ver rango</button></form>';
}

/** Pedidos con TODOS los datos (cliente, zona, repartidor, productos, tiempos). */
function pedidos_detalle(string $where, array $params, int $limit = 500): array {
    $st = db()->prepare("SELECT p.*, u.nombre AS cliente, u.telefono, u.email, z.nombre AS zona, r.nombre AS rep, r.telefono AS rep_tel,
            TIMESTAMPDIFF(MINUTE, p.creado, p.entregado_en) AS min_total, TIMESTAMPDIFF(MINUTE, p.asignado_en, p.entregado_en) AS min_reparto
        FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id LEFT JOIN zonas z ON z.id = p.zona_id LEFT JOIN repartidores r ON r.id = p.repartidor_id
        WHERE $where ORDER BY p.id DESC LIMIT " . (int)$limit);
    $st->execute($params);
    $rows = $st->fetchAll();
    if ($rows) {
        $ids = implode(',', array_map('intval', array_column($rows, 'id')));
        $by = [];
        foreach (db()->query("SELECT * FROM pedido_items WHERE pedido_id IN ($ids) ORDER BY id")->fetchAll() as $i) $by[$i['pedido_id']][] = $i;
        foreach ($rows as &$p) $p['items'] = $by[$p['id']] ?? [];
        unset($p);
    }
    return $rows;
}
function items_texto(array $items): string {
    return implode(' ; ', array_map(fn($i) => $i['cantidad'] . 'x ' . $i['nombre'] . ($i['opciones'] ? ' [' . $i['opciones'] . ']' : ''), $items));
}

/** Lista desplegable de pedidos con toda la información. */
function render_pedidos(array $rows, bool $admin = true, bool $abierto = false, bool $links = true): void {
    if (!$rows) { echo '<p class="muted">No hay pedidos con estos filtros.</p>'; return; }
    foreach ($rows as $p): $wa = wa_numero($p['telefono']); ?>
<details class="ord" <?= $abierto ? 'open' : '' ?>>
  <summary>
    <b>#<?= (int)$p['id'] ?></b>
    <span class="o-fecha"><?= date('d/m H:i', strtotime($p['creado'])) ?></span>
    <span class="o-cli"><?= e($p['cliente']) ?></span>
    <span class="pill"><?= $p['tipo'] === 'delivery' ? 'Delivery' : 'Retiro' ?></span>
    <span class="o-rep"><?= $p['tipo'] === 'delivery' ? ($p['rep'] ? e($p['rep']) : '<i class="muted">sin repartidor</i>') : '' ?></span>
    <b class="o-total"><?= money($p['total']) ?></b>
    <span class="pill st-<?= $p['estado'] ?>"><?= e(ESTADOS[$p['estado']]) ?></span>
  </summary>
  <div class="ord-grid">
    <div><h4>Cliente</h4>
      <?= e($p['cliente']) ?><br>
      <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= e($p['telefono']) ?></a>
      <?php if ($admin): ?><br><span class="muted"><?= e($p['email']) ?></span><?php endif; ?></div>
    <div><h4>Entrega</h4>
      <?= $p['tipo'] === 'delivery' ? 'Delivery' : 'Retiro en el local' ?><?= $p['zona'] ? ' · ' . e($p['zona']) : '' ?>
      <?php if ($p['direccion']): ?><br><?= e($p['direccion']) ?><?php endif; ?>
      <?php if ($p['lat'] !== null): ?><br><a href="<?= e(url_mapa($p)) ?>" target="_blank" rel="noopener">Ver ubicación en el mapa</a><?php endif; ?>
      <?php if ($p['notas']): ?><br><span class="muted">Nota: <?= e($p['notas']) ?></span><?php endif; ?></div>
    <div><h4>Repartidor</h4>
      <?php if ($p['tipo'] !== 'delivery'): ?><span class="muted">No aplica (retiro)</span>
      <?php elseif (!$p['rep']): ?><span class="muted">Sin asignar</span>
      <?php else: ?><?= e($p['rep']) ?><?php if ($p['rep_tel']): ?><br><a href="https://wa.me/<?= e(wa_numero($p['rep_tel'])) ?>" target="_blank" rel="noopener"><?= e($p['rep_tel']) ?></a><?php endif; ?><?php endif; ?></div>
    <div><h4>Productos</h4>
      <?php foreach ($p['items'] as $i): ?><?= (int)$i['cantidad'] ?> × <?= e($i['nombre']) ?> <span class="muted">(<?= money($i['precio']) ?>)</span><?= $i['opciones'] ? '<br><small class="muted">' . e($i['opciones']) . '</small>' : '' ?><br><?php endforeach; ?></div>
    <div><h4>Importes</h4>
      Subtotal: <?= money($p['subtotal']) ?><br>
      <?php if ($p['envio'] > 0): ?>Delivery: <?= money($p['envio']) ?><br><?php endif; ?>
      <?php if ($p['descuento'] > 0): ?>Cupón <?= e($p['cupon']) ?>: −<?= money($p['descuento']) ?><br><?php endif; ?>
      <b>Total: <?= money($p['total']) ?></b><br>
      Pago: <?= e($p['pago']) ?><?= in_array($p['pago'], ['bancard']) ? ' (' . e($p['pago_estado']) . ')' : '' ?></div>
    <div><h4>Tiempos</h4>
      Pedido: <?= date('d/m/Y H:i', strtotime($p['creado'])) ?><br>
      Estimado: <?= (int)$p['minutos'] ?> min<br>
      <?php if ($p['asignado_en']): ?>Asignado: <?= date('H:i', strtotime($p['asignado_en'])) ?><br><?php endif; ?>
      <?php if ($p['entregado_en']): ?>Entregado: <?= date('H:i', strtotime($p['entregado_en'])) ?><br>
        Total: <?= min_txt($p['min_total']) ?><?= $p['rep'] ? ' · Reparto: ' . min_txt($p['min_reparto']) : '' ?><?php endif; ?></div>
  </div>
  <?php if ($admin && $links): ?><p class="ord-links"><a class="btn btn-small" href="pedido.php?id=<?= (int)$p['id'] ?>">Ver ficha y gestionar</a> <a class="btn btn-small" href="ticket.php?id=<?= (int)$p['id'] ?>" target="_blank">Ticket</a></p><?php endif; ?>
</details>
<?php endforeach; }
