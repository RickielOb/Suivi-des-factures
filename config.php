<?php
/**
 * Configuration générale de l'application.
 */

// --- Base de données MySQL (Railway) ---
// L'URL interne ne fonctionne que depuis un service déployé sur Railway.
// En local, définir la variable d'environnement MYSQL_URL avec l'URL publique
// (MYSQL_PUBLIC_URL dans l'onglet Variables de Railway), ou modifier la valeur ci-dessous.
$mysql_url = getenv('MYSQL_URL')
    ?: getenv('DATABASE_URL')
    ?: 'mysql://root:jQTwDlIiCEZMuSbaIizbpcwgwIAuCTow@mysql.railway.internal:3306/railway';

$u = parse_url($mysql_url);
define('DB_HOST', $u['host'] ?? 'localhost');
define('DB_PORT', $u['port'] ?? 3306);
define('DB_NAME', ltrim($u['path'] ?? '/railway', '/'));
define('DB_USER', urldecode($u['user'] ?? 'root'));
define('DB_PASS', urldecode($u['pass'] ?? ''));

// --- Application ---
define('APP_NAME', 'AquaWatt');
define('APP_DESC', 'Suivi des factures eau & électricité');
define('DEVISE',   'FCFA');

// Tarifs unitaires appliqués lors de la génération automatique des factures
define('TARIF_EAU',          350);  // FCFA / m3
define('TARIF_ELECTRICITE',  105);  // FCFA / kWh

date_default_timezone_set('Africa/Libreville');
