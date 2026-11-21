<?php
require_once __DIR__ . '/includes/layout.php';
$err = ''; $v = ['nombre' => '', 'email' => '', 'telefono' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v = ['nombre' => trim($_POST['nombre'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? '')), 'telefono' => trim($_POST['telefono'] ?? '')];
    $pass = $_POST['password'] ?? '';
    if (mb_strlen($v['nombre']) < 2) $err = 'Escribe tu nombre.';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'El correo no es válido.';
    elseif (strlen(preg_replace('/\D/', '', $v['telefono'])) < 6) $err = 'Escribe un teléfono para contactarte.';
    elseif (strlen($pass) < 6) $err = 'La contraseña debe tener al menos 6 caracteres.';
    else {
        $q = db()->prepare("SELECT id FROM usuarios WHERE email = ?");
        $q->execute([$v['email']]);
        if ($q->fetch()) $err = 'Ese correo ya tiene una cuenta. Ingresa en su lugar.';
        else {
            db()->prepare("INSERT INTO usuarios (nombre,email,telefono,password) VALUES (?,?,?,?)")
                ->execute([$v['nombre'], $v['email'], $v['telefono'], password_hash($pass, PASSWORD_DEFAULT)]);
            login_user(['id' => db()->lastInsertId(), 'nombre' => $v['nombre'], 'rol' => 'cliente']);
            header('Location: index.php'); exit;
        }
    }
}
head_html('Crear cuenta'); ?>
<main class="auth">
  <a class="auth-brand" href="index.php"><?= e(cfg('nombre_local')) ?></a>
  <form method="post" class="card auth-card">
    <h1>Crear mi cuenta</h1>
    <?php if ($err): ?><p class="alert"><?= e($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Nombre<input name="nombre" value="<?= e($v['nombre']) ?>" required autocomplete="name"></label>
    <label>Correo<input type="email" name="email" value="<?= e($v['email']) ?>" required autocomplete="email"></label>
    <label>Teléfono<input type="tel" name="telefono" value="<?= e($v['telefono']) ?>" required autocomplete="tel"></label>
    <label>Contraseña<input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
    <button class="btn btn-primary" type="submit">Crear cuenta</button>
    <p class="muted">¿Ya tienes cuenta? <a href="login.php">Ingresar</a></p>
  </form>
</main>
<?php foot_html(false);
