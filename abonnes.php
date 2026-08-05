<?php
require_once __DIR__ . '/lib/auth.php';
require_gestion();

$erreur = null;

// --- Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int) ($_POST['id'] ?? 0);
    $data = [
        trim($_POST['nom'] ?? ''),
        trim($_POST['telephone'] ?? ''),
        trim($_POST['adresse'] ?? ''),
        trim($_POST['numero_compteur'] ?? ''),
        $_POST['type_abonnement'] ?? 'eau',
    ];
    if ($data[0] === '' || $data[3] === '') {
        $erreur = 'Le nom et le numéro de compteur sont obligatoires.';
    } else {
        try {
            if ($id) {
                db_exec('UPDATE abonne SET nom=?, telephone=?, adresse=?, numero_compteur=?, type_abonnement=? WHERE id=?',
                    array_merge($data, [$id]));
                flash('Abonné mis à jour.');
            } else {
                db_exec('INSERT INTO abonne (nom, telephone, adresse, numero_compteur, type_abonnement) VALUES (?,?,?,?,?)', $data);
                flash('Abonné enregistré.');
            }
            redirect('abonnes.php');
        } catch (Exception $ex) {
            $erreur = 'Enregistrement impossible (numéro de compteur déjà utilisé ?).';
        }
    }
}

if (isset($_GET['supprimer'])) {
    $id = (int) $_GET['supprimer'];
    $n  = (int) db_value('SELECT COUNT(*) FROM releve WHERE abonne_id=?', [$id], 0);
    if ($n > 0) {
        flash('Suppression impossible : cet abonné possède des relevés.', 'danger');
    } else {
        db_exec('DELETE FROM abonne WHERE id=?', [$id]);
        flash('Abonné supprimé.');
    }
    redirect('abonnes.php');
}

$edit = isset($_GET['modifier']) ? db_one('SELECT * FROM abonne WHERE id=?', [(int) $_GET['modifier']]) : null;
$voir = isset($_GET['voir'])     ? db_one('SELECT * FROM abonne WHERE id=?', [(int) $_GET['voir']])     : null;

// --- Recherche ---
$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';

$sql    = 'SELECT a.*,
             (SELECT COUNT(*) FROM releve r WHERE r.abonne_id=a.id) nb_releves,
             (SELECT COALESCE(SUM(f.montant),0) FROM facture f WHERE f.abonne_id=a.id) total,
             (SELECT COALESCE(SUM(p.montant),0) FROM paiement p JOIN facture f2 ON f2.id=p.facture_id WHERE f2.abonne_id=a.id) paye
           FROM abonne a WHERE 1=1';
