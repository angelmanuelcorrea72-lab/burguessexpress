<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/reportes_lib.php';
$rep = require_courier();
[$r, $desde, $hasta] = rango_fechas();
$RID = (int)$rep['id'];
$st = db()->prepare("SELECT COUNT(*) AS asignados,
    COALESCE(SUM(estado = 'entregado'), 0) AS entregados, COALESCE(SUM(estado = 'cancelado'), 0) AS cancelados,
    COALESCE(SUM(estado IN ('pendiente','preparando','listo','en_camino')), 0) AS en_curso,
    COALESCE(SUM(CASE WHEN estado = 'entregado' AND pago = 'efectivo' THEN total END), 0) AS efectivo,
    COALESCE(SUM(CASE WHEN estado = 'entregado' THEN envio END), 0) AS envios,
    AVG(CASE WHEN estado = 'entregado' THEN TIMESTAMPDIFF(MINUTE, asignado_en, entregado_en) END) AS prom
    FROM pedidos WHERE repartidor_id = :r AND DATE(creado) BETWEEN :d AND :h");
$st->execute([':r' => $RID, ':d' => $desde, ':h' => $hasta]);
$k = $st->fetch();
$rows = pedidos_detalle("p.repartidor_id = :r AND DATE(p.creado) BETWEEN :d AND :h", [':r' => $RID, ':d' => $desde, ':h' => $hasta], 300);
rep_head('Mi historial', 'historial', $rep);
rango_form($r, $desde, $hasta); ?>
<p class="muted">Del <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?></p>
<div class="stats">
  <div class="stat"><span class="muted">Pedidos entregados</span><b><?= (int)$k['entregados'] ?></b></div>
  <div class="stat"><span class="muted">Asignados</span><b><?= (int)$k['asignados'] ?></b></div>
  <div class="stat"><span class="muted">Tiempo promedio</span><b><?= min_txt($k['prom']) ?></b></div>
  <div class="stat"><span class="muted">Efectivo a rendir</span><b><?= money($k['efectivo']) ?></b></div>
  <div class="stat"><span class="muted">Envíos cobrados</span><b><?= money($k['envios']) ?></b></div>
</div>
<h2 style="margin:1.2rem 0 .6rem">Mis pedidos</h2>
<?php render_pedidos($rows, false); ?>
<?php rep_foot();
