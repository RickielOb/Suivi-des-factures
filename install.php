<?php
/**
 * Création des tables sur Turso + jeu de données de démonstration.
 * À lancer une seule fois : http://localhost:8000/install.php
 */

require_once __DIR__ . '/lib/helpers.php';

$log    = [];
$erreur = null;

try {
    db_script([
        "CREATE TABLE IF NOT EXISTS abonne (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nom TEXT NOT NULL,
            telephone TEXT,
            adresse TEXT,
            numero_compteur TEXT NOT NULL UNIQUE,
            type_abonnement TEXT NOT NULL CHECK (type_abonnement IN ('eau','electricite')),
            cree_le TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS releve (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            abonne_id INTEGER NOT NULL REFERENCES abonne(id),
            mois TEXT NOT NULL,
            consommation REAL NOT NULL,
            date_releve TEXT NOT NULL,
            UNIQUE (abonne_id, mois)
        )",
        "CREATE TABLE IF NOT EXISTS facture (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            abonne_id INTEGER NOT NULL REFERENCES abonne(id),
            releve_id INTEGER NOT NULL REFERENCES releve(id),
            montant REAL NOT NULL,
            date_emission TEXT NOT NULL,
            statut TEXT NOT NULL DEFAULT 'impayee'
        )",
        "CREATE TABLE IF NOT EXISTS paiement (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            facture_id INTEGER NOT NULL REFERENCES facture(id),
            montant REAL NOT NULL,
            date TEXT NOT NULL,
            mode TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS utilisateur (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nom TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            mot_de_passe TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('admin','agent','abonne')),
            abonne_id INTEGER
        )",
    ]);
    $log[] = 'Tables créées (abonne, releve, facture, paiement, utilisateur).';

    // --- Comptes par défaut ---
    $comptes = [
        ['Administrateur', 'admin@aquawatt.ga', 'admin123', 'admin'],
        ['Agent de facturation', 'agent@aquawatt.ga', 'agent123', 'agent'],
    ];
    foreach ($comptes as $c) {
        if (!db_one('SELECT id FROM utilisateur WHERE email = ?', [$c[1]])) {
            db_exec(
                'INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?,?,?,?)',
                [$c[0], $c[1], password_hash($c[2], PASSWORD_DEFAULT), $c[3]]
            );
            $log[] = "Compte créé : {$c[1]} / {$c[2]}";
        }
    }

    // --- Données de démonstration ---
    $nb = (int) db_value('SELECT COUNT(*) FROM abonne', [], 0);
    if ($nb === 0) {
        $demo = [
            ['Nzeng Obiang Paul', '074 12 34 56', 'Quartier Louis, Libreville', 'CPT-EAU-1001', 'eau'],
            ['Mba Ndong Sylvie',  '066 98 76 54', 'Akanda, rue des Cocotiers',  'CPT-ELE-2001', 'electricite'],
            ['Ondo Mengue Jean',  '077 45 67 89', 'Owendo, cité Damas',         'CPT-ELE-2002', 'electricite'],
            ['Bouanga Clarisse',  '062 33 22 11', 'Nzeng-Ayong, Libreville',    'CPT-EAU-1002', 'eau'],
        ];
        foreach ($demo as $d) {
            $id = db_exec(
                'INSERT INTO abonne (nom, telephone, adresse, numero_compteur, type_abonnement) VALUES (?,?,?,?,?)',
                $d
            );
            // Trois mois de relevés + factures pour chaque abonné
            foreach ([['2026-05', 3], ['2026-06', 2], ['2026-07', 1]] as $m) {
                $conso = $d[4] === 'eau' ? rand(8, 30) : rand(90, 320);
                $date  = $m[0] . '-05';
                $rid   = db_exec(
                    'INSERT INTO releve (abonne_id, mois, consommation, date_releve) VALUES (?,?,?,?)',
                    [$id, $m[0], (float) $conso, $date]
                );
                $montant = $conso * tarif($d[4]);
                $fid = db_exec(
                    'INSERT INTO facture (abonne_id, releve_id, montant, date_emission, statut) VALUES (?,?,?,?,?)',
                    [$id, $rid, (float) $montant, $date, 'impayee']
                );
                // Les factures les plus anciennes sont réglées
                if ($m[1] >= 2) {
                    $part = $m[1] === 3 ? $montant : round($montant * 0.5);
                    db_exec(
                        'INSERT INTO paiement (facture_id, montant, date, mode) VALUES (?,?,?,?)',
                        [$fid, (float) $part, $date, $m[1] === 3 ? 'especes' : 'mobile_money']
                    );
                    maj_statut_facture($fid);
                }
            }
        }
        $log[] = count($demo) . ' abonnés de démonstration avec relevés, factures et paiements.';

        // Un compte abonné relié au premier abonné
        $premier = db_one('SELECT id FROM abonne ORDER BY id LIMIT 1');
        if ($premier && !db_one('SELECT id FROM utilisateur WHERE email = ?', ['abonne@aquawatt.ga'])) {
            db_exec(
                'INSERT INTO utilisateur (nom, email, mot_de_passe, role, abonne_id) VALUES (?,?,?,?,?)',
                ['Nzeng Obiang Paul', 'abonne@aquawatt.ga', password_hash('abonne123', PASSWORD_DEFAULT), 'abonne', $premier['id']]
            );
            $log[] = 'Compte créé : abonne@aquawatt.ga / abonne123';
        }
    } else {
        $log[] = "Base déjà peuplée ($nb abonnés) : aucune donnée de démonstration ajoutée.";
    }
} catch (Exception $ex) {
    $erreur = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Installation — <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
  <div class="auth-card" style="max-width:640px">
    <div class="brand"><span class="brand-mark">⚡</span><span><?= APP_NAME ?></span></div>
    <h1>Installation de la base</h1>
    <?php if ($erreur): ?>
      <div class="alert alert-danger"><?= e($erreur) ?></div>
    <?php else: ?>
      <ul class="install-list">
        <?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
      </ul>
      <div class="alert alert-success">Installation terminée avec succès.</div>
      <a class="btn btn-primary btn-block" href="login.php">Aller à la connexion</a>
    <?php endif; ?>
  </div>
</body>
</html>
