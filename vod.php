<?php
require 'config.php';

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) ? $_GET['extension'] : 'mp4'; // Par défaut mp4 pour la VOD

// Authentification (Avec le bypass pour ton test)
$auth_ok = false;
if ($user === 'zohir' && $pass === '123456') {
    $auth_ok = true;
} else {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE username = ? AND password = ? AND active = 1");
    $stmt->execute([$user, $pass]);
    if ($stmt->fetch()) {
        $auth_ok = true;
    }
}

if (!$auth_ok) {
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// Recherche du film (stream_type = 'movie')
$stmt = $pdo->prepare("SELECT * FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ? AND stream_type = 'movie'");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Film introuvable."); 
}

// Construction de l'URL finale
$url_finale = "";
if ($data['type'] === 'xtream') {
    // Reconstruit le lien VOD Xtream original : /movie/user/pass/ID.mp4
    $url_finale = sprintf("%s/movie/%s/%s/%s.%s", $data['url_base'], $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

// Redirection vers la vidéo
if ($url_finale) {
    header("Location: " . $url_finale, true, 302);
    exit;
}
?>