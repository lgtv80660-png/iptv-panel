<?php
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) ? $_GET['extension'] : 'mp4';

$stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1");
$stmt->execute([$user, $pass]);

if (!$stmt->fetch()) {
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

$stmt = $pdo->prepare("SELECT * FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ? AND stream_type IN ('series', 'episode')");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Épisode introuvable."); 
}

$url_finale = "";
if ($data['type'] === 'xtream') {
    $url_finale = sprintf("%s/series/%s/%s/%s.%s", $data['url_base'], $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

if ($url_finale) {
    header("Location: " . $url_finale, true, 302);
    exit;
}
?>
