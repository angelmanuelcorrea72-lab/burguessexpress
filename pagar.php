<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$q = db()->prepare("SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?");
$q->execute([(int)($_GET['id'] ?? 0), user()['id']]);
$p = $q->fetch();
if (!$p || $p['pago'] !== 'bancard') { http_response_code(404); die('Pedido no encontrado.'); }
if ($p['pago_estado'] === 'pagado') { header('Location: pedido.php?id=' . $p['id']); exit; }
if (!bancard_activo()) die('El pago en línea no está disponible por ahora.');
if ($p['estado'] === 'cancelado') die('Este pedido fue cancelado.');

// Cada intento usa un shop_process_id nuevo: pedido*100 + intento
db()->prepare("UPDATE pedidos SET bancard_intentos = bancard_intentos + 1 WHERE id = ?")->execute([$p['id']]);
$intento = (int)db()->query("SELECT bancard_intentos FROM pedidos WHERE id = " . (int)$p['id'])->fetchColumn();
$sp = (int)$p['id'] * 100 + min($intento, 99);
$monto = bancard_monto($p['total']);
$res = bancard_post('/vpos/api/0.3/single_buy', [
    'public_key' => BANCARD_PUBLIC_KEY,
    'operation' => [
        'token' => md5(BANCARD_PRIVATE_KEY . $sp . $monto . 'PYG'),
        'shop_process_id' => $sp, 'amount' => $monto, 'currency' => 'PYG',
        'additional_data' => '', 'description' => 'Pedido #' . $p['id'] . ' ' . cfg('nombre_local'),
        'return_url' => base_url() . '/pedido.php?id=' . $p['id'], 'cancel_url' => base_url() . '/pedido.php?id=' . $p['id'],
    ],
]);
$pid = $res['process_id'] ?? null;
head_html('Pagar pedido'); ?>
<main class="page">
  <a class="back" href="pedido.php?id=<?= (int)$p['id'] ?>">← Volver a mi pedido</a>
  <h1>Pagar pedido #<?= (int)$p['id'] ?></h1>
  <p>Total a pagar: <b><?= money($p['total']) ?></b></p>
  <?php if ($pid): ?>
    <div id="bancard-box" class="card" style="min-height:420px"></div>
    <script src="<?= e(bancard_base()) ?>/checkout/javascript/dist/bancard-checkout-2.1.0.js"></script>
    <script>
      window.onload = function () {
        if (window.Bancard && Bancard.Checkout) Bancard.Checkout.createForm('bancard-box', <?= json_encode($pid) ?>, {});
        else document.getElementById('bancard-box').textContent = 'No se pudo cargar el formulario de pago. Intenta de nuevo.';
      };
    </script>
    <p class="muted">Tras pagar, tu pedido llega al local automáticamente. Puedes volver a «Mi pedido» para seguirlo.</p>
  <?php else: ?>
    <p class="alert">No pudimos iniciar el pago en línea. Intenta de nuevo en unos segundos o escríbenos por WhatsApp.</p>
    <a class="btn btn-primary block" href="pagar.php?id=<?= (int)$p['id'] ?>">Reintentar</a>
    <a class="btn btn-wa block" target="_blank" rel="noopener" href="<?= e(wa_link('Hola, no pude pagar en línea mi pedido #' . $p['id'])) ?>">Escribir por WhatsApp</a>
  <?php endif; ?>
</main>
<?php foot_html();
