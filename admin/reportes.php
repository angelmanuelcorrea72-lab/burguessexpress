<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();
$hoy = date('Y-m-d');
$r = $_GET['r'] ?? '7';
if ($r === '30') { $desde = date('Y-m-d', strtotime('-29 days')); $hasta = $hoy; }
elseif ($r === 'mes') { $desde = date('Y-m-01'); $hasta = $hoy; }
elseif ($r === 'custom' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '')) { $desde = $_GET['desde']; $hasta = $_GET['hasta']; }
else { $r = '7'; $desde = date('Y-m-d', strtotime('-6 days')); $hasta = $hoy; }
if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

$F = "p.estado <> 'cancelado' AND (p.pago <> 'bancard' OR p.pago_estado = 'pagado') AND DATE(p.creado) BETWEEN :d AND :h";
function qa(string $sql, array $a): array { $s = db()->prepare($sql); $s->execute($a); return $s->fetchAll(); }
$a = [':d' => $desde, ':h' => $hasta];
$k = qa("SELECT COUNT(*) n, COALESCE(SUM(total),0) v, COALESCE(SUM(descuento),0) d,
         COALESCE(SUM(tipo='delivery'),0) del, COALESCE(SUM(tipo='retiro'),0) ret FROM pedidos p WHERE $F", $a)[0];
$dias = qa("SELECT DATE(p.creado) f, COUNT(*) n, SUM(p.total) v FROM pedidos p WHERE $F GROUP BY f ORDER BY f", $a);
$top = qa("SELECT i.nombre, SUM(i.cantidad) q, SUM(i.cantidad * i.precio) v FROM pedido_items i JOIN pedidos p ON p.id = i.pedido_id WHERE $F GROUP BY i.nombre ORDER BY q DESC LIMIT 10", $a);
$horas = array_fill(0, 24, 0);
foreach (qa("SELECT HOUR(p.creado) h, COUNT(*) n FROM pedidos p WHERE $F GROUP BY h", $a) as $x) $horas[(int)$x['h']] = (int)$x['n'];
$sem = array_fill(0, 7, 0);
foreach (qa("SELECT DAYOFWEEK(p.creado)-1 d, COUNT(*) n FROM pedidos p WHERE $F GROUP BY d", $a) as $x) $sem[(int)$x['d']] = (int)$x['n'];
$pagos = qa("SELECT p.pago, COUNT(*) n, SUM(p.total) v FROM pedidos p WHERE $F GROUP BY p.pago ORDER BY n DESC", $a);
$maxH = max(1, max($horas)); $maxS = max(1, max($sem)); $maxD = max(1, max(array_map(fn($x) => (float)$x['v'], $dias) ?: [1]));
$maxT = max(1, ...(array_map(fn($x) => (int)$x['q'], $top) ?: [1]));
$pico = array_search(max($horas), $horas);
adm_head('Reportes', 'reportes'); reportes_tabs('resumen'); ?>
<style>
.bars{display:grid;gap:.35rem}.bar{display:grid;grid-template-columns:110px 1fr 90px;gap:.6rem;align-items:center;font-size:.9rem}
.bar i{display:block;height:16px;background:var(--ketchup);border-radius:4px;min-width:2px}.bar.m i{background:var(--mustard)}
.cols{display:flex;align-items:flex-end;gap:3px;height:150px;padding-top:8px}.cols div{flex:1;text-align:center;font-size:.65rem}
.cols i{display:block;background:var(--mustard);border-radius:3px 3px 0 0;min-height:2px}.cols div.pk i{background:var(--ketchup)}
.cols span{display:block;color:var(--muted)}
@media(max-width:560px){.bar{grid-template-columns:90px 1fr 70px}}
</style>
<form class="tabs" method="get">
  <a class="btn btn-small <?= $r === '7' ? 'btn-primary' : '' ?>" href="?r=7">Últimos 7 días</a>
  <a class="btn btn-small <?= $r === '30' ? 'btn-primary' : '' ?>" href="?r=30">Últimos 30 días</a>
  <a class="btn btn-small <?= $r === 'mes' ? 'btn-primary' : '' ?>" href="?r=mes">Este mes</a>
  <input type="hidden" name="r" value="custom">
  <input type="date" name="desde" value="<?= e($desde) ?>" style="width:auto;margin:0" aria-label="Desde"><input type="date" name="hasta" value="<?= e($hasta) ?>" style="width:auto;margin:0" aria-label="Hasta">
  <button class="btn btn-small <?= $r === 'custom' ? 'btn-primary' : '' ?>">Ver rango</button>
</form>
<p class="muted">Del <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>. No cuenta pedidos cancelados ni pagos en línea sin confirmar.</p>

<div class="stats">
  <div class="stat"><span class="muted">Ventas</span><b><?= money($k['v']) ?></b></div>
  <div class="stat"><span class="muted">Pedidos</span><b><?= (int)$k['n'] ?></b></div>
  <div class="stat"><span class="muted">Ticket promedio</span><b><?= money($k['n'] ? $k['v'] / $k['n'] : 0) ?></b></div>
  <div class="stat"><span class="muted">Delivery / Retiro</span><b><?= (int)$k['del'] ?> / <?= (int)$k['ret'] ?></b></div>
  <div class="stat"><span class="muted">Descuentos dados</span><b><?= money($k['d']) ?></b></div>
  <div class="stat"><span class="muted">Hora pico</span><b><?= max($horas) > 0 ? sprintf('%02d:00', $pico) : '—' ?></b></div>
</div>

<section class="card"><h2>Ventas por día</h2>
  <?php if (!$dias): ?><p class="muted">Sin ventas en este período.</p><?php endif; ?>
  <div class="bars"><?php foreach ($dias as $d): ?>
    <div class="bar"><span><?= substr(DIAS[(int)date('w', strtotime($d['f']))], 0, 3) . date(' d/m', strtotime($d['f'])) ?></span><i style="width:<?= round($d['v'] / $maxD * 100) ?>%"></i><b><?= money($d['v']) ?></b></div>
  <?php endforeach; ?></div>
</section>

<section class="card"><h2>Lo más vendido</h2>
  <?php if (!$top): ?><p class="muted">Sin datos.</p><?php endif; ?>
  <div class="bars"><?php foreach ($top as $t): ?>
    <div class="bar m"><span title="<?= e($t['nombre']) ?>" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['nombre']) ?></span><i style="width:<?= round($t['q'] / $maxT * 100) ?>%"></i><b><?= (int)$t['q'] ?> u. · <?= money($t['v']) ?></b></div>
  <?php endforeach; ?></div>
</section>

<section class="card"><h2>Horas pico (pedidos por hora)</h2>
  <div class="cols"><?php foreach ($horas as $h => $n): ?>
    <div class="<?= $n === max($horas) && $n > 0 ? 'pk' : '' ?>" title="<?= $h ?>:00 — <?= $n ?> pedidos"><i style="height:<?= round($n / $maxH * 120) ?>px"></i><span><?= $h ?></span></div>
  <?php endforeach; ?></div>
</section>

<section class="card"><h2>Días de la semana</h2>
  <div class="bars"><?php foreach ($sem as $d => $n): ?>
    <div class="bar m"><span><?= DIAS[$d] ?></span><i style="width:<?= round($n / $maxS * 100) ?>%"></i><b><?= $n ?> pedidos</b></div>
  <?php endforeach; ?></div>
</section>

<section class="card"><h2>Formas de pago</h2>
  <?php foreach ($pagos as $x): ?><div class="line"><span class="nm"><?= e(ucfirst($x['pago'])) ?></span><span><?= (int)$x['n'] ?> pedidos</span><b><?= money($x['v']) ?></b></div><?php endforeach; ?>
  <?php if (!$pagos): ?><p class="muted">Sin datos.</p><?php endif; ?>
</section>
<?php adm_foot();
