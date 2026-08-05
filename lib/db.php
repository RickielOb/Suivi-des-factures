<?php
/**
 * Accès à la base MySQL via PDO.
 * L'API (db_all, db_one, db_value, db_exec, db_script) est identique partout
 * dans l'application : les pages n'ont pas à connaître le SGBD.
 */

require_once __DIR__ . '/../config.php';

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ]);
    }
    return $pdo;
}

/** Prépare et exécute une requête. */
function db_run($sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Toutes les lignes sous forme de tableaux associatifs. */
function db_all($sql, array $params = [])
{
    return db_run($sql, $params)->fetchAll();
}

/** Première ligne ou null. */
function db_one($sql, array $params = [])
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Première colonne de la première ligne. */
function db_value($sql, array $params = [], $default = null)
{
    $v = db_run($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

/** INSERT / UPDATE / DELETE : renvoie l'id inséré. */
function db_exec($sql, array $params = [])
{
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}

/** Exécute plusieurs requêtes à la suite (création de schéma). */
function db_script(array $sqls)
{
    foreach ($sqls as $sql) {
        db()->exec($sql);
    }
}
