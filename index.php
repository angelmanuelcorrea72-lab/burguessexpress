<?php
require_once __DIR__ . '/includes/layout.php';
[$abierto, $textoEstado] = estadoLocal();
$cats = db()->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY orden, id")->fetchAll();
$prods = db()->query("SELECT * FROM productos WHERE disponible = 1 ORDER BY nombre")->fetchAll();
$porCat = []; $data = [];
foreach ($prods as $p) $porCat[$p['categoria_id']][] = $p;

// Opciones por producto
$grupos = db()->query("SELECT * FROM grupos_opciones ORDER BY orden, id")->fetchAll();
$ops = db()->query("SELECT * FROM opciones WHERE activa = 1 ORDER BY id")->fetchAll();
$opsPorGrupo = [];
foreach ($ops as $o) $opsPorGrupo[$o['grupo_id']][] = ['id' => (int)$o['id'], 'n' => $o['nombre'], 'x' => (int)$o['extra']];
$gruposPorProd = [];
foreach ($grupos as $g) {
    if (empty($opsPorGrupo[$g['id']])) continue;
    $gruposPorProd[$g['producto_id']][] = ['id' => (int)$g['id'], 'n' => $g['nombre'], 'multi' => $g['tipo'] === 'multiple', 'req' => (bool)$g['obligatorio'], 'ops' => $opsPorGrupo[$g['id']]];
}
foreach ($prods as $p) {
    $data[$p['id']] = ['id' => (int)$p['id'], 'n' => $p['nombre'], 'd' => $p['descripcion'], 'det' => $p['detalle'], 'ing' => $p['ingredientes'],
        'p' => (int)$p['precio'], 'promo' => $p['precio_promo'] ? (int)$p['precio_promo'] : null,
        'img' => $p['imagen'] ? 'uploads/productos/' . $p['imagen'] : null, 'g' => $gruposPorProd[$p['id']] ?? []];
}
$zonas = db()->query("SELECT id, nombre, costo, minutos_extra FROM zonas WHERE activa = 1 ORDER BY costo")->fetchAll();
$horarios = db()->query("SELECT * FROM horarios ORDER BY dia")->fetchAll();
$u = user();
head_html('Menú'); ?>
<header class="top">
  <div class="top-row">
    <div>
      <h1 class="brand"><?= e(cfg('nombre_local')) ?></h1>
      <button class="status <?= $abierto ? 'on' : 'off' ?>" id="btnHorario" type="button"><i></i><?= e($textoEstado) ?></button>
    </div>
    <nav class="user-nav">
      <?php if ($u): ?>
        <a href="mis_pedidos.php">Mis pedidos</a>
        <?php if (in_array($u['rol'], ['admin', 'empleado'])): ?><a href="admin/index.php">Panel</a><?php endif; ?>
        <?php if ($u['rol'] === 'repartidor'): ?><a href="repartidor/index.php">Mi reparto</a><?php endif; ?>
        <a href="logout.php">Salir</a>
      <?php else: ?>
        <a href="login.php">Ingresar</a><a class="btn btn-small btn-primary" href="registro.php">Crear cuenta</a>
      <?php endif; ?>
    </nav>
  </div>
  <?php if (!$abierto): ?>
    <p class="banner"><?= e(cfg('mensaje_cierre')) ?> Puedes mirar el menú, pero los pedidos se habilitan cuando abramos.</p>
  <?php endif; ?>
  <div class="chips" id="chips">
    <?php foreach ($cats as $c): if (empty($porCat[$c['id']])) continue; ?>
      <a href="#cat<?= $c['id'] ?>"><?= e($c['nombre']) ?></a>
    <?php endforeach; ?>
  </div>
</header>

<main class="menu">
  <?php foreach ($cats as $c): if (empty($porCat[$c['id']])) continue; ?>
    <section id="cat<?= $c['id'] ?>">
      <h2><?= e($c['nombre']) ?></h2>
      <div class="grid">
        <?php foreach ($porCat[$c['id']] as $p): $d = $data[$p['id']]; ?>
          <article class="prod" data-detail="<?= $p['id'] ?>" tabindex="0" role="button" aria-label="Ver detalle de <?= e($p['nombre']) ?>">
            <?php if ($p['imagen']): ?>
              <img src="uploads/productos/<?= e($p['imagen']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="noimg"><?= e(mb_strtoupper(mb_substr($p['nombre'], 0, 1))) ?></div>
            <?php endif; ?>
            <div class="prod-body">
              <h3><?= e($p['nombre']) ?><?php if ($d['promo']): ?> <span class="badge">Oferta</span><?php endif; ?></h3>
              <p><?= e($p['descripcion']) ?></p>
              <div class="prod-foot">
                <span class="price"><?php if ($d['promo']): ?><s><?= money($p['precio']) ?></s> <?php endif; ?><strong><?= money($d['promo'] ?: $p['precio']) ?></strong></span>
                <button class="btn btn-small btn-primary add" type="button" data-id="<?= $p['id'] ?>"><?= $d['g'] ? 'Personalizar' : 'Agregar' ?></button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <?php if (!$prods): ?><p class="muted center">Todavía no hay productos cargados.</p><?php endif; ?>
</main>

<button class="cartbar" id="cartbar" type="button" hidden>
  <span><b id="cartCount">0</b> productos</span><span>Ver mi pedido · <b id="cartTotal"></b></span>
</button>

