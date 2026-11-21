<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['accion'] ?? '';
    if ($a === 'config') {
        $claves = ['nombre_local', 'whatsapp', 'direccion', 'moneda', 'costo_delivery', 'tiempo_retiro', 'tiempo_delivery', 'modo', 'mensaje_cierre', 'codigo_pais'];
        $st = $pdo->prepare("INSERT INTO configuracion (clave,valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($claves as $k) {
            $v = trim($_POST[$k] ?? '');
            if ($k === 'modo' && !in_array($v, ['auto', 'abierto', 'cerrado'], true)) $v = 'auto';
            if (in_array($k, ['costo_delivery', 'tiempo_retiro', 'tiempo_delivery'], true)) $v = (string)max(0, (int)$v);
            if ($k === 'whatsapp' || $k === 'codigo_pais') $v = preg_replace('/\D/', '', $v);
            $st->execute([$k, $v]);
        }
        $h = $pdo->prepare("UPDATE horarios SET abre=?, cierra=?, cerrado=? WHERE dia=?");
        for ($d = 0; $d < 7; $d++) $h->execute([$_POST["abre$d"] ?: '00:00', $_POST["cierra$d"] ?: '00:00', isset($_POST["cerrado$d"]) ? 1 : 0, $d]);
        flash('Ajustes guardados.');
    } elseif ($a === 'empleado') {
        $em = strtolower(trim($_POST['email'] ?? '')); $pw = $_POST['password'] ?? '';
        if (!filter_var($em, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6 || trim($_POST['nombre'] ?? '') === '') flash('Completa nombre, correo válido y contraseña de 6+ caracteres.');
        else {
            try {
                $pdo->prepare("INSERT INTO usuarios (nombre,email,telefono,password,rol) VALUES (?,?,?,?,?)")
                    ->execute([trim($_POST['nombre']), $em, trim($_POST['telefono'] ?? ''), password_hash($pw, PASSWORD_DEFAULT), $_POST['rol'] === 'admin' ? 'admin' : 'empleado']);
                flash('Usuario del local creado.');
            } catch (PDOException $ex) { flash('Ese correo ya está registrado.'); }
        }
    } elseif ($a === 'quitar') {
        $pdo->prepare("UPDATE usuarios SET rol='cliente' WHERE id=? AND id<>?")->execute([(int)$_POST['id'], user()['id']]);
        flash('Acceso al panel retirado.');
    } elseif ($a === 'clave') {
        if (strlen($_POST['password'] ?? '') < 6) flash('La contraseña debe tener 6+ caracteres.');
        else { $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), user()['id']]); flash('Contraseña cambiada.'); }
    }
    header('Location: ajustes.php'); exit;
}
$hs = $pdo->query("SELECT * FROM horarios ORDER BY dia")->fetchAll();
$staff = $pdo->query("SELECT id,nombre,email,rol FROM usuarios WHERE rol IN ('admin','empleado') ORDER BY rol, nombre")->fetchAll();
$msg = flash();
adm_head('Ajustes', 'ajustes'); ?>
<?php if ($msg): ?><p class="alert ok"><?= e($msg) ?></p><?php endif; ?>

<form method="post" class="card">
  <h2>Datos del local</h2>
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="config">
  <div class="two">
    <label>Nombre del local<input name="nombre_local" value="<?= e(cfg('nombre_local')) ?>" required></label>
    <label>WhatsApp (con código de país, solo números)<input name="whatsapp" value="<?= e(cfg('whatsapp')) ?>" inputmode="numeric"></label>
    <label>Código de país para WhatsApp (ej. 595)<input name="codigo_pais" value="<?= e(cfg('codigo_pais', '595')) ?>" inputmode="numeric" maxlength="4"></label>
    <label>Dirección<input name="direccion" value="<?= e(cfg('direccion')) ?>"></label>
    <label>Símbolo de moneda<input name="moneda" value="<?= e(cfg('moneda')) ?>" maxlength="6"></label>
    <label>Costo de delivery<input type="number" min="0" name="costo_delivery" value="<?= e(cfg('costo_delivery')) ?>"></label>
    <label>Minutos estimados: retiro<input type="number" min="1" name="tiempo_retiro" value="<?= e(cfg('tiempo_retiro')) ?>"></label>
    <label>Minutos estimados: delivery<input type="number" min="1" name="tiempo_delivery" value="<?= e(cfg('tiempo_delivery')) ?>"></label>
    <label>Estado del local<select name="modo">
      <option value="auto" <?= cfg('modo') === 'auto' ? 'selected' : '' ?>>Automático según horario</option>
      <option value="abierto" <?= cfg('modo') === 'abierto' ? 'selected' : '' ?>>Forzar abierto</option>
      <option value="cerrado" <?= cfg('modo') === 'cerrado' ? 'selected' : '' ?>>Cerrar ahora (no recibe pedidos)</option>
    </select></label>
  </div>
  <label>Mensaje cuando está cerrado<input name="mensaje_cierre" value="<?= e(cfg('mensaje_cierre')) ?>"></label>

  <h2 style="margin-top:1.4rem">Horarios</h2>
  <?php foreach ($hs as $h): ?>
    <div class="line"><span class="nm"><?= DIAS[$h['dia']] ?></span>
      <input type="time" name="abre<?= $h['dia'] ?>" value="<?= substr($h['abre'], 0, 5) ?>" style="width:120px;margin:0" aria-label="Abre <?= DIAS[$h['dia']] ?>">
      <input type="time" name="cierra<?= $h['dia'] ?>" value="<?= substr($h['cierra'], 0, 5) ?>" style="width:120px;margin:0" aria-label="Cierra <?= DIAS[$h['dia']] ?>">
      <label style="margin:0"><input type="checkbox" name="cerrado<?= $h['dia'] ?>" <?= $h['cerrado'] ? 'checked' : '' ?>> Cerrado</label></div>
  <?php endforeach; ?>
  <p class="muted">Si el cierre es menor que la apertura (por ejemplo 18:00 a 01:00), el sistema entiende que cierra de madrugada.</p>
  <button class="btn btn-primary" type="submit">Guardar ajustes</button>
</form>

<section class="card">
  <h2>Personal con acceso al panel</h2>
  <?php foreach ($staff as $s): ?>
    <div class="line"><span class="nm"><b><?= e($s['nombre']) ?></b> · <?= e($s['email']) ?> · <?= $s['rol'] === 'admin' ? 'Administrador' : 'Empleado' ?></span>
      <?php if ($s['id'] != user()['id']): ?>
      <form method="post" class="inline" onsubmit="return confirm('¿Quitar acceso?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="quitar"><input type="hidden" name="id" value="<?= $s['id'] ?>">
        <button class="btn btn-small btn-danger">Quitar acceso</button></form><?php endif; ?></div>
  <?php endforeach; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="empleado">
    <div class="two"><label>Nombre<input name="nombre" required></label><label>Correo<input type="email" name="email" required></label>
      <label>Teléfono<input name="telefono"></label><label>Contraseña inicial<input type="password" name="password" minlength="6" required></label></div>
    <label>Rol<select name="rol"><option value="empleado">Empleado (pedidos y productos)</option><option value="admin">Administrador (todo)</option></select></label>
    <button class="btn btn-primary" style="margin-top:1rem">Crear usuario del local</button>
  </form>
</section>

<form method="post" class="card"><h2>Cambiar mi contraseña</h2>
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="clave">
  <label>Nueva contraseña<input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
  <button class="btn" style="margin-top:1rem">Cambiar contraseña</button>
</form>
<?php adm_foot();
