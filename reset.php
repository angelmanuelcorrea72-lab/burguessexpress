<?php
require_once __DIR__ . '/includes/layout.php';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$q = db()->prepare("SELECT * FROM reset_tokens WHERE token_hash = ? AND usado = 0 AND expira > NOW()");
$q->execute([hash('sha256', $token)]);
$t = $q->fetch();
$err = ''; $listo = false;
if ($t && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 6) $err = 'La contraseña debe tener al menos 6 caracteres.';
    elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Las contraseñas no coinciden.';
    else {
        db()->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([password_hash($pw, PASSWORD_DEFAULT), $t['usuario_id']]);
        db()->prepare("UPDATE reset_tokens SET usado = 1 WHERE usuario_id = ?")->execute([$t['usuario_id']]);
        $listo = true;
    }
}
head_html('Nueva contraseña'); ?>
<main class="auth">
  <a class="auth-brand" href="index.php"><?= e(cfg('nombre_local')) ?></a>
  <div class="card auth-card">
  <?php if ($listo): ?>
    <h1>Contraseña cambiada</h1>
    <p>Ya puedes ingresar con tu contraseña nueva.</p>
    <a class="btn btn-primary" href="login.php">Ingresar</a>
  <?php elseif (!$t): ?>
    <h1>Enlace no válido</h1>
    <p class="alert">Este enlace venció o ya se usó. Pide uno nuevo.</p>
    <a class="btn btn-primary" href="olvide.php">Pedir otro enlace</a>
  <?php else: ?>
    <form method="post">
      <h1>Crea tu nueva contraseña</h1>
      <?php if ($err): ?><p class="alert"><?= e($err) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
      <label>Nueva contraseña<input type="password" name="password" minlength="6" required autocomplete="new-password" autofocus></label>
      <label>Repítela<input type="password" name="password2" minlength="6" required autocomplete="new-password"></label>
      <button class="btn btn-primary" type="submit">Guardar contraseña</button>
    </form>
  <?php endif; ?>
  </div>
</main>
<?php foot_html(false);
