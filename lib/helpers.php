<?php
/** Fonctions utilitaires partagées. */

require_once __DIR__ . '/db.php';

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function money($v) { return number_format((float) $v, 0, ',', ' ') . ' ' . DEVISE; }

/** Affiche "2026-03" sous la forme "Mars 2026". */
function mois_label($mois)
{
    $noms = [1=>'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $p = explode('-', (string) $mois);
    if (count($p) !== 2 || !isset($noms[(int) $p[1]])) return $mois;
    return $noms[(int) $p[1]] . ' ' . $p[0];
}

function date_fr($d)
{
    $t = strtotime((string) $d);
    return $t ? date('d/m/Y', $t) : $d;
}

function badge_statut($statut)
{
    $map = [
        'payee'     => ['success', 'Payée'],
        'partielle' => ['warning', 'Partielle'],
        'impayee'   => ['danger',  'Impayée'],
    ];
    $b = isset($map[$statut]) ? $map[$statut] : ['secondary', $statut];
    return '<span class="badge badge-' . $b[0] . '">' . e($b[1]) . '</span>';
}

function unite($type) { return $type === 'eau' ? 'm³' : 'kWh'; }

function tarif($type) { return $type === 'eau' ? TARIF_EAU : TARIF_ELECTRICITE; }

/** Messages flash. */
function flash($msg = null, $type = 'success')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Recalcule le statut d'une facture à partir des paiements enregistrés.
 */
function maj_statut_facture($facture_id)
{
    $f = db_one('SELECT montant FROM facture WHERE id = ?', [(int) $facture_id]);
    if (!$f) return;
    $paye = (float) db_value('SELECT COALESCE(SUM(montant),0) FROM paiement WHERE facture_id = ?', [(int) $facture_id], 0);

    if ($paye <= 0)                       $statut = 'impayee';
    elseif ($paye + 0.001 < (float) $f['montant']) $statut = 'partielle';
    else                                  $statut = 'payee';

    db_exec('UPDATE facture SET statut = ? WHERE id = ?', [$statut, (int) $facture_id]);
}
