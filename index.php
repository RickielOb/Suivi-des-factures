<?php
require_once __DIR__ . '/lib/auth.php';
require_login();

$nb_abonnes = (int) db_value('SELECT COUNT(*) FROM abonne', [], 0);
$nb_factures = (int) db_value('SELECT COUNT(*) FROM facture', [], 0);
$total_facture = (float) db_value('SELECT COALESCE(SUM(montant),0) FROM facture', [], 0);
$total_encaisse = (float) db_value('SELECT COALESCE(SUM(montant),0) FROM paiement', [], 0);
$impayes = $total_facture - $total_encaisse;

$par_statut = [];
foreach (db_all('SELECT statut, COUNT(*) n FROM facture GROUP BY statut') as $r) {
    $par_statut[$r['statut']] = (int) $r['n'];
}

$dernieres = db_all(
    'SELECT f.*, a.nom, a.type_abonnement, r.mois
     FROM facture f
     JOIN abonne a ON a.id = f.abonne_id
     JOIN releve r ON r.id = f.releve_id
     ORDER BY f.id DESC LIMIT 8'
);

$top_impayes = db_all(
    "SELECT a.id, a.nom, a.numero_compteur,
            COALESCE(SUM(f.montant),0) - COALESCE((SELECT SUM(p.montant) FROM paiement p
                JOIN facture f2 ON f2.id = p.facture_id WHERE f2.abonne_id = a.id),0) AS reste
     FROM abonne a
     LEFT JOIN facture f ON f.abonne_id = a.id
     GROUP BY a.id
     HAVING reste > 0
     ORDER BY reste DESC LIMIT 5"
);

$titre = 'Tableau de bord';
$soustitre = 'Vue d’ensemble de la facturation';
include __DIR__ . '/views/header.php';
?>

<div class="stats">
  <div class="stat">
    <div class="icon">👥</div>
    <div><div class="label">Abonnés</div><div class="value"><?= $nb_abonnes ?></div></div>
  </div>
  <div class="stat">
    <div class="icon">🧾</div>
    <div><div class="label">Factures émises</div><div class="value"><?= $nb_factures ?></div></div>
  </div>
  <div class="stat green">
    <div class="icon">💰</div>
    <div><div class="label">Encaissé</div><div class="value"><?= money($total_encaisse) ?></div></div>
  </div>
  <div class="stat red">
    <div class="icon">⚠️</div>
    <div><div class="label">Reste à recouvrer</div><div class="value"><?= money(max(0, $impayes)) ?></div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>État des factures</h2>
    <span class="text-muted small">Total facturé : <?= money($total_facture) ?></span>
  </div>
  <div class="card-body">
    <div class="stats" style="margin:0">
      <div class="stat green"><div class="icon">✅</div>
        <div><div class="label">Payées</div><div class="value"><?= $par_statut['payee'] ?? 0 ?></div></div></div>
      <div class="stat amber"><div class="icon">◐</div>
        <div><div class="label">Partielles</div><div class="value"><?= $par_statut['partielle'] ?? 0 ?></div></div></div>
      <div class="stat red"><div class="icon">✖</div>
        <div><div class="label">Impayées</div><div class="value"><?= $par_statut['impayee'] ?? 0 ?></div></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Dernières factures</h2>
    <a class="btn btn-light btn-sm" href="factures.php">Tout voir</a>
  </div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr>
        <th>N°</th><th>Abonné</th><th>Type</th><th>Mois</th><th>Émise le</th>
        <th class="num">Montant</th><th>Statut</th>
      </tr></thead>
      <tbody>
      <?php if (!$dernieres): ?>
        <tr><td colspan="7" class="empty">Aucune facture pour le moment.</td></tr>
      <?php else: foreach ($dernieres as $f): ?>
        <tr>
          <td>#<?= (int) $f['id'] ?></td>
          <td><?= e($f['nom']) ?></td>
          <td><span class="badge badge-info"><?= $f['type_abonnement'] === 'eau' ? 'Eau' : 'Électricité' ?></span></td>
          <td><?= e(mois_label($f['mois'])) ?></td>
          <td><?= date_fr($f['date_emission']) ?></td>
          <td class="num"><?= money($f['montant']) ?></td>
          <td><?= badge_statut($f['statut']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Principaux impayés</h2></div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr><th>Abonné</th><th>Compteur</th><th class="num">Reste à payer</th><th></th></tr></thead>
      <tbody>
      <?php if (!$top_impayes): ?>
        <tr><td colspan="4" class="empty">Aucun impayé. 🎉</td></tr>
      <?php else: foreach ($top_impayes as $t): ?>
        <tr>
          <td><?= e($t['nom']) ?></td>
          <td class="text-muted"><?= e($t['numero_compteur']) ?></td>
          <td class="num" style="color:var(--red);font-weight:600"><?= money($t['reste']) ?></td>
          <td class="num"><a class="btn btn-light btn-sm" href="abonnes.php?voir=<?= (int) $t['id'] ?>">Détail</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
