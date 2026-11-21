<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/reportes_lib.php';
require_admin();
[$r, $desde, $hasta] = rango_fechas();
$st = db()->prepare("SELECT r.id, r.nombre, r.telefono, r.activo,
    COUNT(p.id) AS asignados,
    COALESCE(SUM(p.estado = 'entregado'), 0) AS entregados,
    COALESCE(SUM(p.estado IN ('pendiente','preparando','listo','en_camino')), 0) AS en_curso,
    COALESCE(SUM(p.estado = 'cancelado'), 0) AS cancelados,
    COALESCE(SUM(CASE WHEN p.estado = 'entregado' THEN p.total END), 0) AS ventas,
    COALESCE(SUM(CASE WHEN p.estado = 'entregado' THEN p.envio END), 0) AS envios,
    COALESCE(SUM(CASE WHEN p.estado = 'entregado' AND p.pago = 'efectivo' THEN p.total END), 0) AS efectivo,
    AVG(CASE WHEN p.estado = 'entregado' THEN TIMESTAMPDIFF(MINUTE, p.asignado_en, p.entregado_en) END) AS min_prom
  FROM repartidores r LEFT JOIN pedidos p ON p.repartidor_id = r.id AND DATE(p.creado) BETWEEN :d AND :h
  GROUP BY r.id ORDER BY entregados DESC, r.nombre");
$st->execute([':d' => $desde, ':h' => $hasta]);
$reps = $st->fetchAll();
$sin = db()->prepare("SELECT COUNT(*) FROM pedidos WHERE tipo = 'delivery' AND repartidor_id IS NULL AND estado NOT IN ('cancelado') AND DATE(creado) BETWEEN ? AND ?");
$sin->execute([$desde, $hasta]);
$sinAsignar = (int)$sin->fetchColumn();
$sel = (int)($_GET['rep'] ?? 0);
adm_head('Repartidores', 'reportes'); reportes_tabs('repartidores');
rango_form($r, $desde, $hasta); ?>
<p class="muted">Del <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>.
  «Efectivo a rendir» = total de los pedidos entregados que se pagaron en efectivo.
  <?php if ($sinAsignar): ?><b>Hay <?= $sinAsignar ?> delivery sin repartidor en este período.</b><?php endif; ?></p>
<div class="tbl-wrap"><table class="tbl">
  <tr><th>Repartidor</th><th>Asignados</th><th>Entregados</th><th>En curso</th><th>Cancelados</th><th>Tiempo prom.</th><th>Envíos cobrados</th><th>Efectivo a rendir</th><th>Ventas entregadas</th></tr>
  <?php foreach ($reps as $x): ?>
  <tr style="<?= $x['activo'] ? '' : 'opacity:.55' ?>">
    <td><a href="?<?= e(http_build_query(['r' => $r, 'desde' => $desde, 'hasta' => $hasta, 'rep' => $x['id']])) ?>#detalle"><b><?= e($x['nombre']) ?></b></a><br><span class="muted"><?= e($x['telefono']) ?></span></td>
    <td><?= (int)$x['asignados'] ?></td><td><b><?= (int)$x['entregados'] ?></b></td><td><?= (int)$x['en_curso'] ?></td><td><?= (int)$x['cancelados'] ?></td>
    <td><?= min_txt($x['min_prom']) ?></td><td><?= money($x['envios']) ?></td><td><?= money($x['efectivo']) ?></td><td><?= money($x['ventas']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$reps): ?><tr><td colspan="9" class="muted">Todavía no hay repartidores. Créalos en Delivery.</td></tr><?php endif; ?>
</table></div>
<?php if ($sel): ?>
  <h2 id="detalle" style="margin:1.4rem 0 .6rem">Pedidos de <?= e(current(array_filter($reps, fn($x) => $x['id'] == $sel))['nombre'] ?? 'repartidor') ?></h2>
  <?php render_pedidos(pedidos_detalle("p.repartidor_id = :rp AND DATE(p.creado) BETWEEN :d AND :h", [':rp' => $sel, ':d' => $desde, ':h' => $hasta], 300), true); ?>
<?php else: ?><p class="muted">Toca el nombre de un repartidor para ver el detalle de sus pedidos.</p><?php endif; ?>
<?php adm_foot();
