<?php
/**
 * Configuration générale de l'application.
 */

// --- Base de données Turso (libSQL) ---
define('TURSO_URL',   'https://projet-wen-dynamique-rickiel.aws-eu-west-1.turso.io');
define('TURSO_TOKEN', 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJhIjoicnciLCJleHAiOjE3OTExNDAwMjEsImlhdCI6MTc4NTk1NjAyMSwiaWQiOiIwMTlmZDMxZi1jNjAxLTdjMGEtOGMwYi1hOGY3YzkwZGY0ZDUiLCJraWQiOiJzTXpUUzktOWs4eEJ0Tms4YU02R2M0UE1jMWdtU2w4T1h4aS1fU3pSVEFjIiwicmlkIjoiMzZhYzNjMWEtNjcwYi00MGFmLWFmNzAtYmUyZWVmYjhiZWFhIn0.X4XpCLuy2rEdRAWt1SNHqSaZLUjay2VFp1P58m5rDT8A4g_HwnClyFutYi4bHgrtdiDP9erwIbJIKzlNCDuOBg');

// --- Application ---
define('APP_NAME', 'AquaWatt');
define('APP_DESC', 'Suivi des factures eau & électricité');
define('DEVISE',   'FCFA');

// Tarifs unitaires appliqués lors de la génération automatique des factures
define('TARIF_EAU',          350);  // FCFA / m3
define('TARIF_ELECTRICITE',  105);  // FCFA / kWh

date_default_timezone_set('Africa/Libreville');
