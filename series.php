<?php
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) && !empty($_GET['extension']) ? $_GET['extension'] : 'mp4';

// Authentification client via la base de données
$stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1");
$stmt->execute([$user, $pass]);

if (!$stmt->fetch()) {
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// Recherche du flux (série ou épisode) dans la BDD
$stmt = $pdo->prepare("SELECT streams.*, fournisseurs.type, fournisseurs.url_base, fournisseurs.user, fournisseurs.pass FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ?");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Épisode introuvable."); 
}

$url_finale = "";

if ($data['type'] === 'xtream') {
    $base_host = rtrim($data['url_base'], '/');
    $url_finale = sprintf("%s/series/%s/%s/%s.%s", $base_host, $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

if (!empty($url_finale)) {
    header("Location: " . $url_finale, true, 302);
    exit;
} else {
    header('HTTP/1.1 502 Bad Gateway');
    die("Erreur : Lien de la série introuvable.");
}
?>
