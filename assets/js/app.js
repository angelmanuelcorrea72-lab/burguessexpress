(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const fmt = n => APP.moneda + ' ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  let cart = {};
  try { cart = JSON.parse(localStorage.getItem('cart2') || '{}'); } catch (e) {}
  const save = () => { try { localStorage.setItem('cart2', JSON.stringify(cart)); } catch (e) {} };
  const sub = () => Object.values(cart).reduce((s, i) => s + i.precio * i.q, 0);
  const count = () => Object.values(cart).reduce((s, i) => s + i.q, 0);
  const tipo = () => $('input[name=tipo]:checked').value;
  const unit = p => p.promo || p.p;
  const dlgCart = $('#dlgCart'), dlgH = $('#dlgHorario'), dlgP = $('#dlgProd'), bar = $('#cartbar');
  let cuponOk = null;   // {codigo, descuento}

  const zonaCosto = () => {
    if (tipo() !== 'delivery') return 0;
    if (!APP.zonas.length) return APP.envio;
    const z = APP.zonas.find(z => z.id === +($('#zonaSel').value || 0));
    return z ? z.costo : 0;
  };
  const payload = () => {
    const f = $('#orderForm');
    return {
      csrf: APP.csrf, tipo: tipo(), pago: f.pago.value, notas: f.notas.value, direccion: f.direccion.value,
      zona_id: $('#zonaSel') ? +$('#zonaSel').value || null : null, cupon: cuponOk ? cuponOk.codigo : '',
      lat: pos && pos.lat, lng: pos && pos.lng,
      items: Object.values(cart).map(i => ({id: i.id, q: i.q, op: i.op}))
    };
  };

  function render() {
    const n = count();
    bar.hidden = n === 0;
    $('#cartCount').textContent = n;
    $('#cartTotal').textContent = fmt(sub());
    const list = $('#cartItems');
    list.innerHTML = '';
    Object.entries(cart).forEach(([k, i]) => {
      const row = document.createElement('div');
      row.className = 'line';
      row.dataset.k = k;
      row.innerHTML = '<span class="nm"><span class="n1"></span><small class="muted"></small></span><span class="qty"><button type="button" data-d="-1" aria-label="Quitar uno">−</button><b>' + i.q +
        '</b><button type="button" data-d="1" aria-label="Agregar uno">+</button></span><b>' + fmt(i.precio * i.q) + '</b>';
      row.querySelector('.n1').textContent = i.nombre;
      row.querySelector('small').textContent = i.ops || '';
      list.appendChild(row);
    });
    const env = zonaCosto(), desc = cuponOk ? cuponOk.descuento : 0;
    $('#boxDelivery').hidden = tipo() !== 'delivery';
    $('#rowEnvio').hidden = tipo() !== 'delivery';
    $('#rowDesc').hidden = desc === 0;
    $('#tSub').textContent = fmt(sub());
    $('#tEnvio').textContent = tipo() === 'delivery' && !env && APP.zonas.length ? 'Elige tu zona' : fmt(env);
    $('#tDesc').textContent = '−' + fmt(desc);
    $('#tTotal').textContent = fmt(Math.max(0, sub() + env - desc));
    if (n === 0 && dlgCart.open) dlgCart.close();
  }

  /* ---------- Cupón ---------- */
  async function checkCupon(codigo, quiet) {
    const msg = $('#cuponMsg');
    if (!codigo) { cuponOk = null; msg.textContent = ''; render(); return; }
    if (!APP.logged) { msg.textContent = 'Ingresa a tu cuenta para usar cupones.'; return; }
    const body = payload(); body.cupon = codigo;
    try {
      const r = await fetch('api/cupon.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
      const d = await r.json();
      if (!r.ok) { cuponOk = null; msg.textContent = d.error || 'No se pudo validar el cupón.'; msg.className = 'alert'; }
      else if (d.descuento > 0) { cuponOk = {codigo, descuento: d.descuento}; msg.textContent = d.msg; msg.className = 'muted ok-text'; }
      else { cuponOk = null; msg.textContent = 'Ese cupón no aplica a este pedido.'; msg.className = 'alert'; }
    } catch (e) { msg.textContent = 'Sin conexión para validar el cupón.'; }
    render();
  }
  $('#btnCupon').addEventListener('click', () => checkCupon($('#cupon').value.trim().toUpperCase()));
  const recheck = () => { if (cuponOk) checkCupon(cuponOk.codigo, true); };

  /* ---------- Detalle de producto ---------- */
  let cur = null, curQ = 1;
  function pdTotal() {
    let x = 0;
    dlgP.querySelectorAll('input:checked').forEach(i => x += +i.dataset.x);
    return (unit(cur) + x) * curQ;
  }
  function pdRefresh() { $('#pdQ').textContent = curQ; $('#pdAdd').textContent = 'Agregar al pedido · ' + fmt(pdTotal()); }
  function openProd(id) {
    cur = APP.prods[id]; curQ = 1;
    $('#pdImg').innerHTML = cur.img ? '<img src="' + cur.img + '" alt="">' : '<div class="noimg big">' + cur.n.charAt(0).toUpperCase() + '</div>';
    $('#pdNombre').textContent = cur.n;
    $('#pdPrecio').innerHTML = (cur.promo ? '<s>' + fmt(cur.p) + '</s> ' : '') + '<strong>' + fmt(unit(cur)) + '</strong>' + (cur.promo ? ' <span class="badge">Oferta</span>' : '');
    $('#pdDetalle').textContent = cur.det || cur.d || '';
    const ing = $('#pdIng'); ing.hidden = !cur.ing; ing.textContent = cur.ing ? 'Ingredientes: ' + cur.ing : '';
    const box = $('#pdGrupos'); box.innerHTML = '';
    cur.g.forEach(g => {
      const fs = document.createElement('fieldset');
      fs.innerHTML = '<legend></legend>';
      fs.querySelector('legend').textContent = g.n + (g.req ? ' (obligatorio)' : g.multi ? ' (opcional)' : '');
      g.ops.forEach(o => {
        const l = document.createElement('label'); l.className = 'opt';
        l.innerHTML = '<input type="' + (g.multi ? 'checkbox' : 'radio') + '" name="g' + g.id + '" value="' + o.id + '" data-x="' + o.x + '"><span></span><em></em>';
        l.querySelector('span').textContent = o.n;
        l.querySelector('em').textContent = o.x ? '+ ' + fmt(o.x) : '';
        fs.appendChild(l);
      });
      box.appendChild(fs);
    });
    $('#pdErr').hidden = true; pdRefresh(); dlgP.showModal(); dlgP.scrollTop = 0;
  }
  dlgP.addEventListener('change', pdRefresh);
  $('#pdMenos').onclick = () => { curQ = Math.max(1, curQ - 1); pdRefresh(); };
  $('#pdMas').onclick = () => { curQ = Math.min(50, curQ + 1); pdRefresh(); };
  $('#pdAdd').onclick = () => {
    const err = $('#pdErr'); err.hidden = true;
    for (const g of cur.g) {
      if (g.req && !dlgP.querySelector('input[name=g' + g.id + ']:checked')) { err.textContent = 'Elige una opción en «' + g.n + '».'; err.hidden = false; return; }
    }
    const op = [], names = [];
    cur.g.forEach(g => {
      const sel = [...dlgP.querySelectorAll('input[name=g' + g.id + ']:checked')];
      if (sel.length) names.push(g.n + ': ' + sel.map(s => g.ops.find(o => o.id === +s.value).n).join(', '));
      sel.forEach(s => op.push(+s.value));
    });
    op.sort((a, b) => a - b);
    addToCart(cur, op, names.join(' | '), curQ, pdTotal() / curQ);
    dlgP.close();
  };
  function addToCart(p, op, ops, q, precio) {
    const k = p.id + '|' + op.join(',');
    cart[k] = cart[k] || {id: p.id, nombre: p.n, precio, op, ops, q: 0};
    cart[k].q = Math.min(50, cart[k].q + q);
    save(); render(); recheck();
  }

  /* ---------- Eventos globales ---------- */
  document.addEventListener('click', e => {
    const add = e.target.closest('.add');
    if (add) {
      e.stopPropagation();
      const p = APP.prods[add.dataset.id];
      if (p.g.length) openProd(p.id);
      else { addToCart(p, [], '', 1, unit(p)); add.textContent = 'Agregado ✓'; setTimeout(() => add.textContent = 'Agregar', 700); }
      return;
    }
    const card = e.target.closest('.prod');
    if (card) { openProd(card.dataset.detail); return; }
    const d = e.target.closest('#cartItems .qty button');
    if (d) {
      const k = d.closest('.line').dataset.k;
      cart[k].q += +d.dataset.d;
      if (cart[k].q <= 0) delete cart[k];
      save(); render(); recheck();
      return;
    }
    if (e.target.closest('[data-close]')) e.target.closest('dialog').close();
    if (e.target.tagName === 'DIALOG') e.target.close();
  });
  document.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('prod')) { e.preventDefault(); openProd(e.target.dataset.detail); }
  });

  bar.addEventListener('click', () => { render(); dlgCart.showModal(); });
  $('#btnHorario').addEventListener('click', () => dlgH.showModal());
  document.querySelectorAll('input[name=tipo]').forEach(r => r.addEventListener('change', () => { render(); recheck(); }));
  if ($('#zonaSel')) $('#zonaSel').addEventListener('change', () => { render(); recheck(); });

  /* ---------- Ubicación ---------- */
  let pos = null;
  $('#btnGeo').addEventListener('click', () => {
    const msg = $('#geoMsg');
    if (!navigator.geolocation) { msg.textContent = 'Tu navegador no permite compartir ubicación. Escribe la dirección.'; return; }
    msg.textContent = 'Buscando tu ubicación…';
    navigator.geolocation.getCurrentPosition(p => {
      pos = {lat: p.coords.latitude, lng: p.coords.longitude};
      msg.textContent = 'Ubicación compartida ✓ (precisión ~' + Math.round(p.coords.accuracy) + ' m)';
    }, () => { msg.textContent = 'No pudimos obtener la ubicación. Revisa el permiso o escribe la dirección.'; },
    {enableHighAccuracy: true, timeout: 12000});
  });

  /* ---------- Enviar pedido ---------- */
  $('#orderForm').addEventListener('submit', async e => {
    e.preventDefault();
    const err = $('#orderErr'), btn = $('#btnSend');
    err.hidden = true;
    if (!APP.logged) { location.href = 'login.php'; return; }
    if (!APP.open) { err.textContent = 'El local está cerrado en este momento.'; err.hidden = false; return; }
    btn.disabled = true; btn.textContent = 'Enviando…';
    try {
      const r = await fetch('api/pedido.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload())});
      const d = await r.json();
      if (d.login) { location.href = 'login.php'; return; }
      if (!r.ok) throw new Error(d.error || 'Error al enviar');
      cart = {}; save();
      location.href = d.pagar ? 'pagar.php?id=' + d.id : 'pedido.php?id=' + d.id;
    } catch (ex) {
      err.textContent = ex.message; err.hidden = false;
      btn.disabled = false; btn.textContent = 'Confirmar pedido';
    }
  });

  const chips = [...document.querySelectorAll('#chips a')];
  const io = new IntersectionObserver(es => es.forEach(en => {
    if (en.isIntersecting) chips.forEach(c => c.classList.toggle('active', c.hash === '#' + en.target.id));
  }), {rootMargin: '-40% 0px -55% 0px'});
  document.querySelectorAll('.menu section').forEach(s => io.observe(s));

  // limpiar carrito de versiones anteriores
  try { localStorage.removeItem('cart'); } catch (e) {}
  render();
})();
