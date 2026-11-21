<?php
require_once __DIR__ . '/_layout.php';
require_staff();
$pdo = db();
$pid = (int)($_GET['producto'] ?? $_POST['producto'] ?? 0);
$q = $pdo->prepare("SELECT * FROM productos WHERE id = ?"); $q->execute([$pid]);
$prod = $q->fetch();
if (!$prod) die('Producto no encontrado.');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    if ($a === 'grupo' && trim($_POST['nombre'] ?? '') !== '') {
        $pdo->prepare("INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden) VALUES (?,?,?,?,?)")
            ->execute([$pid, trim($_POST['nombre']), $_POST['tipo'] === 'multiple' ? 'multiple' : 'unica', isset($_POST['obligatorio']) ? 1 : 0, (int)($_POST['orden'] ?? 0)]);
        flash('Grupo creado. Ahora agrega sus opciones.');
    } elseif ($a === 'grupo_del') { $pdo->prepare("DELETE FROM grupos_opciones WHERE id = ? AND producto_id = ?")->execute([(int)$_POST['id'], $pid]); flash('Grupo eliminado.'); }
    elseif ($a === 'opcion' && trim($_POST['nombre'] ?? '') !== '') {
        $pdo->prepare("INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,?,? FROM grupos_opciones WHERE id = ? AND producto_id = ?")
            ->execute([trim($_POST['nombre']), max(0, (int)$_POST['extra']), (int)$_POST['grupo_id'], $pid]);
    } elseif ($a === 'opcion_toggle') $pdo->prepare("UPDATE opciones o JOIN grupos_opciones g ON g.id = o.grupo_id SET o.activa = 1 - o.activa WHERE o.id = ? AND g.producto_id = ?")->execute([(int)$_POST['id'], $pid]);
    elseif ($a === 'opcion_del') $pdo->prepare("DELETE o FROM opciones o JOIN grupos_opciones g ON g.id = o.grupo_id WHERE o.id = ? AND g.producto_id = ?")->execute([(int)$_POST['id'], $pid]);
    header('Location: opciones.php?producto=' . $pid); exit;
}
$grupos = $pdo->prepare("SELECT * FROM grupos_opciones WHERE producto_id = ? ORDER BY orden, id"); $grupos->execute([$pid]);
$grupos = $grupos->fetchAll();
$ops = [];
foreach ($pdo->query("SELECT o.* FROM opciones o JOIN grupos_opciones g ON g.id = o.grupo_id WHERE g.producto_id = " . $pid . " ORDER BY o.id")->fetchAll() as $o) $ops[$o['grupo_id']][] = $o;
$msg = flash();
adm_head('Opciones', 'productos'); ?>
<?php if ($msg): ?><p class="alert ok"><?= e($msg) ?></p><?php endif; ?>
<a class="back" href="productos.php">← Volver a productos</a>
<h1 style="margin:.4rem 0 .2rem">Opciones de «<?= e($prod['nombre']) ?>»</h1>
<p class="muted">Ejemplos: <b>Tamaño</b> (una sola, obligatoria: Simple / Doble +10.000), <b>Extras</b> (varias: Huevo +3.000), <b>Quitar ingredientes</b> (varias, sin costo: Sin cebolla).</p>

<?php foreach ($grupos as $g): ?>
<section class="card">
  <div class="line" style="border:0">
    <span class="nm"><b><?= e($g['nombre']) ?></b> · <?= $g['tipo'] === 'unica' ? 'elige una' : 'elige varias' ?><?= $g['obligatorio'] ? ' · obligatorio' : ' · opcional' ?></span>
    <form method="post" class="inline" onsubmit="return confirm('¿Eliminar el grupo y sus opciones?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="producto" value="<?= $pid ?>">
      <input type="hidden" name="accion" value="grupo_del"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn btn-small btn-danger">Eliminar grupo</button></form>
  </div>
  <?php foreach ($ops[$g['id']] ?? [] as $o): ?>
  <div class="line" style="<?= $o['activa'] ? '' : 'opacity:.5' ?>"><span class="nm"><?= e($o['nombre']) ?></span><span><?= $o['extra'] > 0 ? '+ ' . money($o['extra']) : 'sin costo' ?></span>
    <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="producto" value="<?= $pid ?>"><input type="hidden" name="accion" value="opcion_toggle"><input type="hidden" name="id" value="<?= $o['id'] ?>">
      <button class="btn btn-small"><?= $o['activa'] ? 'Agotado' : 'Activar' ?></button></form>
    <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="producto" value="<?= $pid ?>"><input type="hidden" name="accion" value="opcion_del"><input type="hidden" name="id" value="<?= $o['id'] ?>">
      <button class="btn btn-small btn-danger" aria-label="Eliminar <?= e($o['nombre']) ?>">×</button></form></div>
  <?php endforeach; ?>
  <form method="post" class="row"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="producto" value="<?= $pid ?>"><input type="hidden" name="accion" value="opcion"><input type="hidden" name="grupo_id" value="<?= $g['id'] ?>">
    <input name="nombre" placeholder="Nueva opción" required style="flex:2"><input type="number" name="extra" min="0" value="0" placeholder="Costo extra" style="flex:1"><button class="btn btn-primary">Agregar</button></form>
</section>
<?php endforeach; ?>

<form method="post" class="card"><h2>Nuevo grupo de opciones</h2>
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="producto" value="<?= $pid ?>"><input type="hidden" name="accion" value="grupo">
  <div class="two"><label>Nombre del grupo<input name="nombre" required placeholder="Tamaño, Extras, Quitar ingredientes…"></label>
    <label>El cliente puede elegir<select name="tipo"><option value="unica">Una sola opción</option><option value="multiple">Varias opciones</option></select></label></div>
  <label><input type="checkbox" name="obligatorio"> Es obligatorio elegir</label>
  <label>Orden<input type="number" name="orden" value="0" style="width:100px"></label>
  <button class="btn btn-primary" style="margin-top:1rem">Crear grupo</button>
</form>
<?php adm_foot();
