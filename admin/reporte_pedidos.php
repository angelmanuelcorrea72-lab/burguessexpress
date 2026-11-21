<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/reportes_lib.php';
require_admin();
[$r, $desde, $hasta] = rango_fechas();
$f = [
    'estado' => isset(ESTADOS[$_GET['estado'] ?? '']) ? $_GET['estado'] : '',
    'tipo' => in_array($_GET['tipo'] ?? '', ['delivery', 'retiro'], true) ? $_GET['tipo'] : '',
    'pago' => in_array($_GET['pago'] ?? '', ['efectivo', 'transferencia', 'tarjeta', 'bancard'], true) ? $_GET['pago'] : '',
    'rep' => ($_GET['rep'] ?? '') === 'sin' ? 'sin' : (ctype_digit($_GET['rep'] ?? '') ? $_GET['rep'] : ''),
    'q' => trim($_GET['q'] ?? ''),
];
$where = ["DATE(p.creado) BETWEEN :d AND :h"]; $par = [':d' => $desde, ':h' => $hasta];
if ($f['estado']) { $where[] = "p.estado = :es"; $par[':es'] = $f['estado']; }
if ($f['tipo']) { $where[] = "p.tipo = :ti"; $par[':ti'] = $f['tipo']; }
if ($f['pago']) { $where[] = "p.pago = :pa"; $par[':pa'] = $f['pago']; }
if ($f['rep'] === 'sin') $where[] = "p.tipo = 'delivery' AND p.repartidor_id IS NULL";
elseif ($f['rep'] !== '') { $where[] = "p.repartidor_id = :rp"; $par[':rp'] = (int)$f['rep']; }
if ($f['q'] !== '') {
    $where[] = "(p.id = :qid OR u.nombre LIKE :q1 OR u.telefono LIKE :q2 OR u.email LIKE :q3 OR p.direccion LIKE :q4)";
    $like = '%' . $f['q'] . '%';
    $par += [':qid' => (int)ltrim($f['q'], '#'), ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
}
$W = implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = pedidos_detalle($W, $par, 5000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pedidos_' . $desde . '_a_' . $hasta . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Pedido', 'Fecha', 'Hora', 'Cliente', 'Telefono', 'Email', 'Tipo', 'Zona', 'Direccion', 'Latitud', 'Longitud', 'Repartidor', 'Tel repartidor',
                 'Productos', 'Notas', 'Subtotal', 'Envio', 'Descuento', 'Cupon', 'Total', 'Pago', 'Estado del pago', 'Estado', 'Min estimados',
                 'Asignado', 'Entregado', 'Min total', 'Min reparto'], ';');
    foreach ($rows as $p) fputcsv($o, [$p['id'], date('d/m/Y', strtotime($p['creado'])), date('H:i', strtotime($p['creado'])), $p['cliente'], $p['telefono'], $p['email'],
        $p['tipo'], $p['zona'], $p['direccion'], $p['lat'], $p['lng'], $p['rep'], $p['rep_tel'], items_texto($p['items']), $p['notas'],
        $p['subtotal'], $p['envio'], $p['descuento'], $p['cupon'], $p['total'], $p['pago'], $p['pago_estado'], ESTADOS[$p['estado']], $p['minutos'],
        $p['asignado_en'], $p['entregado_en'], $p['min_total'], $p['min_reparto']], ';');
    exit;
}

$rows = pedidos_detalle($W, $par, 500);
$ventas = 0; $validos = 0;
foreach ($rows as $p) if ($p['estado'] !== 'cancelado' && ($p['pago'] !== 'bancard' || $p['pago_estado'] === 'pagado')) { $ventas += $p['total']; $validos++; }
$reps = db()->query("SELECT id, nombre FROM repartidores ORDER BY nombre")->fetchAll();
adm_head('Detalle de pedidos', 'reportes'); reportes_tabs('pedidos');
rango_form($r, $desde, $hasta, array_filter($f, fn($v) => $v !== '')); ?>
<form method="get" class="card filtros">
  <input type="hidden" name="r" value="<?= e($r) ?>"><input type="hidden" name="desde" value="<?= e($desde) ?>"><input type="hidden" name="hasta" value="<?= e($hasta) ?>">
  <label>Buscar<input name="q" value="<?= e($f['q']) ?>" placeholder="N.º de pedido, cliente, teléfono, dirección…"></label>
  <label>Estado<select name="estado"><option value="">Todos</option><?php foreach (ESTADOS as $k => $l): ?><option value="<?= $k ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label>Tipo<select name="tipo"><option value="">Todos</option><option value="delivery" <?= $f['tipo'] === 'delivery' ? 'selected' : '' ?>>Delivery</option><option value="retiro" <?= $f['tipo'] === 'retiro' ? 'selected' : '' ?>>Retiro</option></select></label>
  <label>Repartidor<select name="rep"><option value="">Todos</option><option value="sin" <?= $f['rep'] === 'sin' ? 'selected' : '' ?>>Delivery sin asignar</option>
    <?php foreach ($reps as $x): ?><option value="<?= $x['id'] ?>" <?= $f['rep'] === (string)$x['id'] ? 'selected' : '' ?>><?= e($x['nombre']) ?></option><?php endforeach; ?></select></label>
  <label>Pago<select name="pago"><option value="">Todos</option><?php foreach (['efectivo', 'transferencia', 'tarjeta', 'bancard'] as $x): ?><option value="<?= $x ?>" <?= $f['pago'] === $x ? 'selected' : '' ?>><?= ucfirst($x) ?></option><?php endforeach; ?></select></label>
  <div class="row" style="align-self:end"><button class="btn btn-primary">Filtrar</button>
    <a class="btn" href="?<?= e(http_build_query(array_filter($f, fn($v) => $v !== '') + ['r' => $r, 'desde' => $desde, 'hasta' => $hasta, 'export' => 'csv'])) ?>">Exportar a Excel (CSV)</a></div>
</form>
<p class="muted"><?= count($rows) ?> pedido(s) del <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?> · Ventas (sin cancelados): <b><?= money($ventas) ?></b> en <?= $validos ?> pedido(s).
  <?= count($rows) >= 500 ? 'Se muestran los 500 más recientes; usa filtros o exporta para ver todos.' : '' ?></p>
<?php render_pedidos($rows, true); ?>
<?php adm_foot();
