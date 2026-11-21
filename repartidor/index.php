<?php
require_once __DIR__ . '/_layout.php';
$rep = require_courier();
rep_head('Mis pedidos', 'pedidos', $rep); ?>
<div class="stats">
  <div class="stat"><span class="muted">Entregas de hoy</span><b id="sEnt">–</b></div>
  <div class="stat"><span class="muted">Efectivo a rendir hoy</span><b id="sEf">–</b></div>
  <div class="stat"><span class="muted">Envíos de hoy</span><b id="sEnv">–</b></div>
</div>
<div class="tabs"><button class="btn btn-small" id="btnAvisos" type="button">Activar sonido y avisos de nuevos pedidos</button></div>
<p class="muted" id="estadoConexion"></p>
<div id="lista" class="rep-list"></div>
<p class="muted center" id="vacio" hidden>No tienes pedidos asignados por ahora. Cuando el local te asigne uno, suena un aviso y aparece aquí.</p>
<script>
const CSRF = <?= json_encode(csrf()) ?>;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let known = null, sonido = false, ctx = null, WA_LOCAL = '';

function beep() {
  if (navigator.vibrate) navigator.vibrate([300, 150, 300, 150, 300]);
  if (!sonido || !ctx) return;
  [880, 660, 880, 660].forEach((f, i) => {
    const o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(.25, ctx.currentTime + i * .25); g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + i * .25 + .22);
    o.start(ctx.currentTime + i * .25); o.stop(ctx.currentTime + i * .25 + .22);
  });
}
async function notificar(o) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return;
  const t = 'Nuevo pedido #' + o.id, b = o.direccion || o.zona || 'Revisa los detalles';
  try { const reg = navigator.serviceWorker && await navigator.serviceWorker.getRegistration(); reg ? reg.showNotification(t, {body: b, tag: 'rep-' + o.id, vibrate: [300, 150, 300]}) : new Notification(t, {body: b}); } catch (e) {}
}
document.getElementById('btnAvisos').onclick = async e => {
  ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); sonido = true; beep();
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('../sw.js').catch(() => {});
  if ('Notification' in window && Notification.permission === 'default') await Notification.requestPermission();
  e.target.textContent = 'Avisos activados ✓ (deja esta pantalla abierta)';
};

function card(o) {
  const items = o.items.map(i => `<li>${i.q} × ${esc(i.n)}${i.o ? `<br><small class="muted">${esc(i.o)}</small>` : ''}</li>`).join('');
  const accion = o.accion === 'salir' ? `<button class="btn btn-primary block" data-est="en_camino" data-id="${o.id}">Retiré el pedido · salgo a entregar</button>`
    : o.accion === 'entregar' ? `<button class="btn btn-primary block" data-est="entregado" data-id="${o.id}">✓ Confirmar entrega</button>`
    : `<p class="muted center">Esperando que cocina empiece a prepararlo…</p>`;
  return `<article class="ocard rep-card ${o.isNew ? 'new' : ''}">
    <h3><span>#${o.id} · ${esc(o.cliente)}</span><span class="pill st-${o.estado}">${esc(o.estado_txt)}</span></h3>
    <p class="meta">${o.hace !== null ? 'Asignado hace ' + o.hace + ' min · ' : ''}Tiempo prometido: ${o.minutos} min</p>
    <div class="cobro ${o.cobro.c}">${esc(o.cobro.t)}</div>
    <p class="dir"><b>Dirección:</b> ${esc(o.direccion || 'Sin dirección escrita: usa el mapa')}${o.zona ? '<br><span class="muted">Zona: ' + esc(o.zona) + '</span>' : ''}</p>
    ${o.notas ? `<p class="meta"><b>Nota del cliente:</b> ${esc(o.notas)}</p>` : ''}
    <ul>${items}</ul>
    <div class="acts">
      ${o.mapa ? `<a class="btn btn-small btn-primary" href="${esc(o.mapa)}" target="_blank" rel="noopener">📍 Cómo llegar</a>` : ''}
      <a class="btn btn-small btn-wa" href="https://wa.me/${o.wa}?text=${o.wa_msg}" target="_blank" rel="noopener">WhatsApp cliente</a>
      <a class="btn btn-small" href="tel:${esc(o.tel)}">Llamar</a>
      <a class="btn btn-small" href="${esc(WA_LOCAL)}${WA_LOCAL.includes('?') ? '&' : '?'}text=${o.wa_local}" target="_blank" rel="noopener">Avisar al local</a>
    </div>${accion}</article>`;
}
async function cargar(force) {
  try {
    const d = await (await fetch('api.php', {cache: 'no-store'})).json();
    if (d.error) { document.getElementById('estadoConexion').textContent = d.error; return; }
    document.getElementById('estadoConexion').textContent = '';
    WA_LOCAL = d.wa_local;
    sEnt.textContent = d.hoy.entregados; sEf.textContent = d.hoy.efectivo; sEnv.textContent = d.hoy.envios;
    const ids = d.orders.map(o => +o.id);
    if (known !== null) {
      const nuevos = d.orders.filter(o => !known.includes(+o.id));
      nuevos.forEach(o => o.isNew = true);
      if (nuevos.length) { beep(); nuevos.forEach(notificar); }
    }
    known = ids;
    document.getElementById('lista').innerHTML = d.orders.map(card).join('');
    document.getElementById('vacio').hidden = d.orders.length > 0;
  } catch (e) { document.getElementById('estadoConexion').textContent = 'Sin conexión… reintentando.'; }
}
document.getElementById('lista').addEventListener('click', async e => {
  const b = e.target.closest('button[data-est]'); if (!b) return;
  if (b.dataset.est === 'entregado' && !confirm('¿Confirmas que entregaste el pedido #' + b.dataset.id + '?')) return;
  b.disabled = true;
  const r = await fetch('api.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({csrf: CSRF, id: +b.dataset.id, estado: b.dataset.est})});
  if (!r.ok) { const d = await r.json().catch(() => ({})); alert(d.error || 'No se pudo actualizar.'); }
  cargar(true);
});
cargar(); setInterval(cargar, 6000);
</script>
<?php rep_foot();
