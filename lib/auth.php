<?php
/** Gestion de la session et des rôles. */

require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function user() { return isset($_SESSION['user']) ? $_SESSION['user'] : null; }

function role() { $u = user(); return $u ? $u['role'] : null; }

function is_logged() { return user() !== null; }

/** admin et agent gèrent les données, l'abonné consulte uniquement. */
function peut_gerer() { return in_array(role(), ['admin', 'agent'], true); }

function is_admin() { return role() === 'admin'; }

function require_login()
{
    if (!is_logged()) redirect('login.php');
    // L'abonné n'accède qu'à son espace personnel
    if (role() === 'abonne' && basename($_SERVER['PHP_SELF']) !== 'mon-espace.php') {
        redirect('mon-espace.php');
    }
}

function require_gestion()
{
    require_login();
    if (!peut_gerer()) {
        flash("Vous n'avez pas les droits nécessaires.", 'danger');
        redirect('index.php');
    }
}

function tenter_connexion($email, $motdepasse)
{
    $u = db_one('SELECT * FROM utilisateur WHERE email = ?', [trim($email)]);
    if (!$u || !password_verify($motdepasse, $u['mot_de_passe'])) {
        return false;
    }
    $_SESSION['user'] = [
        'id'        => $u['id'],
        'nom'       => $u['nom'],
        'email'     => $u['email'],
        'role'      => $u['role'],
        'abonne_id' => $u['abonne_id'],
    ];
    return true;
}
