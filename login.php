<?php
require_once __DIR__ . '/lib/auth.php';

if (is_logged()) redirect(role() === 'abonne' ? 'mon-espace.php' : 'index.php');

$erreur = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (tenter_connexion($_POST['email'] ?? '', $_POST['mot_de_passe'] ?? '')) {
            redirect(role() === 'abonne' ? 'mon-espace.php' : 'index.php');
        }
        $erreur = 'Email ou mot de passe incorrect.';
    } catch (Exception $ex) {
        $erreur = "Base indisponible : " . $ex->getMessage() . " — avez-vous lancé install.php ?";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
  <form class="auth-card" method="post">
    <div class="brand"><span class="brand-mark">⚡</span><span><?= APP_NAME ?></span></div>
    <h1>Connexion</h1>
    <p class="sub"><?= APP_DESC ?></p>

    <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

    <div class="field">
      <label for="email">Adresse email</label>
      <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="mot_de_passe">Mot de passe</label>
      <input type="password" id="mot_de_passe" name="mot_de_passe" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Se connecter</button>

    <div class="hint">
      Comptes de démonstration :<br>
      <code>admin@aquawatt.ga</code> / <code>admin123</code><br>
      <code>agent@aquawatt.ga</code> / <code>agent123</code><br>
      <code>abonne@aquawatt.ga</code> / <code>abonne123</code>
    </div>
  </form>
</body>
</html>
