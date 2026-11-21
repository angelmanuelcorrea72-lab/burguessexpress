<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    if ($a === 'crear') {
        $cod = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['codigo'] ?? ''));
        $tipo = in_array($_POST['tipo'] ?? '', ['porcentaje', 'monto', 'envio_gratis'], true) ? $_POST['tipo'] : 'porcentaje';
        $valor = max(0, (int)($_POST['valor'] ?? 0));
        if ($tipo === 'porcentaje') $valor = min(100, $valor);
        if ($cod === '') flash('Escribe un código (letras y números).');
        elseif ($tipo !== 'envio_gratis' && $valor <= 0) flash('Indica el valor del descuento.');
        else {
            try {
                $pdo->prepare("INSERT INTO cupones (codigo,tipo,valor,minimo,usos_max,uno_por_usuario,vence) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$cod, $tipo, $valor, max(0, (int)($_POST['minimo'] ?? 0)), max(0, (int)($_POST['usos_max'] ?? 0)), isset($_POST['uno']) ? 1 : 0, ($_POST['vence'] ?? '') ?: null]);
                flash('Cupón ' . $cod . ' creado.');
            } catch (PDOException $ex) { flash('Ya existe un cupón con ese código.'); }
        }
    } elseif ($a === 'toggle') $pdo->prepare("UPDATE cupones SET activo = 1 - activo WHERE id = ?")->execute([(int)$_POST['id']]);
    elseif ($a === 'eliminar') { $pdo->prepare("DELETE FROM cupones WHERE id = ?")->execute([(int)$_POST['id']]); flash('Cupón eliminado.'); }
    header('Location: cupones.php'); exit;
}
$rows = $pdo->query("SELECT * FROM cupones ORDER BY activo DESC, id DESC")->fetchAll();
$msg = flash();
adm_head('Cupones', 'cupones'); ?>
<?php if ($msg): ?><p class="alert ok"><?= e($msg) ?></p><?php endif; ?>
<form method="post" class="card">
  <h2>Nuevo cupón</h2>
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="crear">
  <div class="two">
    <label>Código<input name="codigo" required maxlength="30" placeholder="VERANO10" style="text-transform:uppercase"></label>
    <label>Tipo<select name="tipo"><option value="porcentaje">Porcentaje (%)</option><option value="monto">Monto fijo</option><option value="envio_gratis">Delivery gratis</option></select></label>
    <label>Valor (% o monto; ignorado en delivery gratis)<input type="number" name="valor" min="0" value="10"></label>
    <label>Pedido mínimo<input type="number" name="minimo" min="0" value="0"></label>
    <label>Usos máximos (0 = sin límite)<input type="number" name="usos_max" min="0" value="0"></label>
    <label>Vence (opcional)<input type="date" name="vence"></label>
  </div>
  <label><input type="checkbox" name="uno" checked> Una sola vez por cliente</label>
  <button class="btn btn-primary" style="margin-top:1rem">Crear cupón</button>
</form>
<div class="tbl-wrap"><table class="tbl">
  <tr><th>Código</th><th>Descuento</th><th>Mínimo</th><th>Usos</th><th>Vence</th><th></th></tr>
  <?php foreach ($rows as $c): ?>
  <tr style="<?= $c['activo'] ? '' : 'opacity:.55' ?>">
    <td><b><?= e($c['codigo']) ?></b></td>
    <td><?= $c['tipo'] === 'porcentaje' ? (int)$c['valor'] . '%' : ($c['tipo'] === 'monto' ? money($c['valor']) : 'Delivery gratis') ?></td>
    <td><?= money($c['minimo']) ?></td>
    <td><?= (int)$c['usos'] ?><?= $c['usos_max'] ? ' / ' . (int)$c['usos_max'] : '' ?></td>
    <td><?= $c['vence'] ? date('d/m/Y', strtotime($c['vence'])) : '—' ?></td>
    <td>
      <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <button class="btn btn-small"><?= $c['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
      <form method="post" class="inline" onsubmit="return confirm('¿Eliminar el cupón?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <button class="btn btn-small btn-danger">Eliminar</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<p class="muted">Las ofertas por producto (precio rebajado) se cargan en <a href="productos.php">Productos</a> con el campo «Precio en oferta».</p>
<?php adm_foot();
