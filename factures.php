<?php
require_once __DIR__ . '/lib/auth.php';
require_gestion();

$erreur = null;

// --- Enregistrement d'un paiement depuis la fiche facture ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fid     = (int) ($_POST['facture_id'] ?? 0);
    $montant = (float) str_replace(',', '.', $_POST['montant'] ?? '0');
    $facture = db_one('SELECT * FROM facture WHERE id=?', [$fid]);
    $paye    = (float) db_value('SELECT COALESCE(SUM(montant),0) FROM paiement WHERE facture_id=?', [$fid], 0);

    if (!$facture)                                   $erreur = 'Facture introuvable.';
    elseif ($montant <= 0)                           $erreur = 'Le montant doit être supérieur à zéro.';
    elseif ($montant > (float) $facture['montant'] - $paye + 0.001)
                                                     $erreur = 'Le montant dépasse le reste à payer (' . money($facture['montant'] - $paye) . ').';
    else {
        db_exec('INSERT INTO paiement (facture_id, montant, date, mode) VALUES (?,?,?,?)',
            [$fid, $montant, $_POST['date'] ?: date('Y-m-d'), $_POST['mode'] ?? 'especes']);
        maj_statut_facture($fid);
        flash('Paiement de ' . money($montant) . ' enregistré.');
        redirect('factures.php?voir=' . $fid);
    }
}

