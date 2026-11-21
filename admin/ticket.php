<?php
require_once __DIR__ . '/../includes/functions.php';
require_staff();
$q = db()->prepare("SELECT p.*, u.nombre AS cliente, u.telefono, z.nombre AS zona, r.nombre AS repartidor
    FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id LEFT JOIN zonas z ON z.id = p.zona_id LEFT JOIN repartidores r ON r.id = p.repartidor_id WHERE p.id = ?");
$q->execute([(int)($_GET['id'] ?? 0)]);
$p = $q->fetch();
if (!$p) { http_response_code(404); die('Pedido no encontrado'); }
$it = db()->prepare("SELECT * FROM pedido_items WHERE pedido_id = ?");
$it->execute([$p['id']]);
$items = $it->fetchAll();
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Ticket #<?= (int)$p['id'] ?></title>
<style>
@page { size: 80mm auto; margin: 3mm; }
* { box-sizing: border-box }
body { font: 13px/1.35 "Courier New", monospace; width: 74mm; margin: 0 auto; color: #000 }
h1 { font-size: 18px; text-align: center; margin: 0 0 2px }
.c { text-align: center } hr { border: 0; border-top: 1px dashed #000; margin: 6px 0 }
.big { font-size: 22px; font-weight: bold } .row { display: flex; justify-content: space-between; gap: 6px }
.it { margin: 4px 0; font-weight: bold } .op { padding-left: 10px; font-size: 12px; font-weight: normal }
.noprint { text-align: center; margin: 12px 0 } @media print { .noprint { display: none } }
</style></head><body>
<h1><?= e(cfg('nombre_local')) ?></h1>
<p class="c" style="margin:0"><?= date('d/m/Y H:i', strtotime($p['creado'])) ?></p>
<p class="c big" style="margin:4px 0">PEDIDO #<?= (int)$p['id'] ?></p>
<p class="c big" style="margin:0;font-size:16px"><?= $p['tipo'] === 'delivery' ? 'DELIVERY' : 'RETIRO EN LOCAL' ?></p>
<hr>
<?php foreach ($items as $i): ?>
  <div class="it"><?= (int)$i['cantidad'] ?> × <?= e($i['nombre']) ?></div>
  <?php if ($i['opciones']): ?><div class="op"><?= e(str_replace(' | ', "\n", $i['opciones'])) ?></div><?php endif; ?>
<?php endforeach; ?>
<?php if ($p['notas']): ?><hr><div><b>NOTA:</b> <?= e($p['notas']) ?></div><?php endif; ?>
<hr>
<div class="row"><span>Subtotal</span><span><?= money($p['subtotal']) ?></span></div>
<?php if ($p['envio'] > 0): ?><div class="row"><span>Delivery</span><span><?= money($p['envio']) ?></span></div><?php endif; ?>
<?php if ($p['descuento'] > 0): ?><div class="row"><span>Cupón <?= e($p['cupon']) ?></span><span>-<?= money($p['descuento']) ?></span></div><?php endif; ?>
<div class="row big"><span>TOTAL</span><span><?= money($p['total']) ?></span></div>
<div>Pago: <?= e($p['pago']) ?><?= $p['pago'] === 'bancard' ? ' (' . $p['pago_estado'] . ')' : '' ?></div>
<hr>
<div><b><?= e($p['cliente']) ?></b> · <?= e($p['telefono']) ?></div>
<?php if ($p['tipo'] === 'delivery'): ?>
  <?php if ($p['zona']): ?><div>Zona: <?= e($p['zona']) ?></div><?php endif; ?>
  <?php if ($p['direccion']): ?><div><?= e($p['direccion']) ?></div><?php endif; ?>
  <?php if ($p['lat']): ?><div>GPS: <?= e($p['lat']) ?>, <?= e($p['lng']) ?></div><?php endif; ?>
  <?php if ($p['repartidor']): ?><div>Repartidor: <?= e($p['repartidor']) ?></div><?php endif; ?>
<?php endif; ?>
<hr><p class="c">¡Gracias por tu compra!</p>
<div class="noprint"><button onclick="window.print()">Imprimir</button></div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, <?= isset($_GET['auto']) ? 300 : 500 ?>); });</script>
</body></html>
