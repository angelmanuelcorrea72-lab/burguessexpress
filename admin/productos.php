<?php
require_once __DIR__ . '/_layout.php';
require_staff();
$pdo = db();

function subir_imagen(string $campo): ?string {
    if (empty($_FILES[$campo]['tmp_name']) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$campo]['size'] > 3 * 1024 * 1024) { flash('La imagen supera 3 MB.'); return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$campo]['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) { flash('Usa una imagen JPG, PNG o WEBP.'); return null; }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($_FILES[$campo]['tmp_name'], __DIR__ . '/../uploads/productos/' . $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    if ($a === 'producto') {
        $id = (int)($_POST['id'] ?? 0);
        $promo = (int)($_POST['precio_promo'] ?? 0); $promo = $promo > 0 ? $promo : null;
        $d = [(int)$_POST['categoria_id'], trim($_POST['nombre']), trim($_POST['descripcion'] ?? ''), trim($_POST['detalle'] ?? '') ?: null,
              trim($_POST['ingredientes'] ?? '') ?: null, max(0, (int)$_POST['precio']), $promo, isset($_POST['disponible']) ? 1 : 0];
        $img = subir_imagen('imagen');
        if ($id) {
            $pdo->prepare("UPDATE productos SET categoria_id=?, nombre=?, descripcion=?, detalle=?, ingredientes=?, precio=?, precio_promo=?, disponible=? WHERE id=?")->execute([...$d, $id]);
            if ($img) $pdo->prepare("UPDATE productos SET imagen=? WHERE id=?")->execute([$img, $id]);
        } else {
            $pdo->prepare("INSERT INTO productos (categoria_id,nombre,descripcion,detalle,ingredientes,precio,precio_promo,disponible,imagen) VALUES (?,?,?,?,?,?,?,?,?)")->execute([...$d, $img]);
        }
        flash(flash() ?? 'Producto guardado.');
    } elseif ($a === 'toggle') {
        $pdo->prepare("UPDATE productos SET disponible = 1 - disponible WHERE id = ?")->execute([(int)$_POST['id']]);
    } elseif ($a === 'precio') {
        $pdo->prepare("UPDATE productos SET precio = ? WHERE id = ?")->execute([max(0, (int)$_POST['precio']), (int)$_POST['id']]);
        flash('Precio actualizado.');
    } elseif ($a === 'eliminar') {
        $pdo->prepare("UPDATE pedido_items SET producto_id = NULL WHERE producto_id = ?")->execute([(int)$_POST['id']]);
        $pdo->prepare("DELETE FROM productos WHERE id = ?")->execute([(int)$_POST['id']]);
        flash('Producto eliminado.');
    } elseif ($a === 'categoria') {
        $n = trim($_POST['nombre'] ?? '');
        if ($n !== '') $pdo->prepare("INSERT INTO categorias (nombre, orden) VALUES (?, ?)")->execute([$n, (int)($_POST['orden'] ?? 0)]);
        flash('Categoría creada.');
    } elseif ($a === 'cat_eliminar') {
        $n = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ?"); $n->execute([(int)$_POST['id']]);
        if ($n->fetchColumn() > 0) flash('Primero mueve o elimina los productos de esa categoría.');
        else { $pdo->prepare("DELETE FROM categorias WHERE id = ?")->execute([(int)$_POST['id']]); flash('Categoría eliminada.'); }
    }
    header('Location: productos.php' . (isset($_POST['volver']) ? '' : '')); exit;
}

$cats = $pdo->query("SELECT * FROM categorias ORDER BY orden, id")->fetchAll();
$rows = $pdo->query("SELECT p.*, c.nombre AS cat FROM productos p JOIN categorias c ON c.id = p.categoria_id ORDER BY c.orden, p.nombre")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) { $q = $pdo->prepare("SELECT * FROM productos WHERE id = ?"); $q->execute([(int)$_GET['edit']]); $edit = $q->fetch() ?: null; }
$msg = flash();
adm_head('Productos', 'productos'); ?>
<?php if ($msg): ?><p class="alert ok"><?= e($msg) ?></p><?php endif; ?>

