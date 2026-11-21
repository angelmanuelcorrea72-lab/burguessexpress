<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$q = db()->prepare("SELECT p.*, z.nombre AS zona FROM pedidos p LEFT JOIN zonas z ON z.id = p.zona_id WHERE p.id = ? AND p.usuario_id = ?");
$q->execute([(int)($_GET['id'] ?? 0), user()['id']]);
$p = $q->fetch();
if (!$p) { http_response_code(404); die('Pedido no encontrado.'); }
$it = db()->prepare("SELECT * FROM pedido_items WHERE pedido_id = ?");
$it->execute([$p['id']]);
$items = $it->fetchAll();
$pasos = $p['tipo'] === 'delivery'
    ? ['pendiente' => 'Recibido', 'preparando' => 'Preparando', 'en_camino' => 'En camino', 'entregado' => 'Entregado']
    : ['pendiente' => 'Recibido', 'preparando' => 'Preparando', 'listo' => 'Listo para retirar', 'entregado' => 'Entregado'];
$sinPagar = $p['pago'] === 'bancard' && $p['pago_estado'] !== 'pagado';
head_html('Pedido #' . $p['id']); ?>
<main class="page">
  <a class="back" href="index.php">← Volver al menú</a>
  <h1>Pedido #<?= (int)$p['id'] ?></h1>

  <?php if ($sinPagar): ?>
  <section class="card">
    <p class="alert" style="margin-top:0"><?= $p['pago_estado'] === 'fallido' ? 'El pago no se pudo completar.' : 'Tu pedido todavía no está pagado. El local lo recibe cuando se confirma el pago.' ?></p>
    <a class="btn btn-primary block" style="margin-top:0" href="pagar.php?id=<?= (int)$p['id'] ?>">Pagar ahora</a>
  </section>
  <?php else: ?>
  <section class="card track" id="track" data-id="<?= (int)$p['id'] ?>">
    <p class="eta" id="eta"></p>
    <p class="muted" id="rep" hidden></p>
    <ol class="steps" id="steps">
      <?php foreach ($pasos as $k => $label): ?><li data-k="<?= $k ?>"><i></i><?= e($label) ?></li><?php endforeach; ?>
    </ol>
    <p class="alert" id="cancelado" hidden>Este pedido fue cancelado. Escríbenos si tienes dudas.</p>
    <button class="btn btn-small" id="btnAvisos" type="button" hidden style="margin-top:1rem">Avisarme cuando cambie el estado</button>
    <p class="muted" id="avisosMsg"></p>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Detalle</h2>
    <?php foreach ($items as $i): ?>
      <div class="line"><span class="nm"><?= (int)$i['cantidad'] ?> × <?= e($i['nombre']) ?><?php if ($i['opciones']): ?><small class="muted"><?= e($i['opciones']) ?></small><?php endif; ?></span><b><?= money($i['precio'] * $i['cantidad']) ?></b></div>
    <?php endforeach; ?>
    <?php if ($p['tipo'] === 'delivery'): ?><div class="line"><span>Delivery<?= $p['zona'] ? ' · ' . e($p['zona']) : '' ?></span><b><?= money($p['envio']) ?></b></div><?php endif; ?>
    <?php if ($p['descuento'] > 0): ?><div class="line"><span>Cupón <?= e($p['cupon']) ?></span><b>−<?= money($p['descuento']) ?></b></div><?php endif; ?>
    <div class="line grand"><span>Total</span><b><?= money($p['total']) ?></b></div>
    <p class="muted">
      <?= $p['tipo'] === 'delivery' ? 'Delivery' : 'Retiro en local' ?> · Pago: <?= $p['pago'] === 'bancard' ? 'en línea (' . ($p['pago_estado'] === 'pagado' ? 'pagado' : 'pendiente') . ')' : e($p['pago']) ?>
      <?php if ($p['direccion']): ?><br><?= e($p['direccion']) ?><?php endif; ?>
      <?php if ($p['notas']): ?><br>Nota: <?= e($p['notas']) ?><?php endif; ?>
    </p>
  </section>

  <a class="btn btn-wa block" target="_blank" rel="noopener"
     href="<?= e(wa_link('Hola, consulto por mi pedido #' . $p['id'])) ?>">Escribir al local por WhatsApp</a>
</main>
<?php if (!$sinPagar): ?>
<script>
(function () {
  const box = document.getElementById('track'), id = box.dataset.id, eta = document.getElementById('eta'), rep = document.getElementById('rep');
  const lis = [...document.querySelectorAll('#steps li')], order = lis.map(l => l.dataset.k);
  const MSG = {preparando: 'Estamos preparando tu pedido.', listo: 'Tu pedido está listo. Puedes pasar a retirarlo.',
               en_camino: 'Tu pedido salió y va en camino.', entregado: '¡Buen provecho!', cancelado: 'Tu pedido fue cancelado.'};
  const btn = document.getElementById('btnAvisos'), am = document.getElementById('avisosMsg');
  let last = null;

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  if ('Notification' in window) {
    if (Notification.permission === 'default') btn.hidden = false;
    else if (Notification.permission === 'granted') am.textContent = 'Avisos activados: te avisamos mientras esta página siga abierta.';
    btn.onclick = async () => {
      const r = await Notification.requestPermission();
      btn.hidden = true;
      am.textContent = r === 'granted' ? 'Avisos activados: te avisamos mientras esta página siga abierta.' : 'No activaste los avisos. Puedes seguir mirando aquí.';
    };
  }
  async function notify(title, body) {
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    document.title = '● ' + title;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
      const reg = await navigator.serviceWorker.getRegistration();
      if (reg) reg.showNotification(title, {body, tag: 'pedido-' + id, vibrate: [200, 100, 200], data: {url: location.href}});
      else new Notification(title, {body});
    } catch (e) {}
  }
  async function tick() {
    try {
      const d = await (await fetch('api/estado.php?id=' + id, {cache: 'no-store'})).json();
      if (d.error) return;
      const idx = order.indexOf(d.estado);
      lis.forEach((l, i) => l.className = d.estado === 'cancelado' ? '' : (i < idx ? 'done' : i === idx ? 'now' : ''));
      document.getElementById('cancelado').hidden = d.estado !== 'cancelado';
      if (d.estado === 'entregado') eta.textContent = '¡Buen provecho!';
      else if (d.estado === 'cancelado') eta.textContent = 'Pedido cancelado';
      else if (d.estado === 'listo') eta.textContent = 'Tu pedido está listo. Puedes pasar a retirarlo.';
      else eta.textContent = d.restante > 0 ? 'Llega en unos ' + d.restante + ' min' : 'Está por llegar, estamos terminando';
      if (d.repartidor && ['en_camino', 'preparando'].includes(d.estado)) {
        rep.hidden = false; rep.textContent = 'Repartidor: ' + d.repartidor;
        if (d.repartidor_wa) { const a = document.createElement('a'); a.href = 'https://wa.me/' + d.repartidor_wa; a.target = '_blank'; a.rel = 'noopener'; a.textContent = ' · escribirle'; rep.appendChild(a); }
      } else rep.hidden = true;
      if (last !== null && last !== d.estado && MSG[d.estado]) notify('Pedido #' + id + ': ' + d.etiqueta, MSG[d.estado]);
      last = d.estado;
    } catch (e) {}
  }
  tick(); setInterval(tick, 8000);
})();
</script>
<?php endif; ?>
<?php foot_html();
