<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$page    = basename($_SERVER['PHP_SELF']);
$titre   = isset($titre) ? $titre : APP_NAME;
$soustitre = isset($soustitre) ? $soustitre : '';
$u       = user();
$f       = flash();

$nav = [];
if (peut_gerer()) {
    $nav = [
        'index.php'     => ['📊', 'Tableau de bord'],
        'abonnes.php'   => ['👥', 'Abonnés'],
        'releves.php'   => ['📈', 'Relevés'],
        'factures.php'  => ['🧾', 'Factures'],
        'paiements.php' => ['💳', 'Paiements'],
    ];
} else {
    $nav = ['mon-espace.php' => ['🏠', 'Mon espace']];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-mark">⚡</span>
      <span><?= APP_NAME ?><small>Eau &amp; Électricité</small></span>
    </div>
    <nav class="menu">
      <div class="menu-title">Navigation</div>
      <?php foreach ($nav as $url => $it): ?>
        <a href="<?= $url ?>" class="<?= $page === $url ? 'active' : '' ?>">
          <span class="ico"><?= $it[0] ?></span><?= $it[1] ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="who"><?= e($u['nom']) ?></div>
      <div class="role"><?= e($u['role']) ?></div>
      <a href="logout.php">Se déconnecter</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div>
        <h1><?= e($titre) ?></h1>
        <?php if ($soustitre): ?><div class="text-muted small"><?= e($soustitre) ?></div><?php endif; ?>
      </div>
      <div class="text-muted small"><?= date('d/m/Y') ?></div>
    </header>
    <main class="content">
      <?php if ($f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endif; ?>
