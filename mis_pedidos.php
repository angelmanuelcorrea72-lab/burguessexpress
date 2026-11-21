<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$q = db()->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY id DESC LIMIT 50");
$q->execute([user()['id']]);
$rows = $q->fetchAll();
head_html('Mis pedidos'); ?>
<main class="page">
  <a class="back" href="index.php">← Volver al menú</a>
  <h1>Mis pedidos</h1>
  <?php if (!$rows): ?><p class="muted">Aún no hiciste ningún pedido.</p><?php endif; ?>
  <?php foreach ($rows as $p): ?>
    <a class="card order-link" href="pedido.php?id=<?= $p['id'] ?>">
      <div><b>Pedido #<?= $p['id'] ?></b><br><span class="muted"><?= date('d/m/Y H:i', strtotime($p['creado'])) ?> · <?= $p['tipo'] === 'delivery' ? 'Delivery' : 'Retiro' ?></span></div>
      <div class="right"><b><?= money($p['total']) ?></b><br><span class="pill st-<?= $p['estado'] ?>"><?= e(ESTADOS[$p['estado']]) ?></span></div>
    </a>
  <?php endforeach; ?>
</main>
<?php foot_html();
