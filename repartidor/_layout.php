<?php
require_once __DIR__ . '/../includes/layout.php';
function rep_head(string $titulo, string $activo, array $rep): void {
    head_html($titulo, '../'); ?>
<header class="adm-top"><div class="adm-top-in">
  <b>🛵 <?= e($rep['nombre']) ?></b>
  <a class="<?= $activo === 'pedidos' ? 'on' : '' ?>" href="index.php">Mis pedidos</a>
  <a class="<?= $activo === 'historial' ? 'on' : '' ?>" href="historial.php">Mi historial</a>
  <a href="../logout.php">Salir</a>
</div></header>
<main class="rep">
<?php }
function rep_foot(): void { echo '</main></body></html>'; }
