<?php
require_once __DIR__ . '/lib/auth.php';
require_gestion();

$erreur = null;

// --- Nouveau paiement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fid     = (int) ($_POST['facture_id'] ?? 0);
    $montant = (float) str_replace(',', '.', $_POST['montant'] ?? '0');
    $facture = db_one('SELECT * FROM facture WHERE id=?', [$fid]);
    $paye    = (float) db_value('SELECT COALESCE(SUM(montant),0) FROM paiement WHERE facture_id=?', [$fid], 0);

    if (!$facture)         $erreur = 'Sélectionnez une facture.';
    elseif ($montant <= 0) $erreur = 'Le montant doit être supérieur à zéro.';
    elseif ($montant > (float) $facture['montant'] - $paye + 0.001)
                           $erreur = 'Le montant dépasse le reste à payer (' . money($facture['montant'] - $paye) . ').';
    else {
        db_exec('INSERT INTO paiement (facture_id, montant, date, mode) VALUES (?,?,?,?)',
            [$fid, $montant, $_POST['date'] ?: date('Y-m-d'), $_POST['mode'] ?? 'especes']);
        maj_statut_facture($fid);
        flash('Paiement de ' . money($montant) . ' enregistré.');
        redirect('paiements.php');
    }
}

if (isset($_GET['supprimer']) && is_admin()) {
    $id = (int) $_GET['supprimer'];
    $p  = db_one('SELECT facture_id FROM paiement WHERE id=?', [$id]);
    if ($p) {
        db_exec('DELETE FROM paiement WHERE id=?', [$id]);
        maj_statut_facture((int) $p['facture_id']);
        flash('Paiement annulé.');
    }
    redirect('paiements.php');
}

// Factures encore ouvertes, pour le formulaire
$ouvertes = db_all(
    "SELECT f.id, f.montant, a.nom, a.numero_compteur, r.mois,
            (SELECT COALESCE(SUM(p.montant),0) FROM paiement p WHERE p.facture_id=f.id) paye
     FROM facture f JOIN abonne a ON a.id=f.abonne_id JOIN releve r ON r.id=f.releve_id
     WHERE f.statut <> 'payee' ORDER BY r.mois, a.nom"
);

// --- Recherche ---
$q    = trim($_GET['q'] ?? '');
$mode = $_GET['mode'] ?? '';
$sql  = 'SELECT p.*, f.montant montant_facture, a.nom, a.numero_compteur, r.mois
         FROM paiement p JOIN facture f ON f.id=p.facture_id
         JOIN abonne a ON a.id=f.abonne_id JOIN releve r ON r.id=f.releve_id WHERE 1=1';
$params = [];
if ($q !== '')    { $sql .= ' AND (a.nom LIKE ? OR a.numero_compteur LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($mode !== '') { $sql .= ' AND p.mode = ?'; $params[] = $mode; }
$sql .= ' ORDER BY p.date DESC, p.id DESC';
$paiements = db_all($sql, $params);

$total = 0; foreach ($paiements as $p) { $total += (float) $p['montant']; }

$titre = 'Paiements';
$soustitre = count($paiements) . ' paiement(s) · ' . money($total) . ' encaissés';
include __DIR__ . '/views/header.php';
?>

<?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

<div class="card">
  <div class="card-head"><h2>Encaisser un paiement</h2>
    <span class="text-muted small"><?= count($ouvertes) ?> facture(s) non soldée(s)</span></div>
  <div class="card-body">
    <?php if (!$ouvertes): ?>
      <div class="alert alert-success">Toutes les factures sont réglées.</div>
    <?php else: ?>
    <form method="post">
      <div class="form-row">
        <div class="field"><label>Facture *</label>
          <select name="facture_id" required>
            <option value="">— Choisir —</option>
            <?php foreach ($ouvertes as $o): $reste = (float) $o['montant'] - (float) $o['paye']; ?>
              <option value="<?= (int) $o['id'] ?>">
                #<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?> — <?= e($o['nom']) ?> — <?= e(mois_label($o['mois'])) ?> — reste <?= money($reste) ?>
              </option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Montant (<?= DEVISE ?>) *</label>
          <input type="number" step="1" min="1" name="montant" required></div>
        <div class="field"><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label>Mode</label>
          <select name="mode">
            <option value="especes">Espèces</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="virement">Virement</option>
            <option value="cheque">Chèque</option>
          </select></div>
      </div>
      <div class="form-actions"><button class="btn btn-success" type="submit">Enregistrer le paiement</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Journal des paiements</h2>
    <form class="filters" method="get">
      <div class="field"><input name="q" placeholder="Abonné ou compteur…" value="<?= e($q) ?>"></div>
      <div class="field"><select name="mode">
        <option value="">Tous les modes</option>
        <?php foreach (['especes' => 'Espèces', 'mobile_money' => 'Mobile Money', 'virement' => 'Virement', 'cheque' => 'Chèque'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $mode === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select></div>
      <button class="btn btn-light btn-sm" type="submit">Filtrer</button>
      <a class="btn btn-light btn-sm" href="paiements.php">Réinitialiser</a>
    </form>
  </div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr><th>Date</th><th>Abonné</th><th>Facture</th><th>Mois</th><th>Mode</th><th class="num">Montant</th><th></th></tr></thead>
      <tbody>
      <?php if (!$paiements): ?>
        <tr><td colspan="7" class="empty">Aucun paiement trouvé.</td></tr>
      <?php else: foreach ($paiements as $p): ?>
        <tr>
          <td><?= date_fr($p['date']) ?></td>
          <td><b><?= e($p['nom']) ?></b><div class="text-muted small"><?= e($p['numero_compteur']) ?></div></td>
          <td><a href="factures.php?voir=<?= (int) $p['facture_id'] ?>">#<?= str_pad($p['facture_id'], 5, '0', STR_PAD_LEFT) ?></a></td>
          <td><?= e(mois_label($p['mois'])) ?></td>
          <td><span class="badge badge-secondary"><?= e(ucfirst(str_replace('_', ' ', $p['mode']))) ?></span></td>
          <td class="num"><?= money($p['montant']) ?></td>
          <td class="num">
            <?php if (is_admin()): ?>
              <a class="btn btn-danger btn-sm" href="paiements.php?supprimer=<?= (int) $p['id'] ?>"
                 onclick="return confirm('Annuler ce paiement ?')">Annuler</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
