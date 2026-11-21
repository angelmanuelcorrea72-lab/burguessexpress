<?php
require_once __DIR__ . '/includes/layout.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $q = db()->prepare("SELECT * FROM usuarios WHERE email = ?");
    $q->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $u = $q->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        login_user($u);
        header('Location: ' . (in_array($u['rol'], ['admin', 'empleado']) ? 'admin/index.php' : ($u['rol'] === 'repartidor' ? 'repartidor/index.php' : 'index.php')));
        exit;
    }
    $err = 'Correo o contraseña incorrectos.';
}
head_html('Ingresar'); ?>
<main class="auth">
  <a class="auth-brand" href="index.php"><?= e(cfg('nombre_local')) ?></a>
  <form method="post" class="card auth-card">
    <h1>Ingresar</h1>
    <?php if ($err): ?><p class="alert"><?= e($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Correo<input type="email" name="email" required autocomplete="email" autofocus></label>
    <label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary" type="submit">Ingresar</button>
    <p class="muted"><a href="olvide.php">Olvidé mi contraseña</a></p>
    <p class="muted">¿Primera vez? <a href="registro.php">Crear mi cuenta</a></p>
  </form>
</main>
<?php foot_html(false);
