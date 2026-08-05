<?php
/**
 * Petit client HTTP pour Turso / libSQL (API "pipeline" v2).
 * PHP n'ayant pas de driver libSQL natif, on dialogue en HTTP + JSON.
 * L'API exposée (db_all, db_one, db_value, db_exec, db_script) est la même
 * partout dans l'application : les pages n'ont pas à connaître le SGBD.
 */

require_once __DIR__ . '/../config.php';

class TursoException extends Exception {}

/**
 * Envoie une liste d'instructions SQL et renvoie les résultats bruts.
 */
function turso_pipeline(array $statements)
{
    $requests = [];
    foreach ($statements as $st) {
        $requests[] = ['type' => 'execute', 'stmt' => $st];
    }
    $requests[] = ['type' => 'close'];

    $ch = curl_init(rtrim(TURSO_URL, '/') . '/v2/pipeline');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['requests' => $requests]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . TURSO_TOKEN,
            'Content-Type: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new TursoException("Connexion à la base impossible : $err");
    }
    if ($code !== 200) {
        throw new TursoException("Erreur HTTP $code : $body");
    }

    $data = json_decode($body, true);
    if (!isset($data['results'])) {
        throw new TursoException("Réponse inattendue de la base.");
    }

    $out = [];
    foreach ($data['results'] as $res) {
        if ($res['type'] === 'error') {
            throw new TursoException('SQL : ' . $res['error']['message']);
        }
        if (isset($res['response']['result'])) {
            $out[] = $res['response']['result'];
        }
    }
    return $out;
}

/** Convertit une valeur PHP en argument typé libSQL. */
function turso_arg($v)
{
    if ($v === null)  return ['type' => 'null'];
    if (is_bool($v))  return ['type' => 'integer', 'value' => $v ? '1' : '0'];
    if (is_int($v))   return ['type' => 'integer', 'value' => (string) $v];
    if (is_float($v)) return ['type' => 'float',   'value' => $v];
    return ['type' => 'text', 'value' => (string) $v];
}

/** Convertit une valeur libSQL en valeur PHP. */
function turso_val(array $c)
{
    switch ($c['type']) {
        case 'null':    return null;
        case 'integer': return (int) $c['value'];
        case 'float':   return (float) $c['value'];
        default:        return $c['value'];
    }
}

/** Exécute une requête et renvoie toutes les lignes (tableaux associatifs). */
function db_all($sql, array $params = [])
{
    $args = array_map('turso_arg', $params);
    $res  = turso_pipeline([['sql' => $sql, 'args' => $args]]);
    $res  = $res[0];

    $cols = array_column($res['cols'], 'name');
    $rows = [];
    foreach ($res['rows'] as $r) {
        $line = [];
        foreach ($r as $i => $cell) {
            $line[$cols[$i]] = turso_val($cell);
        }
        $rows[] = $line;
    }
    return $rows;
}

/** Renvoie la première ligne ou null. */
function db_one($sql, array $params = [])
{
    $rows = db_all($sql, $params);
    return $rows ? $rows[0] : null;
}

/** Renvoie la première colonne de la première ligne. */
function db_value($sql, array $params = [], $default = null)
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : $default;
}

/** INSERT / UPDATE / DELETE : renvoie l'id inséré. */
function db_exec($sql, array $params = [])
{
    $args = array_map('turso_arg', $params);
    $res  = turso_pipeline([['sql' => $sql, 'args' => $args]]);
    return isset($res[0]['last_insert_rowid']) ? (int) $res[0]['last_insert_rowid'] : 0;
}

/** Exécute plusieurs requêtes SQL sans paramètres, à la suite. */
function db_script(array $sqls)
{
    $stmts = [];
    foreach ($sqls as $s) {
        $stmts[] = ['sql' => $s, 'args' => []];
    }
    turso_pipeline($stmts);
}
