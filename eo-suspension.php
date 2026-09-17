<?php
/**
 * Suspension / réactivation d'un site (usage agence Eoxia).
 * UN SEUL fichier à déposer à la RACINE du site (même dossier que le .htaccess et index.php).
 * La page publique "suspendu.html" est générée automatiquement par ce script.
 *
 * Utilisation :
 *   https://site-du-client.fr/gestion-site.php?key=VOTRE_CLE           -> page d'état + boutons
 *   https://site-du-client.fr/gestion-site.php?key=VOTRE_CLE&etat=off  -> suspend
 *   https://site-du-client.fr/gestion-site.php?key=VOTRE_CLE&etat=on   -> réactive
 */

// ============ À CONFIGURER ============
$CLE       = 'CLE_UNIQUE_A_CHANGER';  // secret d'accès
$IP_BUREAU = '';   // optionnel : votre IP fixe pour continuer à voir le site suspendu ('' = personne)
// ======================================

$SCRIPT = basename(__FILE__);
$HT     = __DIR__ . '/.htaccess';
$PAGE   = __DIR__ . '/suspendu.html';
$DEBUT  = '# ===SUSPENSION_DEBUT===';
$FIN    = '# ===SUSPENSION_FIN===';

// Page publique affichée aux visiteurs pendant la suspension (auto-générée).
$HTML_SUSPENDU = <<<'HTML'
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Site temporairement indisponible</title>
<style>
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f7f9;color:#2c3e50;
       display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;box-sizing:border-box;}
  .box{max-width:520px;text-align:center;background:#fff;padding:48px 40px;border-radius:16px;
       box-shadow:0 10px 40px rgba(0,0,0,.06)}
  h1{font-size:26px;margin:0 0 14px}
  p{font-size:16px;line-height:1.6;color:#5a6b7b;margin:0 0 10px}
  a{color:#2c7be5;text-decoration:none;font-weight:600}
</style>
</head>
<body>
  <div class="box">
    <h1>Site suspendu</h1>
    <p>Ce site est momentanément suspendu.</p>
    <p>Pour rétablir le service, merci de nous contacter à
       <a href="mailto:contact@eoxia.com">contact@eoxia.com</a>.</p>
  </div>
</body>
</html>
HTML;

// Contrôle d'accès
if ( ($_GET['key'] ?? '') !== $CLE ) {
    http_response_code(403);
    exit('Acces refuse');
}

$ht       = is_file($HT) ? file_get_contents($HT) : '';
$suspendu = (strpos($ht, $DEBUT) !== false);
$action   = $_GET['etat'] ?? '';

// --- SUSPENDRE ---
if ( $action === 'off' && ! $suspendu ) {
    // (Re)génère la page publique, puis pose le blocage 503 en haut du .htaccess.
    file_put_contents($PAGE, $HTML_SUSPENDU);

    $condIp = $IP_BUREAU !== '' ? "RewriteCond %{REMOTE_ADDR} !=$IP_BUREAU\n" : '';
    $bloc =
        "$DEBUT\n" .
        "<IfModule mod_rewrite.c>\n" .
        "ErrorDocument 503 /suspendu.html\n" .
        "RewriteEngine On\n" .
        $condIp .
        "RewriteCond %{REQUEST_URI} !^/suspendu\\.html$ [NC]\n" .
        "RewriteCond %{REQUEST_URI} !^/$SCRIPT$ [NC]\n" .
        "RewriteRule .* - [R=503,L]\n" .
        "</IfModule>\n" .
        "<IfModule mod_headers.c>\n" .
        "Header always set Retry-After \"86400\"\n" .
        "Header always set Cache-Control \"no-store\"\n" .
        "</IfModule>\n" .
        "$FIN\n";
    file_put_contents($HT, $bloc . $ht);
    $suspendu = true;
}

// --- RÉACTIVER ---
elseif ( $action === 'on' && $suspendu ) {
    $ht = preg_replace(
        '/' . preg_quote($DEBUT, '/') . '.*?' . preg_quote($FIN, '/') . '\s*/s',
        '',
        $ht
    );
    file_put_contents($HT, $ht);
    @unlink($PAGE);   // nettoie la page publique
    $suspendu = false;
}

// --- Page d'état avec boutons ---
$etat    = $suspendu ? 'SUSPENDU' : 'EN LIGNE';
$couleur = $suspendu ? '#c0392b' : '#27ae60';
$k       = urlencode($CLE);

header('Content-Type: text/html; charset=UTF-8');
?><!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestion du site</title>
<div style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;max-width:440px;margin:12vh auto;text-align:center;padding:0 20px">
  <p style="font-size:15px;color:#555;margin:0 0 6px">État du site</p>
  <p style="font-size:26px;font-weight:700;color:<?php echo $couleur; ?>;margin:0 0 30px"><?php echo $etat; ?></p>
  <?php if ($suspendu): ?>
    <a href="?key=<?php echo $k; ?>&etat=on"
       style="display:inline-block;padding:16px 32px;background:#27ae60;color:#fff;border-radius:10px;text-decoration:none;font-size:18px;font-weight:600">✅ Réactiver le site</a>
  <?php else: ?>
    <a href="?key=<?php echo $k; ?>&etat=off"
       onclick="return confirm('Suspendre ce site ?')"
       style="display:inline-block;padding:16px 32px;background:#c0392b;color:#fff;border-radius:10px;text-decoration:none;font-size:18px;font-weight:600">⛔ Suspendre le site</a>
  <?php endif; ?>
</div>
