<?php
require 'config.php';

// Autoriser la lecture cross-origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

// Augmenter les limites de temps et de mémoire pour le streaming
set_time_limit(0);
ini_set('memory_limit', '512M');

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) && !empty($_GET['extension']) ? $_GET['extension'] : 'mp4';

// 1. Authentification dynamique
$stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1");
$stmt->execute([$user, $pass]);
$client = $stmt->fetch();

if (!$client) {
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// 2. Recherche de l'épisode dans la BDD
$stmt = $pdo->prepare("SELECT streams.*, fournisseurs.type, fournisseurs.url_base, fournisseurs.user, fournisseurs.pass FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ? AND stream_type IN ('series', 'episode')");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Épisode introuvable."); 
}

// 3. Construction du lien source
$url_finale = "";
if ($data['type'] === 'xtream') {
    $base_host = rtrim($data['url_base'], '/');
    $url_finale = sprintf("%s/series/%s/%s/%s.%s", $base_host, $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

if (empty($url_finale)) {
    header('HTTP/1.1 502 Bad Gateway');
    die("Erreur : Lien vidéo vide.");
}

// 4. Mode Proxy Streaming (contourne le blocage HTTP Mixed Content)
$mime_types = [
    'mp4'  => 'video/mp4',
    'mkv'  => 'video/x-matroska',
    'avi'  => 'video/x-msvideo',
    'm3u8' => 'application/x-mpegURL',
    'ts'   => 'video/mp2t'
];
$content_type = $mime_types[$extension] ?? 'video/mp4';

header("Content-Type: " . $content_type);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_finale);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
curl_setopt($ch, CURLOPT_TIMEOUT, 0);

// Transmettre le flux au lecteur
curl_exec($ch);
curl_close($ch);
exit;
?>
