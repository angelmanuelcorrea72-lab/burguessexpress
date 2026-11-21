<?php
require_once __DIR__ . '/_layout.php';
require_staff();
adm_head('Pedidos', 'index'); ?>
<div class="stats">
  <div class="stat"><span class="muted">Pedidos de hoy</span><b id="sHoy">–</b></div>
  <div class="stat"><span class="muted">Ventas de hoy</span><b id="sVentas">–</b></div>
  <div class="stat"><span class="muted">Esperando respuesta</span><b id="sPend">–</b></div>
</div>
<div class="tabs">
  <button class="btn btn-small btn-primary" data-v="activos">Activos</button>
  <button class="btn btn-small" data-v="terminados">Terminados hoy</button>
  <button class="btn btn-small" id="btnSound">Activar sonido de aviso</button>
  <label class="btn btn-small" style="margin:0;cursor:pointer"><input type="checkbox" id="autoPrint"> Imprimir tickets nuevos automáticamente</label>
</div>
<div class="board" id="board"></div>
<p class="muted center" id="empty" hidden>No hay pedidos en esta lista.</p>
<script>
const CSRF = <?= json_encode(csrf()) ?>;
let vista = 'activos', maxId = null, sound = false, ctx = null, REPS = [];
const autoP = document.getElementById('autoPrint');
try { autoP.checked = localStorage.getItem('autoprint') === '1'; } catch (e) {}
autoP.onchange = () => { try { localStorage.setItem('autoprint', autoP.checked ? '1' : '0'); } catch (e) {} };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt = n => Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

function beep() {
  if (!sound || !ctx) return;
  [880, 660, 880].forEach((f, i) => {
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(.2, ctx.currentTime + i * .22); g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + i * .22 + .2);
    o.start(ctx.currentTime + i * .22); o.stop(ctx.currentTime + i * .22 + .2);
  });
}
document.getElementById('btnSound').onclick = e => {
  ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); sound = !sound;
  e.target.textContent = sound ? 'Sonido activado ✓' : 'Activar sonido de aviso'; if (sound) beep();
};
document.querySelectorAll('[data-v]').forEach(b => b.onclick = () => {
  vista = b.dataset.v; maxId = null;
  document.querySelectorAll('[data-v]').forEach(x => x.classList.toggle('btn-primary', x === b));
  load();
});

