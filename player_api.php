<?php
require 'config.php';

// En-têtes CORS obligatoires pour les lecteurs IPTV
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

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
    file_put_contents(__DIR__ . '/debug_series.txt', date('Y-m-d H:i:s') . " | ERREUR : Auth échouée | User: '$user'\n", FILE_APPEND);
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// 2. Recherche de l'épisode dans la BDD
$stmt = $pdo->prepare("SELECT streams.*, fournisseurs.type, fournisseurs.url_base, fournisseurs.user, fournisseurs.pass FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ? AND stream_type IN ('series', 'episode')");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    file_put_contents(__DIR__ . '/debug_series.txt', date('Y-m-d H:i:s') . " | ERREUR : ID Introuvable | Stream ID: '$stream_id'\n", FILE_APPEND);
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Épisode introuvable."); 
}

// 3. Construction de l'URL distante
$url_finale = "";
if ($data['type'] === 'xtream') {
    $base_host = rtrim($data['url_base'], '/');
    $url_finale = sprintf("%s/series/%s/%s/%s.%s", $base_host, $data['user'], $data['pass'], $data['direct_source'], $extension);
} elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
}

if (empty($url_finale)) {
    header('HTTP/1.1 502 Bad Gateway');
    die("Erreur : Lien vidéo introuvable.");
}

// --- LOG DEBUG ---
$log = date('Y-m-d H:i:s') . " | SUCCÈS STREAM | Stream ID: $stream_id | Source: $url_finale\n";
file_put_contents(__DIR__ . '/debug_series.txt', $log, FILE_APPEND);

// 4. Définition du Content-Type pour le lecteur
$mime_types = [
    'mp4'  => 'video/mp4',
    'mkv'  => 'video/x-matroska',
    'avi'  => 'video/x-msvideo',
    'm3u8' => 'application/x-mpegURL',
    'ts'   => 'video/mp2t'
];
$content_type = $mime_types[strtolower($extension)] ?? 'video/mp4';

header("Content-Type: " . $content_type);

// Transmettre les en-têtes HTTP de support Range (reprise de lecture / avance rapide)
$req_headers = [];
if (isset($_SERVER['HTTP_RANGE'])) {
    $req_headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

// 5. Proxy de streaming cURL direct
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_finale);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
curl_setopt($ch, CURLOPT_TIMEOUT, 0);

if (!empty($req_headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $req_headers);
}

// Relayer les en-têtes de réponse du fournisseur au lecteur
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    $len = strlen($header);
    $header_clean = trim($header);
    if (strpos($header_clean, 'Content-Range:') === 0 || 
        strpos($header_clean, 'Content-Length:') === 0 || 
        strpos($header_clean, 'Accept-Ranges:') === 0) {
        header($header_clean);
    }
    return $len;
});

curl_exec($ch);
curl_close($ch);
exit;
?>
