<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

/** Crea el usuario de acceso de un repartidor. Devuelve el id o un mensaje de error. */
function crear_acceso_rep(string $nombre, string $tel, string $email, string $pw) {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'El correo de acceso no es válido.';
    if (strlen($pw) < 6) return 'La contraseña debe tener al menos 6 caracteres.';
    try {
        db()->prepare("INSERT INTO usuarios (nombre,email,telefono,password,rol) VALUES (?,?,?,?, 'repartidor')")
            ->execute([$nombre, $email, $tel, password_hash($pw, PASSWORD_DEFAULT)]);
    } catch (PDOException $ex) { return 'Ese correo ya está registrado. Usa otro.'; }
    return (int)db()->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'zona' && trim($_POST['nombre'] ?? '') !== '') {
        $pdo->prepare("INSERT INTO zonas (nombre,costo,minutos_extra) VALUES (?,?,?)")->execute([trim($_POST['nombre']), max(0, (int)$_POST['costo']), max(0, (int)$_POST['minutos_extra'])]);
        flash('✓ Zona agregada.');
    } elseif ($a === 'zona_edit') {
        $pdo->prepare("UPDATE zonas SET nombre=?, costo=?, minutos_extra=?, activa=? WHERE id=?")
            ->execute([trim($_POST['nombre']), max(0, (int)$_POST['costo']), max(0, (int)$_POST['minutos_extra']), isset($_POST['activa']) ? 1 : 0, $id]);
        flash('✓ Zona actualizada.');
    } elseif ($a === 'zona_del') {
        $pdo->prepare("UPDATE pedidos SET zona_id = NULL WHERE zona_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM zonas WHERE id = ?")->execute([$id]);
        flash('✓ Zona eliminada.');
    } elseif ($a === 'rep' && trim($_POST['nombre'] ?? '') !== '') {
        $nombre = trim($_POST['nombre']); $tel = trim($_POST['telefono'] ?? ''); $uid = null;
        if (trim($_POST['email'] ?? '') !== '' || ($_POST['password'] ?? '') !== '') {
            $uid = crear_acceso_rep($nombre, $tel, $_POST['email'] ?? '', $_POST['password'] ?? '');
            if (is_string($uid)) { flash($uid); header('Location: zonas.php'); exit; }
        }
        $pdo->prepare("INSERT INTO repartidores (nombre,telefono,usuario_id) VALUES (?,?,?)")->execute([$nombre, $tel, $uid]);
        flash('✓ Repartidor agregado' . ($uid ? ' con acceso a su panel.' : '. Puedes darle acceso a su panel cuando quieras.'));
    } elseif ($a === 'rep_edit') {
        $pdo->prepare("UPDATE repartidores SET nombre = ?, telefono = ? WHERE id = ?")->execute([trim($_POST['nombre']), trim($_POST['telefono'] ?? ''), $id]);
        flash('✓ Datos del repartidor actualizados.');
    } elseif ($a === 'rep_acceso') {
        $q = $pdo->prepare("SELECT * FROM repartidores WHERE id = ? AND usuario_id IS NULL"); $q->execute([$id]);
        if ($rep = $q->fetch()) {
            $uid = crear_acceso_rep($rep['nombre'], (string)$rep['telefono'], $_POST['email'] ?? '', $_POST['password'] ?? '');
            if (is_string($uid)) flash($uid);
            else { $pdo->prepare("UPDATE repartidores SET usuario_id = ? WHERE id = ?")->execute([$uid, $id]); flash('✓ Acceso creado. Ya puede ingresar en la página de login.'); }
        }
    } elseif ($a === 'rep_clave') {
        if (strlen($_POST['password'] ?? '') < 6) flash('La contraseña debe tener al menos 6 caracteres.');
        else {
            $pdo->prepare("UPDATE usuarios x JOIN repartidores r ON r.usuario_id = x.id SET x.password = ? WHERE r.id = ?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            flash('✓ Contraseña del repartidor cambiada.');
        }
    } elseif ($a === 'rep_quitar') {
        $pdo->prepare("UPDATE usuarios x JOIN repartidores r ON r.usuario_id = x.id SET x.rol = 'cliente' WHERE r.id = ? AND x.rol = 'repartidor'")->execute([$id]);
        $pdo->prepare("UPDATE repartidores SET usuario_id = NULL WHERE id = ?")->execute([$id]);
        flash('✓ Acceso al panel de reparto retirado.');
    } elseif ($a === 'rep_toggle') {
        $pdo->prepare("UPDATE repartidores SET activo = 1 - activo WHERE id = ?")->execute([$id]);
    } elseif ($a === 'rep_del') {
        $pdo->prepare("UPDATE usuarios x JOIN repartidores r ON r.usuario_id = x.id SET x.rol = 'cliente' WHERE r.id = ? AND x.rol = 'repartidor'")->execute([$id]);
        $pdo->prepare("UPDATE pedidos SET repartidor_id = NULL WHERE repartidor_id = ? AND estado IN ('pendiente','preparando','listo','en_camino')")->execute([$id]);
        $pdo->prepare("DELETE FROM repartidores WHERE id = ?")->execute([$id]);
        flash('✓ Repartidor eliminado. Sus pedidos entregados quedan en el historial sin repartidor asignado.');
    }
    header('Location: zonas.php'); exit;
}
$zonas = $pdo->query("SELECT * FROM zonas ORDER BY costo")->fetchAll();
$reps = $pdo->query("SELECT r.*, x.email,
    (SELECT COUNT(*) FROM pedidos p WHERE p.repartidor_id = r.id AND DATE(p.creado) = CURDATE()) AS hoy,
    (SELECT COUNT(*) FROM pedidos p WHERE p.repartidor_id = r.id AND p.estado IN ('pendiente','preparando','listo','en_camino')) AS activos
    FROM repartidores r LEFT JOIN usuarios x ON x.id = r.usuario_id ORDER BY r.activo DESC, r.nombre")->fetchAll();
$msg = flash();
adm_head('Delivery', 'zonas'); ?>
<?php if ($msg): ?><p class="alert <?= $msg[0] === '✓' ? 'ok' : '' ?>" role="status"><?= e($msg) ?></p><?php endif; ?>
<section class="card">
  <h2>Zonas de delivery</h2>
  <p class="muted">El cliente elige su zona y paga el costo que definas aquí. Si no hay zonas activas, se usa el costo único de Ajustes.</p>
  <?php foreach ($zonas as $z): ?>
  <form method="post" class="line" style="flex-wrap:wrap">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="zona_edit"><input type="hidden" name="id" value="<?= $z['id'] ?>">
    <input name="nombre" value="<?= e($z['nombre']) ?>" style="flex:2;min-width:160px;margin:0" aria-label="Nombre de la zona">
    <input type="number" name="costo" value="<?= (int)$z['costo'] ?>" min="0" style="width:120px;margin:0" aria-label="Costo">
    <input type="number" name="minutos_extra" value="<?= (int)$z['minutos_extra'] ?>" min="0" style="width:90px;margin:0" aria-label="Minutos extra" title="Minutos extra">
    <label style="margin:0"><input type="checkbox" name="activa" <?= $z['activa'] ? 'checked' : '' ?>> Activa</label>
    <button class="btn btn-small">Guardar</button>
    <button class="btn btn-small btn-danger" name="accion" value="zona_del" onclick="return confirm('¿Eliminar la zona?')">Eliminar</button>
  </form>
  <?php endforeach; ?>
  <form method="post" class="row">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="zona">
    <input name="nombre" placeholder="Nueva zona (ej. Barrio Norte)" required style="flex:2"><input type="number" name="costo" placeholder="Costo" min="0" required style="flex:1">
    <input type="number" name="minutos_extra" placeholder="Min. extra" min="0" value="0" style="flex:1"><button class="btn btn-primary">Agregar zona</button>
  </form>
  <p class="muted">Orden de columnas: nombre · costo · minutos extra sobre el tiempo base de delivery.</p>
</section>

<section class="card">
  <h2>Repartidores</h2>
  <p class="muted">Con acceso, el repartidor entra desde su celular en la misma página de login y ve solo los pedidos que le asignes. Los pedidos se asignan desde <a href="index.php">Pedidos</a>.</p>
  <?php foreach ($reps as $r): ?>
  <div class="cat-box" style="<?= $r['activo'] ? '' : 'opacity:.6' ?>">
    <form method="post" class="cat-edit">
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep_edit"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <input name="nombre" value="<?= e($r['nombre']) ?>" required style="flex:2;min-width:140px" aria-label="Nombre">
      <input name="telefono" value="<?= e($r['telefono']) ?>" placeholder="WhatsApp" style="flex:2;min-width:130px" aria-label="Teléfono">
      <button class="btn btn-small">Guardar</button>
    </form>
    <p class="muted" style="margin:.4rem 0"><?= (int)$r['activos'] ?> en curso · <?= (int)$r['hoy'] ?> hoy · <?= $r['activo'] ? 'Activo' : 'Inactivo' ?> ·
      <?= $r['email'] ? 'Acceso: <b>' . e($r['email']) . '</b>' : 'Sin acceso al panel' ?></p>
    <div class="cat-del">
      <?php if ($r['usuario_id']): ?>
        <form method="post" class="row" style="margin:0;flex:1"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep_clave"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <input type="password" name="password" minlength="6" placeholder="Nueva contraseña" required style="margin:0;flex:2" autocomplete="new-password" aria-label="Nueva contraseña"><button class="btn btn-small">Cambiar clave</button></form>
        <form method="post" class="inline" onsubmit="return confirm('¿Quitarle el acceso al panel?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep_quitar"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-small btn-danger">Quitar acceso</button></form>
      <?php else: ?>
        <form method="post" class="row" style="margin:0;flex:1"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep_acceso"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <input type="email" name="email" placeholder="Correo de acceso" required style="margin:0;flex:2" aria-label="Correo"><input type="password" name="password" minlength="6" placeholder="Contraseña" required style="margin:0;flex:2" autocomplete="new-password" aria-label="Contraseña">
          <button class="btn btn-small btn-primary">Crear acceso</button></form>
      <?php endif; ?>
      <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep_toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-small"><?= $r['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
      <form method="post" class="inline" onsubmit="return confirm('¿Eliminar al repartidor? Sus pedidos en curso quedarán sin asignar.')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep_del"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-small btn-danger">Eliminar</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <form method="post" class="card" style="background:var(--bg);box-shadow:none">
    <h3 style="margin-bottom:.4rem">Nuevo repartidor</h3>
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="rep">
    <div class="two"><label>Nombre<input name="nombre" required></label><label>Teléfono / WhatsApp<input name="telefono" placeholder="0981 123 456"></label>
      <label>Correo de acceso (opcional)<input type="email" name="email" autocomplete="off"></label><label>Contraseña (opcional)<input type="password" name="password" minlength="6" autocomplete="new-password"></label></div>
    <button class="btn btn-primary" style="margin-top:1rem">Agregar repartidor</button>
  </form>
</section>
<?php adm_foot();
