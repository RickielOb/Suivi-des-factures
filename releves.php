<?php
require_once __DIR__ . '/lib/auth.php';
require_gestion();

$erreur = null;

// --- Enregistrement d'un relevé + génération automatique de la facture ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $abonne_id = (int) ($_POST['abonne_id'] ?? 0);
    $mois      = trim($_POST['mois'] ?? '');
    $conso     = (float) str_replace(',', '.', $_POST['consommation'] ?? '0');
    $date      = $_POST['date_releve'] ?: date('Y-m-d');

    $abonne = $abonne_id ? db_one('SELECT * FROM abonne WHERE id=?', [$abonne_id]) : null;

    if (!$abonne)                       $erreur = 'Sélectionnez un abonné.';
    elseif (!preg_match('/^\d{4}-\d{2}$/', $mois)) $erreur = 'Le mois doit être au format AAAA-MM.';
    elseif ($conso <= 0)                $erreur = 'La consommation doit être supérieure à zéro.';
    elseif (db_one('SELECT id FROM releve WHERE abonne_id=? AND mois=?', [$abonne_id, $mois]))
                                        $erreur = 'Un relevé existe déjà pour cet abonné sur ce mois.';
    else {
        $rid = db_exec('INSERT INTO releve (abonne_id, mois, consommation, date_releve) VALUES (?,?,?,?)',
            [$abonne_id, $mois, $conso, $date]);
        $montant = $conso * tarif($abonne['type_abonnement']);
        db_exec('INSERT INTO facture (abonne_id, releve_id, montant, date_emission, statut) VALUES (?,?,?,?,?)',
            [$abonne_id, $rid, $montant, $date, 'impayee']);
        flash('Relevé enregistré et facture de ' . money($montant) . ' générée automatiquement.');
        redirect('releves.php');
    }
}

if (isset($_GET['supprimer']) && is_admin()) {
    $id = (int) $_GET['supprimer'];
    $f  = db_one('SELECT id FROM facture WHERE releve_id=?', [$id]);
    if ($f && (int) db_value('SELECT COUNT(*) FROM paiement WHERE facture_id=?', [(int) $f['id']], 0) > 0) {
        flash('Suppression impossible : des paiements sont liés à la facture de ce relevé.', 'danger');
    } else {
        if ($f) db_exec('DELETE FROM facture WHERE id=?', [(int) $f['id']]);
        db_exec('DELETE FROM releve WHERE id=?', [$id]);
        flash('Relevé et facture associée supprimés.');
    }
    redirect('releves.php');
}

$abonnes = db_all('SELECT id, nom, numero_compteur, type_abonnement FROM abonne ORDER BY nom');

// --- Recherche ---
$q     = trim($_GET['q'] ?? '');
$mois_f = trim($_GET['mois'] ?? '');
$sql = 'SELECT r.*, a.nom, a.numero_compteur, a.type_abonnement, f.montant, f.statut
        FROM releve r JOIN abonne a ON a.id = r.abonne_id
        LEFT JOIN facture f ON f.releve_id = r.id WHERE 1=1';
$params = [];
if ($q !== '')      { $sql .= ' AND (a.nom LIKE ? OR a.numero_compteur LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($mois_f !== '') { $sql .= ' AND r.mois = ?'; $params[] = $mois_f; }
$sql .= ' ORDER BY r.mois DESC, a.nom';
$releves = db_all($sql, $params);

$titre = 'Relevés de compteur';
$soustitre = count($releves) . ' relevé(s) — la facture est générée automatiquement à l’enregistrement';
include __DIR__ . '/views/header.php';
?>

<?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Nouveau relevé</h2>
    <span class="text-muted small">Tarifs : eau <?= TARIF_EAU ?> <?= DEVISE ?>/m³ · électricité <?= TARIF_ELECTRICITE ?> <?= DEVISE ?>/kWh</span>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="form-row">
        <div class="field"><label>Abonné *</label>
          <select name="abonne_id" required>
            <option value="">— Choisir —</option>
            <?php foreach ($abonnes as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= ((int) ($_POST['abonne_id'] ?? 0) === (int) $a['id']) ? 'selected' : '' ?>>
                <?= e($a['nom']) ?> (<?= e($a['numero_compteur']) ?> — <?= $a['type_abonnement'] === 'eau' ? 'Eau' : 'Élec.' ?>)
              </option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Mois (AAAA-MM) *</label>
          <input type="month" name="mois" required value="<?= e($_POST['mois'] ?? date('Y-m')) ?>"></div>
        <div class="field"><label>Consommation (m³ ou kWh) *</label>
          <input type="number" step="0.01" min="0.01" name="consommation" required value="<?= e($_POST['consommation'] ?? '') ?>"></div>
        <div class="field"><label>Date du relevé</label>
          <input type="date" name="date_releve" value="<?= e($_POST['date_releve'] ?? date('Y-m-d')) ?>"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Enregistrer et facturer</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Historique des relevés</h2>
    <form class="filters" method="get">
      <div class="field"><input name="q" placeholder="Abonné ou compteur…" value="<?= e($q) ?>"></div>
      <div class="field"><input type="month" name="mois" value="<?= e($mois_f) ?>"></div>
      <button class="btn btn-light btn-sm" type="submit">Filtrer</button>
      <a class="btn btn-light btn-sm" href="releves.php">Réinitialiser</a>
    </form>
  </div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr>
        <th>Mois</th><th>Abonné</th><th>Compteur</th><th>Type</th>
        <th class="num">Consommation</th><th>Relevé le</th><th class="num">Facture</th><th>Statut</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (!$releves): ?>
        <tr><td colspan="9" class="empty">Aucun relevé trouvé.</td></tr>
      <?php else: foreach ($releves as $r): ?>
        <tr>
          <td><b><?= e(mois_label($r['mois'])) ?></b></td>
          <td><?= e($r['nom']) ?></td>
          <td class="text-muted"><?= e($r['numero_compteur']) ?></td>
          <td><span class="badge badge-info"><?= $r['type_abonnement'] === 'eau' ? 'Eau' : 'Électricité' ?></span></td>
          <td class="num"><?= rtrim(rtrim(number_format($r['consommation'], 2, ',', ' '), '0'), ',') ?> <?= unite($r['type_abonnement']) ?></td>
          <td><?= date_fr($r['date_releve']) ?></td>
          <td class="num"><?= $r['montant'] !== null ? money($r['montant']) : '—' ?></td>
          <td><?= $r['statut'] ? badge_statut($r['statut']) : '—' ?></td>
          <td class="num">
            <?php if (is_admin()): ?>
            <a class="btn btn-danger btn-sm" href="releves.php?supprimer=<?= (int) $r['id'] ?>"
               onclick="return confirm('Supprimer ce relevé et sa facture ?')">Suppr.</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
