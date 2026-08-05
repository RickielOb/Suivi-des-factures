<?php
require_once __DIR__ . '/lib/auth.php';
require_login();

$u  = user();
$id = (int) $u['abonne_id'];
$abonne = $id ? db_one('SELECT * FROM abonne WHERE id=?', [$id]) : null;

$factures = $abonne ? db_all(
    'SELECT f.*, r.mois, r.consommation, r.date_releve,
            (SELECT COALESCE(SUM(p.montant),0) FROM paiement p WHERE p.facture_id=f.id) paye
     FROM facture f JOIN releve r ON r.id=f.releve_id
     WHERE f.abonne_id=? ORDER BY r.mois DESC', [$id]) : [];

$total = 0; $paye = 0; $maxc = 1;
foreach ($factures as $f) {
    $total += (float) $f['montant'];
    $paye  += (float) $f['paye'];
    $maxc   = max($maxc, (float) $f['consommation']);
}

$titre = 'Mon espace';
$soustitre = $abonne ? 'Compteur ' . $abonne['numero_compteur'] : '';
include __DIR__ . '/views/header.php';
?>

<?php if (!$abonne): ?>
  <div class="alert alert-info">Votre compte n’est relié à aucun abonnement. Contactez l’agence.</div>
<?php else: ?>

<div class="stats">
  <div class="stat"><div class="icon"><?= $abonne['type_abonnement'] === 'eau' ? '💧' : '⚡' ?></div>
    <div><div class="label">Abonnement</div><div class="value" style="font-size:17px"><?= $abonne['type_abonnement'] === 'eau' ? 'Eau' : 'Électricité' ?></div></div></div>
  <div class="stat"><div class="icon">🧾</div>
    <div><div class="label">Total facturé</div><div class="value"><?= money($total) ?></div></div></div>
  <div class="stat green"><div class="icon">💰</div>
    <div><div class="label">Réglé</div><div class="value"><?= money($paye) ?></div></div></div>
  <div class="stat <?= ($total - $paye) > 0 ? 'red' : 'green' ?>"><div class="icon">⚠️</div>
    <div><div class="label">Solde dû</div><div class="value"><?= money(max(0, $total - $paye)) ?></div></div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Mes factures et ma consommation</h2></div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr><th>Mois</th><th class="num">Consommation</th><th style="width:170px"></th>
        <th class="num">Montant</th><th class="num">Payé</th><th>Statut</th></tr></thead>
      <tbody>
      <?php if (!$factures): ?>
        <tr><td colspan="6" class="empty">Aucune facture pour l’instant.</td></tr>
      <?php else: foreach ($factures as $f): ?>
        <tr>
          <td><b><?= e(mois_label($f['mois'])) ?></b><div class="text-muted small">Relevé du <?= date_fr($f['date_releve']) ?></div></td>
          <td class="num"><?= rtrim(rtrim(number_format($f['consommation'], 2, ',', ' '), '0'), ',') ?> <?= unite($abonne['type_abonnement']) ?></td>
          <td><div class="bar"><span style="width:<?= round($f['consommation'] / $maxc * 100) ?>%"></span></div></td>
          <td class="num"><?= money($f['montant']) ?></td>
          <td class="num"><?= money($f['paye']) ?></td>
          <td><?= badge_statut($f['statut']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/views/footer.php'; ?>
