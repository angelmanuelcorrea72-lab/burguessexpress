<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/reportes_lib.php';
require_staff();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    if ($a === 'repartidor') {
        $rp = (int)($_POST['repartidor_id'] ?? 0) ?: null;
        db()->prepare("UPDATE pedidos SET asignado_en = IF(? IS NULL, NULL, IF(repartidor_id <=> ?, asignado_en, NOW())), repartidor_id = ? WHERE id = ? AND tipo = 'delivery'")->execute([$rp, $rp, $rp, $id]);
        flash('✓ Repartidor actualizado.');
    } elseif ($a === 'estado' && isset(ESTADOS[$_POST['estado'] ?? ''])) {
        db()->prepare("UPDATE pedidos SET estado = ?, entregado_en = IF(? = 'entregado', COALESCE(entregado_en, NOW()), NULL) WHERE id = ?")->execute([$_POST['estado'], $_POST['estado'], $id]);
        flash('✓ Estado actualizado.');
    } elseif ($a === 'minutos') {
        db()->prepare("UPDATE pedidos SET minutos = ? WHERE id = ?")->execute([max(1, min(240, (int)$_POST['minutos'])), $id]);
        flash('✓ Tiempo estimado actualizado.');
    } elseif ($a === 'pagado') {
        db()->prepare("UPDATE pedidos SET pago_estado = 'pagado' WHERE id = ? AND pago IN ('transferencia','bancard')")->execute([$id]);
        flash('✓ Pago marcado como confirmado.');
    }
    header('Location: pedido.php?id=' . $id); exit;
}
$rows = pedidos_detalle("p.id = :id", [':id' => $id], 1);
if (!$rows) { http_response_code(404); die('Pedido no encontrado.'); }
$p = $rows[0];
$reps = db()->query("SELECT id, nombre FROM repartidores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$msg = flash();
adm_head('Pedido #' . $id, 'index'); ?>
<?php if ($msg): ?><p class="alert ok" role="status"><?= e($msg) ?></p><?php endif; ?>
<a class="back" href="index.php">← Volver a pedidos</a>
<h1 style="margin:.4rem 0 1rem">Pedido #<?= $id ?></h1>
<?php render_pedidos($rows, true, true, false); ?>
<section class="card">
  <h2>Gestionar</h2>
  <form method="post" class="row" style="align-items:end">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="estado">
    <label style="flex:2;margin:0">Estado<select name="estado"><?php foreach (ESTADOS as $k => $l): ?><option value="<?= $k ?>" <?= $p['estado'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Cambiar estado</button>
  </form>
  <?php if ($p['tipo'] === 'delivery'): ?>
  <form method="post" class="row" style="align-items:end">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="repartidor">
    <label style="flex:2;margin:0">Repartidor<select name="repartidor_id"><option value="">Sin asignar</option>
      <?php foreach ($reps as $x): ?><option value="<?= $x['id'] ?>" <?= (int)$p['repartidor_id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['nombre']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Asignar</button>
  </form>
  <?php endif; ?>
  <form method="post" class="row" style="align-items:end">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="minutos">
    <label style="flex:2;margin:0">Tiempo estimado (min)<input type="number" name="minutos" min="1" max="240" value="<?= (int)$p['minutos'] ?>"></label>
    <button class="btn">Guardar tiempo</button>
  </form>
  <div class="row"><a class="btn" href="ticket.php?id=<?= $id ?>" target="_blank">Imprimir ticket</a>
    <a class="btn btn-wa" target="_blank" rel="noopener" href="https://wa.me/<?= e(wa_numero($p['telefono'])) ?>?text=<?= rawurlencode('Hola ' . $p['cliente'] . ', tu pedido #' . $id . ' de ' . cfg('nombre_local') . ': ' . ESTADOS[$p['estado']] . '.') ?>">Avisar al cliente</a>
    <?php if ($p['pago'] === 'transferencia' && $p['pago_estado'] !== 'pagado'): ?>
    <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="pagado"><button class="btn">Confirmar transferencia</button></form>
    <?php endif; ?></div>
</section>
<?php adm_foot();