function actions(o) {
  const b = (est, txt, cls = '') => `<button class="btn btn-small ${cls}" data-act="estado" data-id="${o.id}" data-est="${est}">${txt}</button>`;
  let h = '';
  if (o.estado === 'pendiente') h += b('preparando', 'Aceptar y preparar', 'btn-primary');
  if (o.estado === 'preparando') h += o.tipo === 'delivery' ? b('en_camino', 'Salió a entregar', 'btn-primary') : b('listo', 'Listo para retirar', 'btn-primary');
  if (['listo', 'en_camino'].includes(o.estado)) h += b('entregado', 'Marcar entregado', 'btn-primary');
  if (!['entregado', 'cancelado'].includes(o.estado)) h += b('cancelado', 'Cancelar', 'btn-danger');
  return h;
}
const b2 = (a, id, t) => `<button class="btn btn-small" data-act="${a}" data-id="${id}">${t}</button>`;
function card(o) {
  const mapa = o.lat ? `<a href="https://www.google.com/maps?q=${o.lat},${o.lng}" target="_blank" rel="noopener">Abrir ubicación en mapa</a>` : '';
  const live = !['entregado', 'cancelado'].includes(o.estado);
  return `<article class="ocard ${o.isNew ? 'new' : ''}">
    <h3><span>#${o.id} · ${esc(o.cliente)}</span><span class="pill st-${o.estado}">${esc(o.estado_txt)}</span></h3>
    <p class="meta">${o.creado.slice(11, 16)} · ${o.tipo === 'delivery' ? 'Delivery' : 'Retiro'} · Pago: ${esc(o.pago)}${o.rep_nombre ? '<br>Repartidor: <b>' + esc(o.rep_nombre) + '</b>' : ''}<br>
      Tel: <a href="https://wa.me/${o.wa}" target="_blank" rel="noopener">${esc(o.telefono)}</a></p>
    <ul>${o.items.map(i => `<li>${i.q} × ${esc(i.n)}${i.o ? `<br><small class="muted">${esc(i.o)}</small>` : ''}</li>`).join('')}</ul>
    ${o.notas ? `<p class="meta">Nota: ${esc(o.notas)}</p>` : ''}
    ${o.zona ? `<p class="meta">Zona: ${esc(o.zona)}</p>` : ''}${o.direccion ? `<p class="meta">${esc(o.direccion)}</p>` : ''}${mapa}
    ${+o.descuento > 0 ? `<p class="meta">Cupón ${esc(o.cupon)}: −${fmt(o.descuento)}</p>` : ''}
    <p><b>Total: ${esc(<?= json_encode(cfg('moneda', 'Gs.')) ?>)} ${fmt(o.total)}</b>${o.pago === 'bancard' ? ' · pagado en línea ✓' : o.pago === 'transferencia' ? (o.pago_estado === 'pagado' ? ' · transferencia confirmada ✓' : ' · transferencia por confirmar') : ''}</p>
    ${o.tipo === 'delivery' && live ? `<div class="mins"><span>Repartidor</span><select data-rep="${o.id}" style="margin:0;padding:.4rem">
      <option value="">Sin asignar</option>${REPS.map(r => `<option value="${r.id}" ${+o.repartidor_id === +r.id ? 'selected' : ''}>${esc(r.nombre)}</option>`).join('')}</select></div>` : ''}
    ${live ? `<div class="mins"><span>Tiempo estimado</span><input type="number" min="1" max="240" value="${o.minutos}" id="m${o.id}"><span>min</span>
      <button class="btn btn-small" data-act="minutos" data-id="${o.id}">Guardar</button></div>` : ''}
    <div class="acts">${actions(o)}
      <a class="btn btn-small" href="ticket.php?id=${o.id}" target="_blank">Imprimir ticket</a>
      <a class="btn btn-small btn-wa" href="https://wa.me/${o.wa}?text=${o.wa_msg}" target="_blank" rel="noopener">Avisar al cliente</a>
      ${o.wa_rep ? `<a class="btn btn-small btn-wa" href="${esc(o.wa_rep)}" target="_blank" rel="noopener">Avisar al repartidor</a>` : ''}
      <a class="btn btn-small" href="pedido.php?id=${o.id}">Ver ficha</a>
      ${o.pago === 'transferencia' && o.pago_estado !== 'pagado' ? b2('pagado', o.id, 'Confirmar transferencia') : ''}</div></article>`;
}
async function load(force) {
  try {
    const d = await (await fetch('api.php?vista=' + vista, {cache: 'no-store'})).json();
    if (d.error) return;
    const top = d.orders.length ? Math.max(...d.orders.map(o => +o.id)) : 0;
    REPS = d.reps || [];
    if (maxId !== null && top > maxId && vista === 'activos') {
      d.orders.forEach(o => o.isNew = +o.id > maxId); beep();
      if (autoP.checked) d.orders.filter(o => o.isNew).forEach(o => { const f = document.createElement('iframe'); f.style.display = 'none'; f.src = 'ticket.php?id=' + o.id + '&auto=1'; document.body.appendChild(f); setTimeout(() => f.remove(), 20000); });
    }
    maxId = Math.max(maxId ?? 0, top);
    const ae = document.activeElement, board = document.getElementById('board');
    if (!force && ae && board.contains(ae) && ['SELECT', 'INPUT'].includes(ae.tagName)) { sHoy.textContent = d.stats.hoy; return; }
    board.innerHTML = d.orders.map(card).join('');
    document.getElementById('empty').hidden = d.orders.length > 0;
    sHoy.textContent = d.stats.hoy; sVentas.textContent = d.stats.ventas; sPend.textContent = d.stats.pendientes;
  } catch (e) {}
}
document.getElementById('board').addEventListener('change', async e => {
  const sel = e.target.closest('select[data-rep]'); if (!sel) return;
  sel.disabled = true;
  const r = await fetch('api.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({csrf: CSRF, action: 'repartidor', id: +sel.dataset.rep, repartidor_id: +sel.value || 0})});
  if (!r.ok) alert('No se pudo asignar el repartidor. Recarga la página e intenta de nuevo.');
  load(true);
});
document.getElementById('board').addEventListener('click', async e => {
  if (e.target.closest('select, input')) return;
  const b = e.target.closest('[data-act]'); if (!b) return;
  const id = +b.dataset.id, body = {csrf: CSRF, id, action: b.dataset.act};
  if (b.dataset.act === 'pagado') { /* confirmar pago */ }
  else if (b.dataset.act === 'estado') {
    if (b.dataset.est === 'cancelado' && !confirm('¿Cancelar el pedido #' + id + '?')) return;
    body.estado = b.dataset.est;
  } else if (b.dataset.act === 'minutos') body.minutos = +document.getElementById('m' + id).value;
  b.disabled = true;
  await fetch('api.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
  load();
});
load(); setInterval(load, 8000);
</script>
<?php adm_foot();
