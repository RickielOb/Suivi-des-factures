<?php
/**
 * Création des tables MySQL + jeu de données de démonstration.
 * À lancer une seule fois : http://localhost:8000/install.php
 */

require_once __DIR__ . '/lib/helpers.php';

$log    = [];
$erreur = null;

try {
    db_script([
        "CREATE TABLE IF NOT EXISTS abonne (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            telephone VARCHAR(30),
            adresse VARCHAR(180),
            numero_compteur VARCHAR(40) NOT NULL UNIQUE,
            type_abonnement ENUM('eau','electricite') NOT NULL,
            cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS releve (
            id INT AUTO_INCREMENT PRIMARY KEY,
            abonne_id INT NOT NULL,
            mois VARCHAR(7) NOT NULL,
            consommation DECIMAL(10,2) NOT NULL,
            date_releve DATE NOT NULL,
            UNIQUE KEY uniq_abonne_mois (abonne_id, mois),
            CONSTRAINT fk_releve_abonne FOREIGN KEY (abonne_id) REFERENCES abonne(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS facture (
            id INT AUTO_INCREMENT PRIMARY KEY,
            abonne_id INT NOT NULL,
            releve_id INT NOT NULL,
            montant DECIMAL(12,2) NOT NULL,
            date_emission DATE NOT NULL,
            statut ENUM('impayee','partielle','payee') NOT NULL DEFAULT 'impayee',
            CONSTRAINT fk_facture_abonne FOREIGN KEY (abonne_id) REFERENCES abonne(id),
            CONSTRAINT fk_facture_releve FOREIGN KEY (releve_id) REFERENCES releve(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS paiement (
            id INT AUTO_INCREMENT PRIMARY KEY,
            facture_id INT NOT NULL,
            montant DECIMAL(12,2) NOT NULL,
            date DATE NOT NULL,
            mode ENUM('especes','mobile_money','virement','cheque') NOT NULL,
            CONSTRAINT fk_paiement_facture FOREIGN KEY (facture_id) REFERENCES facture(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS utilisateur (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            mot_de_passe VARCHAR(255) NOT NULL,
            role ENUM('admin','agent','abonne') NOT NULL,
            abonne_id INT NULL,
            CONSTRAINT fk_user_abonne FOREIGN KEY (abonne_id) REFERENCES abonne(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
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
