<?php
require_once __DIR__ . '/includes/layout.php';
$enviado = false; $debug = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $q = db()->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
    $q->execute([$email]);
    if ($u = $q->fetch()) {
        $n = db()->prepare("SELECT COUNT(*) FROM reset_tokens WHERE usuario_id = ? AND creado > NOW() - INTERVAL 1 HOUR");
        $n->execute([$u['id']]);
        if ($n->fetchColumn() < 3) {   // máximo 3 solicitudes por hora
            $token = bin2hex(random_bytes(32));
            db()->prepare("INSERT INTO reset_tokens (usuario_id, token_hash, expira) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)")
                ->execute([$u['id'], hash('sha256', $token)]);
            $link = base_url() . '/reset.php?token=' . $token;
            send_mail($email, 'Recupera tu contraseña · ' . cfg('nombre_local'),
                "Hola {$u['nombre']},\n\nPara crear una contraseña nueva abre este enlace (vale 1 hora):\n$link\n\nSi no lo pediste tú, ignora este mensaje.\n");
            if (MAIL_DEBUG) $debug = $link;
        }
    }
    $enviado = true;   // misma respuesta exista o no el correo
}
head_html('Recuperar contraseña'); ?>
<main class="auth">
  <a class="auth-brand" href="index.php"><?= e(cfg('nombre_local')) ?></a>
  <form method="post" class="card auth-card">
    <h1>Recuperar contraseña</h1>
    <?php if ($enviado): ?>
      <p class="alert ok">Si ese correo tiene una cuenta, te enviamos un enlace para crear una contraseña nueva. Revisa también la carpeta de spam.</p>
      <?php if ($debug): ?><p class="muted">Modo de pruebas: <a href="<?= e($debug) ?>">abrir enlace de recuperación</a></p><?php endif; ?>
    <?php endif; ?>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Correo de tu cuenta<input type="email" name="email" required autocomplete="email" autofocus></label>
    <button class="btn btn-primary" type="submit">Enviarme el enlace</button>
    <p class="muted"><a href="login.php">Volver a ingresar</a></p>
  </form>
</main>
<?php foot_html(false);