$voir = null;
if (isset($_GET['voir'])) {
    $voir = db_one('SELECT f.*, a.nom, a.telephone, a.adresse, a.numero_compteur, a.type_abonnement,
                           r.mois, r.consommation, r.date_releve
                    FROM facture f JOIN abonne a ON a.id=f.abonne_id JOIN releve r ON r.id=f.releve_id
                    WHERE f.id=?', [(int) $_GET['voir']]);
}

// --- Recherche ---
$q      = trim($_GET['q'] ?? '');
$mois_f = trim($_GET['mois'] ?? '');
$statut = $_GET['statut'] ?? '';

$sql = 'SELECT f.*, a.nom, a.numero_compteur, a.type_abonnement, r.mois, r.consommation,
               (SELECT COALESCE(SUM(p.montant),0) FROM paiement p WHERE p.facture_id=f.id) paye
        FROM facture f JOIN abonne a ON a.id=f.abonne_id JOIN releve r ON r.id=f.releve_id WHERE 1=1';
$params = [];
if ($q !== '')      { $sql .= ' AND (a.nom LIKE ? OR a.numero_compteur LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($mois_f !== '') { $sql .= ' AND r.mois = ?'; $params[] = $mois_f; }
if ($statut !== '') { $sql .= ' AND f.statut = ?'; $params[] = $statut; }
$sql .= ' ORDER BY f.id DESC';
$factures = db_all($sql, $params);

$total = 0; $encaisse = 0;
foreach ($factures as $f) { $total += (float) $f['montant']; $encaisse += (float) $f['paye']; }

$titre = 'Factures';
$soustitre = count($factures) . ' facture(s) · ' . money($total) . ' facturés · ' . money($total - $encaisse) . ' restant dû';
include __DIR__ . '/views/header.php';
?>

<?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

<?php if ($voir):
    $paiements = db_all('SELECT * FROM paiement WHERE facture_id=? ORDER BY date, id', [(int) $voir['id']]);
    $paye = 0; foreach ($paiements as $p) { $paye += (float) $p['montant']; }
    $reste = (float) $voir['montant'] - $paye;
?>
<div class="card">
  <div class="card-head">
    <h2>Facture n° <?= str_pad($voir['id'], 5, '0', STR_PAD_LEFT) ?> — <?= e(mois_label($voir['mois'])) ?></h2>
    <div style="display:flex;gap:8px;align-items:center">
      <?= badge_statut($voir['statut']) ?>
      <a class="btn btn-light btn-sm" href="factures.php">Fermer</a>
    </div>
  </div>
  <div class="card-body">
    <div class="form-row" style="margin-bottom:18px">
      <div><div class="label text-muted small">Abonné</div><b><?= e($voir['nom']) ?></b>
           <div class="small text-muted"><?= e($voir['adresse']) ?></div></div>
      <div><div class="label text-muted small">Compteur</div><b><?= e($voir['numero_compteur']) ?></b>
           <div class="small text-muted"><?= $voir['type_abonnement'] === 'eau' ? 'Eau' : 'Électricité' ?></div></div>
      <div><div class="label text-muted small">Consommation</div>
           <b><?= rtrim(rtrim(number_format($voir['consommation'], 2, ',', ' '), '0'), ',') ?> <?= unite($voir['type_abonnement']) ?></b>
           <div class="small text-muted">× <?= tarif($voir['type_abonnement']) ?> <?= DEVISE ?></div></div>
      <div><div class="label text-muted small">Émise le</div><b><?= date_fr($voir['date_emission']) ?></b></div>
    </div>

    <div class="stats" style="margin-bottom:18px">
      <div class="stat"><div class="icon">🧾</div><div><div class="label">Montant</div><div class="value"><?= money($voir['montant']) ?></div></div></div>
      <div class="stat green"><div class="icon">💰</div><div><div class="label">Déjà payé</div><div class="value"><?= money($paye) ?></div></div></div>
      <div class="stat <?= $reste > 0 ? 'red' : 'green' ?>"><div class="icon"><?= $reste > 0 ? '⚠️' : '✅' ?></div>
        <div><div class="label">Reste à payer</div><div class="value"><?= money(max(0, $reste)) ?></div></div></div>
    </div>

    <h2>Paiements enregistrés</h2>
    <div class="table-wrap" style="margin-bottom:18px">
      <table class="table">
        <thead><tr><th>Date</th><th>Mode</th><th class="num">Montant</th></tr></thead>
        <tbody>
        <?php if (!$paiements): ?>
          <tr><td colspan="3" class="empty">Aucun paiement pour cette facture.</td></tr>
        <?php else: foreach ($paiements as $p): ?>
          <tr><td><?= date_fr($p['date']) ?></td>
              <td><?= e(ucfirst(str_replace('_', ' ', $p['mode']))) ?></td>
              <td class="num"><?= money($p['montant']) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($reste > 0): ?>
    <h2>Encaisser un paiement</h2>
    <form method="post">
      <input type="hidden" name="facture_id" value="<?= (int) $voir['id'] ?>">
      <div class="form-row">
        <div class="field"><label>Montant (<?= DEVISE ?>) *</label>
          <input type="number" step="1" min="1" max="<?= (int) ceil($reste) ?>" name="montant" required value="<?= (int) round($reste) ?>"></div>
        <div class="field"><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label>Mode de paiement</label>
          <select name="mode">
            <option value="especes">Espèces</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="virement">Virement</option>
            <option value="cheque">Chèque</option>
          </select></div>
      </div>
      <div class="form-actions"><button class="btn btn-success" type="submit">Valider le paiement</button></div>
    </form>
    <?php else: ?>
      <div class="alert alert-success">Cette facture est entièrement réglée.</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Toutes les factures</h2>
    <form class="filters" method="get">
      <div class="field"><input name="q" placeholder="Abonné ou compteur…" value="<?= e($q) ?>"></div>
      <div class="field"><input type="month" name="mois" value="<?= e($mois_f) ?>"></div>
      <div class="field"><select name="statut">
        <option value="">Tous les statuts</option>
        <option value="impayee"   <?= $statut === 'impayee'   ? 'selected' : '' ?>>Impayée</option>
        <option value="partielle" <?= $statut === 'partielle' ? 'selected' : '' ?>>Partielle</option>
        <option value="payee"     <?= $statut === 'payee'     ? 'selected' : '' ?>>Payée</option>
      </select></div>
      <button class="btn btn-light btn-sm" type="submit">Filtrer</button>
      <a class="btn btn-light btn-sm" href="factures.php">Réinitialiser</a>
    </form>
  </div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr>
        <th>N°</th><th>Abonné</th><th>Mois</th><th class="num">Consommation</th>
        <th class="num">Montant</th><th class="num">Payé</th><th class="num">Reste</th><th>Statut</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (!$factures): ?>
        <tr><td colspan="9" class="empty">Aucune facture trouvée.</td></tr>
      <?php else: foreach ($factures as $f):
        $reste = (float) $f['montant'] - (float) $f['paye']; ?>
        <tr>
          <td>#<?= str_pad($f['id'], 5, '0', STR_PAD_LEFT) ?></td>
          <td><b><?= e($f['nom']) ?></b><div class="text-muted small"><?= e($f['numero_compteur']) ?></div></td>
          <td><?= e(mois_label($f['mois'])) ?></td>
          <td class="num"><?= rtrim(rtrim(number_format($f['consommation'], 2, ',', ' '), '0'), ',') ?> <?= unite($f['type_abonnement']) ?></td>
          <td class="num"><?= money($f['montant']) ?></td>
          <td class="num"><?= money($f['paye']) ?></td>
          <td class="num" style="<?= $reste > 0 ? 'color:var(--red);font-weight:600' : 'color:var(--green)' ?>"><?= money(max(0, $reste)) ?></td>
          <td><?= badge_statut($f['statut']) ?></td>
          <td class="num"><a class="btn btn-light btn-sm" href="factures.php?voir=<?= (int) $f['id'] ?>">Ouvrir</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
