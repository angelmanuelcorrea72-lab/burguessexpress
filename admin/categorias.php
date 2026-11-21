<?php
require_once __DIR__ . '/_layout.php';
require_staff();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'crear') {
        $n = trim($_POST['nombre'] ?? '');
        if ($n === '') flash('Escribe el nombre de la categoría.');
        else {
            $orden = ($_POST['orden'] ?? '') === '' ? (int)$pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM categorias")->fetchColumn() : (int)$_POST['orden'];
            $pdo->prepare("INSERT INTO categorias (nombre, orden) VALUES (?, ?)")->execute([mb_substr($n, 0, 60), $orden]);
            flash('✓ Categoría «' . $n . '» creada. Ya puedes cargarle productos.');
        }
    } elseif ($a === 'guardar') {
        $n = trim($_POST['nombre'] ?? '');
        if ($n === '') flash('El nombre no puede quedar vacío.');
        else {
            $pdo->prepare("UPDATE categorias SET nombre = ?, orden = ?, activa = ? WHERE id = ?")->execute([mb_substr($n, 0, 60), (int)$_POST['orden'], isset($_POST['activa']) ? 1 : 0, $id]);
            flash('✓ Categoría «' . $n . '» guardada.');
        }
    } elseif ($a === 'eliminar') {
        $q = $pdo->prepare("SELECT nombre FROM categorias WHERE id = ?"); $q->execute([$id]);
        $nombre = $q->fetchColumn();
        $c = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ?"); $c->execute([$id]);
        $n = (int)$c->fetchColumn();
        $dest = (int)($_POST['destino'] ?? 0);
        if ($nombre === false) flash('Esa categoría ya no existe.');
        elseif ($n > 0 && $dest <= 0) flash('«' . $nombre . '» tiene ' . $n . ' producto(s). Elige a qué categoría moverlos y vuelve a eliminar.');
        elseif ($n > 0 && $dest === $id) flash('Elige una categoría de destino distinta.');
        else {
            $pdo->beginTransaction();
            try {
                if ($n > 0) $pdo->prepare("UPDATE productos SET categoria_id = ? WHERE categoria_id = ?")->execute([$dest, $id]);
                $pdo->prepare("DELETE FROM categorias WHERE id = ?")->execute([$id]);
                $pdo->commit();
                flash('✓ Categoría «' . $nombre . '» eliminada' . ($n > 0 ? ' y sus ' . $n . ' producto(s) se movieron.' : '.'));
            } catch (Throwable $ex) { $pdo->rollBack(); flash('No se pudo eliminar la categoría. Intenta de nuevo.'); }
        }
    }
    header('Location: categorias.php'); exit;
}
$cats = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS n FROM categorias c ORDER BY c.orden, c.id")->fetchAll();
$msg = flash();
adm_head('Categorías', 'categorias'); ?>
<?php if ($msg): ?><p class="alert <?= $msg[0] === '✓' ? 'ok' : '' ?>" role="status"><?= e($msg) ?></p><?php endif; ?>

<form method="post" class="card">
  <h2>Nueva categoría</h2>
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="crear">
  <div class="two">
    <label>Nombre<input name="nombre" required maxlength="60" placeholder="Ej. Postres, Combos, Bebidas"></label>
    <label>Posición en el menú (opcional)<input type="number" name="orden" placeholder="Vacío = al final"></label>
  </div>
  <button class="btn btn-primary" style="margin-top:1rem">Agregar categoría</button>
</form>

<section class="card">
  <h2>Mis categorías</h2>
  <p class="muted">Los clientes solo ven las categorías activas que tengan productos. Menor número = aparece primero.</p>
  <?php if (!$cats): ?><p class="muted">Todavía no hay categorías.</p><?php endif; ?>
  <?php foreach ($cats as $c): ?>
  <div class="cat-box" id="cat<?= $c['id'] ?>">
    <form method="post" class="cat-edit">
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <input name="nombre" value="<?= e($c['nombre']) ?>" required maxlength="60" aria-label="Nombre" style="flex:3;min-width:150px">
      <input type="number" name="orden" value="<?= (int)$c['orden'] ?>" aria-label="Posición" style="flex:1;min-width:70px">
      <label style="margin:0;white-space:nowrap"><input type="checkbox" name="activa" <?= $c['activa'] ? 'checked' : '' ?>> Activa</label>
      <span class="pill"><?= (int)$c['n'] ?> producto<?= $c['n'] == 1 ? '' : 's' ?></span>
      <button class="btn btn-small btn-primary">Guardar</button>
    </form>
    <form method="post" class="cat-del" onsubmit="return confirm('¿Eliminar la categoría «<?= e(addslashes($c['nombre'])) ?>»?')">
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <?php if ($c['n'] > 0): ?>
        <label style="margin:0">Mover sus <?= (int)$c['n'] ?> producto(s) a:
          <select name="destino" required style="margin:0">
            <option value="">Elige categoría…</option>
            <?php foreach ($cats as $o): if ($o['id'] == $c['id']) continue; ?><option value="<?= $o['id'] ?>"><?= e($o['nombre']) ?></option><?php endforeach; ?>
          </select></label>
      <?php endif; ?>
      <button class="btn btn-small btn-danger" <?= $c['n'] > 0 && count($cats) < 2 ? 'disabled title="Crea otra categoría para mover los productos"' : '' ?>>Eliminar</button>
    </form>
  </div>
  <?php endforeach; ?>
</section>
<?php adm_foot();