$params = [];
if ($q !== '')    { $sql .= ' AND (a.nom LIKE ? OR a.numero_compteur LIKE ? OR a.telephone LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($type !== '') { $sql .= ' AND a.type_abonnement = ?'; $params[] = $type; }
$sql .= ' ORDER BY a.nom';
$abonnes = db_all($sql, $params);

$titre = 'Abonnés';
$soustitre = count($abonnes) . ' abonné(s) affiché(s)';
include __DIR__ . '/views/header.php';
?>

<?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

<?php if ($voir):
    $histo = db_all('SELECT r.*, f.id fid, f.montant, f.statut FROM releve r
                     LEFT JOIN facture f ON f.releve_id = r.id
                     WHERE r.abonne_id=? ORDER BY r.mois DESC', [(int) $voir['id']]);
    $max = 1;
    foreach ($histo as $h) { $max = max($max, (float) $h['consommation']); }
?>
<div class="card">
  <div class="card-head">
    <h2>Fiche abonné — <?= e($voir['nom']) ?></h2>
    <a class="btn btn-light btn-sm" href="abonnes.php">Fermer</a>
  </div>
  <div class="card-body">
    <div class="form-row" style="margin-bottom:18px">
      <div><div class="label text-muted small">Compteur</div><b><?= e($voir['numero_compteur']) ?></b></div>
      <div><div class="label text-muted small">Type</div><b><?= $voir['type_abonnement'] === 'eau' ? 'Eau' : 'Électricité' ?></b></div>
      <div><div class="label text-muted small">Téléphone</div><b><?= e($voir['telephone'] ?: '—') ?></b></div>
      <div><div class="label text-muted small">Adresse</div><b><?= e($voir['adresse'] ?: '—') ?></b></div>
    </div>
    <h2>Historique de consommation</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Mois</th><th>Relevé le</th><th class="num">Consommation</th><th style="width:190px"></th><th class="num">Facture</th><th>Statut</th></tr></thead>
        <tbody>
        <?php if (!$histo): ?>
          <tr><td colspan="6" class="empty">Aucun relevé enregistré.</td></tr>
        <?php else: foreach ($histo as $h): ?>
          <tr>
            <td><?= e(mois_label($h['mois'])) ?></td>
            <td><?= date_fr($h['date_releve']) ?></td>
            <td class="num"><?= rtrim(rtrim(number_format($h['consommation'], 2, ',', ' '), '0'), ',') ?> <?= unite($voir['type_abonnement']) ?></td>
            <td><div class="bar"><span style="width:<?= round($h['consommation'] / $max * 100) ?>%"></span></div></td>
            <td class="num"><?= $h['montant'] !== null ? money($h['montant']) : '—' ?></td>
            <td><?= $h['statut'] ? badge_statut($h['statut']) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2><?= $edit ? 'Modifier l’abonné' : 'Nouvel abonné' ?></h2>
    <?php if ($edit): ?><a class="btn btn-light btn-sm" href="abonnes.php">Annuler</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : '' ?>">
      <div class="form-row">
        <div class="field"><label>Nom complet *</label>
          <input name="nom" required value="<?= e($edit['nom'] ?? '') ?>"></div>
        <div class="field"><label>Téléphone</label>
          <input name="telephone" value="<?= e($edit['telephone'] ?? '') ?>"></div>
        <div class="field"><label>N° de compteur *</label>
          <input name="numero_compteur" required value="<?= e($edit['numero_compteur'] ?? '') ?>"></div>
        <div class="field"><label>Type d’abonnement</label>
          <select name="type_abonnement">
            <option value="eau" <?= (($edit['type_abonnement'] ?? '') === 'eau') ? 'selected' : '' ?>>Eau</option>
            <option value="electricite" <?= (($edit['type_abonnement'] ?? '') === 'electricite') ? 'selected' : '' ?>>Électricité</option>
          </select></div>
        <div class="field"><label>Adresse</label>
          <input name="adresse" value="<?= e($edit['adresse'] ?? '') ?>"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter l’abonné' ?></button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Liste des abonnés</h2>
    <form class="filters" method="get">
      <div class="field"><input name="q" placeholder="Nom, compteur, téléphone…" value="<?= e($q) ?>"></div>
      <div class="field"><select name="type">
        <option value="">Tous les types</option>
        <option value="eau" <?= $type === 'eau' ? 'selected' : '' ?>>Eau</option>
        <option value="electricite" <?= $type === 'electricite' ? 'selected' : '' ?>>Électricité</option>
      </select></div>
      <button class="btn btn-light btn-sm" type="submit">Rechercher</button>
    </form>
  </div>
  <div class="card-body tight table-wrap">
    <table class="table">
      <thead><tr>
        <th>Nom</th><th>Compteur</th><th>Type</th><th>Téléphone</th>
        <th class="num">Relevés</th><th class="num">Facturé</th><th class="num">Solde</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (!$abonnes): ?>
        <tr><td colspan="8" class="empty">Aucun abonné trouvé.</td></tr>
      <?php else: foreach ($abonnes as $a):
        $solde = (float) $a['total'] - (float) $a['paye']; ?>
        <tr>
          <td><b><?= e($a['nom']) ?></b><div class="text-muted small"><?= e($a['adresse']) ?></div></td>
          <td class="text-muted"><?= e($a['numero_compteur']) ?></td>
          <td><span class="badge badge-info"><?= $a['type_abonnement'] === 'eau' ? 'Eau' : 'Électricité' ?></span></td>
          <td><?= e($a['telephone'] ?: '—') ?></td>
          <td class="num"><?= (int) $a['nb_releves'] ?></td>
          <td class="num"><?= money($a['total']) ?></td>
          <td class="num" style="<?= $solde > 0 ? 'color:var(--red);font-weight:600' : 'color:var(--green)' ?>"><?= money(max(0, $solde)) ?></td>
          <td class="num" style="white-space:nowrap">
            <a class="btn btn-light btn-sm" href="abonnes.php?voir=<?= (int) $a['id'] ?>">Historique</a>
            <a class="btn btn-light btn-sm" href="abonnes.php?modifier=<?= (int) $a['id'] ?>">Modifier</a>
            <?php if (is_admin()): ?>
            <a class="btn btn-danger btn-sm" href="abonnes.php?supprimer=<?= (int) $a['id'] ?>"
               onclick="return confirm('Supprimer cet abonné ?')">Suppr.</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
