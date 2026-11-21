<?php
require_once __DIR__ . '/../includes/layout.php';
function adm_head(string $titulo, string $active): void {
    head_html($titulo, '../');
    $links = ['index' => ['index.php', 'Pedidos'], 'productos' => ['productos.php', 'Productos'], 'categorias' => ['categorias.php', 'Categorías']];
    if (user()['rol'] === 'admin') {
        $links['cupones'] = ['cupones.php', 'Cupones'];
        $links['zonas'] = ['zonas.php', 'Delivery'];
        $links['reportes'] = ['reportes.php', 'Reportes'];
        $links['ajustes'] = ['ajustes.php', 'Ajustes'];
    }
    ?>
<header class="adm-top"><div class="adm-top-in">
  <b><?= e(cfg('nombre_local')) ?></b>
  <?php foreach ($links as $k => [$href, $lbl]): ?><a class="<?= $k === $active ? 'on' : '' ?>" href="<?= $href ?>"><?= $lbl ?></a><?php endforeach; ?>
  <a href="../index.php" target="_blank">Ver tienda</a><a href="../logout.php">Salir</a>
</div></header>
<main class="adm">
<?php }
function adm_foot(): void { echo "</main></body></html>"; }
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

function reportes_tabs(string $activo): void {
    $t = ['resumen' => ['reportes.php', 'Resumen de ventas'], 'pedidos' => ['reporte_pedidos.php', 'Detalle de pedidos'], 'repartidores' => ['reporte_repartidores.php', 'Repartidores']];
    echo '<div class="tabs" style="margin-top:0">';
    foreach ($t as $k => [$h, $l]) echo '<a class="btn btn-small ' . ($k === $activo ? 'btn-primary' : '') . '" href="' . $h . '">' . $l . '</a>';
    echo '</div>';
}
