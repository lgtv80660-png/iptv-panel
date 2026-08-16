<?php
require 'config.php';

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) ? $_GET['extension'] : 'mp4';

// 1. Authentification (Avec bypass pour zohir)
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

// SI L'AUTHENTIFICATION ÉCHOUE
if (!$auth_ok) {
    file_put_contents(__DIR__ . '/debug_series.txt', date('Y-m-d H:i:s') . " | ERREUR : Auth échouée | User: '$user' | Pass: '$pass'\n", FILE_APPEND);
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// 2. Recherche de l'épisode (on vérifie bien les types 'series' OU 'episode')
$stmt = $pdo->prepare("SELECT * FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ? AND stream_type IN ('series', 'episode')");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

// SI L'ÉPISODE EST INTROUVABLE
if (!$data) { 
    file_put_contents(__DIR__ . '/debug_series.txt', date('Y-m-d H:i:s') . " | ERREUR : ID Introuvable | Stream ID: '$stream_id'\n", FILE_APPEND);
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Épisode introuvable."); 
}

// 3. Construction de l'URL finale vers ton fournisseur
$url_finale = "";
if ($data['type'] === 'xtream') {
    $url_finale = sprintf("%s/series/%s/%s/%s.%s", $data['url_base'], $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

// --- MOUCHARD DEBUG ---
// On enregistre l'URL exacte que ton serveur essaie d'ouvrir
$log = date('Y-m-d H:i:s') . " | SUCCÈS | Stream ID: $stream_id | Redirection vers : $url_finale\n";
file_put_contents(__DIR__ . '/debug_series.txt', $log, FILE_APPEND);
// ----------------------

// 4. Redirection vers la vidéo
if ($url_finale) {
    header("Location: " . $url_finale, true, 302);
    exit;
}
?>