<section class="card">
  <h2><?= $edit ? 'Editar producto' : 'Nuevo producto' ?></h2>
  <?php if (!$cats): ?><p class="alert">Primero crea una categoría (abajo).</p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="producto">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="two">
      <label>Nombre<input name="nombre" required value="<?= e($edit['nombre'] ?? '') ?>"></label>
      <label>Categoría<select name="categoria_id" required>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['categoria_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
      </select></label>
      <label>Precio (<?= e(cfg('moneda')) ?>)<input type="number" name="precio" min="0" required value="<?= e($edit['precio'] ?? '') ?>"></label>
      <label>Precio en oferta (opcional)<input type="number" name="precio_promo" min="0" value="<?= e($edit['precio_promo'] ?? '') ?>" placeholder="Vacío = sin oferta"></label>
      <label>Foto (JPG, PNG o WEBP, máx. 3 MB)<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <label>Descripción corta (se ve en la lista)<input name="descripcion" maxlength="250" value="<?= e($edit['descripcion'] ?? '') ?>"></label>
    <label>Descripción completa (se ve al tocar el producto)<textarea name="detalle" rows="3"><?= e($edit['detalle'] ?? '') ?></textarea></label>
    <label>Ingredientes<input name="ingredientes" maxlength="250" value="<?= e($edit['ingredientes'] ?? '') ?>" placeholder="Pan, carne, queso, lechuga…"></label>
    <label><input type="checkbox" name="disponible" <?= ($edit['disponible'] ?? 1) ? 'checked' : '' ?>> Disponible para pedir</label>
    <div class="row"><button class="btn btn-primary" type="submit">Guardar producto</button>
      <?php if ($edit): ?><a class="btn" href="productos.php">Cancelar edición</a><?php endif; ?></div>
  </form>
</section>

<section>
  <h2>Menú actual</h2>
  <div class="tbl-wrap"><table class="tbl">
    <tr><th></th><th>Producto</th><th>Precio</th><th>Disponible</th><th></th></tr>
    <?php foreach ($rows as $p): ?>
    <tr>
      <td><?php if ($p['imagen']): ?><img src="../uploads/productos/<?= e($p['imagen']) ?>" alt=""><?php endif; ?></td>
      <td><b><?= e($p['nombre']) ?></b><?= $p['precio_promo'] ? ' <span class="badge">Oferta ' . money($p['precio_promo']) . '</span>' : '' ?><br><span class="muted"><?= e($p['cat']) ?></span></td>
      <td><form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="precio"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="number" name="precio" value="<?= (int)$p['precio'] ?>" min="0" style="width:120px;display:inline-block;margin:0;padding:.4rem" aria-label="Precio de <?= e($p['nombre']) ?>">
        <button class="btn btn-small">OK</button></form></td>
      <td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-small <?= $p['disponible'] ? '' : 'btn-danger' ?>"><?= $p['disponible'] ? 'Sí' : 'Agotado' ?></button></form></td>
      <td><a class="btn btn-small" href="?edit=<?= $p['id'] ?>">Editar</a> <a class="btn btn-small" href="opciones.php?producto=<?= $p['id'] ?>">Opciones</a>
        <form method="post" class="inline" onsubmit="return confirm('¿Eliminar <?= e(addslashes($p['nombre'])) ?>?')">
          <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn btn-small btn-danger">Eliminar</button></form></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</section>

<section class="card">
  <h2>Categorías</h2>
  <p>Para agregar, renombrar, ordenar o eliminar categorías usa la pantalla dedicada.</p>
  <a class="btn btn-primary" href="categorias.php">Gestionar categorías</a>
</section>
<?php adm_foot();