<!-- Detalle de producto -->
<dialog id="dlgProd" class="sheet">
  <div id="pdImg"></div>
  <h2 id="pdNombre"></h2>
  <p class="price big" id="pdPrecio"></p>
  <p id="pdDetalle"></p>
  <p class="muted" id="pdIng" hidden></p>
  <div id="pdGrupos"></div>
  <p class="alert" id="pdErr" hidden></p>
  <div class="row pd-foot">
    <span class="qty"><button type="button" id="pdMenos" aria-label="Quitar uno">−</button><b id="pdQ">1</b><button type="button" id="pdMas" aria-label="Agregar uno">+</button></span>
    <button class="btn btn-primary" type="button" id="pdAdd" style="flex:2"></button>
  </div>
  <button class="btn block" type="button" data-close>Cerrar</button>
</dialog>

<!-- Horarios -->
<dialog id="dlgHorario" class="sheet">
  <h2>Horarios de atención</h2>
  <table class="hours">
    <?php foreach ($horarios as $h): ?>
      <tr class="<?= (int)$h['dia'] === (int)date('w') ? 'today' : '' ?>">
        <td><?= DIAS[$h['dia']] ?></td>
        <td><?= $h['cerrado'] ? 'Cerrado' : substr($h['abre'], 0, 5) . ' a ' . substr($h['cierra'], 0, 5) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted"><?= e(cfg('direccion')) ?></p>
  <div class="row"><a class="btn btn-wa" href="<?= e(wa_link('Hola, quiero hacer una consulta')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
  <button class="btn" data-close>Cerrar</button></div>
</dialog>

<!-- Pedido -->
<dialog id="dlgCart" class="sheet">
  <form id="orderForm" autocomplete="off">
    <h2>Mi pedido</h2>
    <div id="cartItems"></div>

    <fieldset>
      <legend>¿Cómo lo quieres?</legend>
      <div class="seg">
        <label><input type="radio" name="tipo" value="retiro" checked><span>Retiro en local<small><?= (int)cfg('tiempo_retiro') ?> min</small></span></label>
        <label><input type="radio" name="tipo" value="delivery"><span>Delivery<small>a domicilio</small></span></label>
      </div>
    </fieldset>

    <fieldset id="boxDelivery" hidden>
      <legend>Dónde te lo llevamos</legend>
      <?php if ($zonas): ?>
      <label>Zona de entrega<select name="zona_id" id="zonaSel">
        <option value="">Elige tu zona…</option>
        <?php foreach ($zonas as $z): ?><option value="<?= $z['id'] ?>"><?= e($z['nombre']) ?> — <?= money($z['costo']) ?></option><?php endforeach; ?>
      </select></label>
      <?php endif; ?>
      <label>Dirección y referencias<textarea name="direccion" rows="2" placeholder="Calle, número, color de casa, referencia"></textarea></label>
      <button type="button" class="btn" id="btnGeo" style="margin-top:.6rem">Compartir mi ubicación actual</button>
      <p class="muted" id="geoMsg"></p>
    </fieldset>

    <fieldset>
      <legend>Forma de pago</legend>
      <div class="seg <?= bancard_activo() ? 'four' : 'three' ?>">
        <label><input type="radio" name="pago" value="efectivo" checked><span>Efectivo</span></label>
        <label><input type="radio" name="pago" value="transferencia"><span>Transferencia</span></label>
        <label><input type="radio" name="pago" value="tarjeta"><span>Tarjeta al recibir</span></label>
        <?php if (bancard_activo()): ?><label><input type="radio" name="pago" value="bancard"><span>Pagar en línea<small>Bancard</small></span></label><?php endif; ?>
      </div>
    </fieldset>

    <div class="coupon">
      <label>Cupón de descuento
        <span class="coupon-row"><input name="cupon" id="cupon" placeholder="Código" autocapitalize="characters" style="margin:0"><button type="button" class="btn" id="btnCupon">Aplicar</button></span>
      </label>
      <p class="muted" id="cuponMsg"></p>
    </div>

    <label>Notas para la cocina (opcional)<input name="notas" maxlength="200" placeholder="Sin cebolla, con más salsa…"></label>

    <div class="totals">
      <div><span>Subtotal</span><b id="tSub"></b></div>
      <div id="rowEnvio" hidden><span>Delivery</span><b id="tEnvio"></b></div>
      <div id="rowDesc" hidden><span>Descuento</span><b id="tDesc"></b></div>
      <div class="grand"><span>Total</span><b id="tTotal"></b></div>
    </div>
    <p class="alert" id="orderErr" hidden></p>
    <div class="row">
      <button class="btn" type="button" data-close>Seguir viendo</button>
      <button class="btn btn-primary" type="submit" id="btnSend">Confirmar pedido</button>
    </div>
  </form>
</dialog>

<script>
window.APP = {
  logged: <?= $u ? 'true' : 'false' ?>, open: <?= $abierto ? 'true' : 'false' ?>,
  envio: <?= (int)cfg('costo_delivery', '0') ?>, csrf: <?= json_encode(csrf()) ?>, moneda: <?= json_encode(cfg('moneda', 'Gs.')) ?>,
  zonas: <?= json_encode(array_map(fn($z) => ['id' => (int)$z['id'], 'costo' => (int)$z['costo']], $zonas)) ?>,
  prods: <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="assets/js/app.js"></script>
<?php foot_html();
