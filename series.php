<?php
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) && !empty($_GET['extension']) ? $_GET['extension'] : 'mp4';

// 1. Authentification dynamique via BDD
$stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1");
$stmt->execute([$user, $pass]);
$client = $stmt->fetch();

if (!$client) {
    file_put_contents(__DIR__ . '/debug_series.txt', date('Y-m-d H:i:s') . " | ERREUR : Auth échouée | User: '$user'\n", FILE_APPEND);
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// 2. Recherche de l'épisode dans la BDD (stream_type = 'episode' ou 'series')
$stmt = $pdo->prepare("SELECT streams.*, fournisseurs.type, fournisseurs.url_base, fournisseurs.user, fournisseurs.pass FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ? AND stream_type IN ('series', 'episode')");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    file_put_contents(__DIR__ . '/debug_series.txt', date('Y-m-d H:i:s') . " | ERREUR : ID Introuvable | Stream ID: '$stream_id'\n", FILE_APPEND);
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Épisode introuvable."); 
}

// 3. Construction de l'URL du fournisseur d'origine
$url_finale = "";
if ($data['type'] === 'xtream') {
    $base_host = rtrim($data['url_base'], '/');
    $url_finale = sprintf("%s/series/%s/%s/%s.%s", $base_host, $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

// --- LOG DEBUG ---
$log = date('Y-m-d H:i:s') . " | SUCCÈS | Stream ID: $stream_id | Redirection vers : $url_finale\n";
file_put_contents(__DIR__ . '/debug_series.txt', $log, FILE_APPEND);

// 4. Redirection HTTP 301/302
if (!empty($url_finale)) {
    header("Location: " . $url_finale, true, 301);
    exit;
} else {
    header('HTTP/1.1 502 Bad Gateway');
    die("Erreur : Impossible de générer le lien de la série.");
}
?>
