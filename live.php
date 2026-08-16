<?php
require 'config.php';

// En-têtes CORS obligatoires pour la lecture multi-plateforme
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

function fetch_data_live($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stb appstore safari/533.3'); 
    if (!empty($headers)) { curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); }
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) && !empty($_GET['extension']) ? $_GET['extension'] : 'ts';

// Authentification
$stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1");
$stmt->execute([$user, $pass]);

if (!$stmt->fetch()) {
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// Recherche du flux Live
$stmt = $pdo->prepare("SELECT streams.*, fournisseurs.type, fournisseurs.url_base, fournisseurs.user, fournisseurs.pass, fournisseurs.mac_address FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ?");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Flux introuvable dans la base de données."); 
}

$url_finale = "";

if ($data['type'] === 'xtream') {
    // Nettoyage de l'URL de base et construction du lien Live
    $base_host = rtrim($data['url_base'], '/');
    $url_finale = sprintf("%s/live/%s/%s/%s.%s", $base_host, $data['user'], $data['pass'], $data['direct_source'], $extension);
} 
elseif ($data['type'] === 'm3u') {
    $url_finale = $data['direct_source'];
} 
elseif ($data['type'] === 'stalker') {
    $portalUrl = rtrim($data['url_base'], '/');
    $mac = $data['mac_address'] ?? '';
    $cmd = $data['direct_source'];

    $handshakeUrl = "$portalUrl/c/portal.php?type=stb&action=handshake&mac=" . urlencode($mac);
    $handshakeResp = fetch_data_live($handshakeUrl);
    $handshakeData = json_decode($handshakeResp, true);
    $token = $handshakeData['js']['token'] ?? '';

    $headers = [];
    if ($token) { $headers[] = "Authorization: Bearer " . $token; }
    $headers[] = "Cookie: mac=" . urlencode($mac) . "; stb_lang=fr; timezone=Europe/Paris";

    $createLinkUrl = "$portalUrl/c/portal.php?type=itv&action=create_link&cmd=" . urlencode($cmd);
    $createLinkResp = fetch_data_live($createLinkUrl, $headers);
    $createData = json_decode($createLinkResp, true);
    
    $url_finale = $createData['js']['cmd'] ?? '';
    
    if (strpos($url_finale, 'http') !== false && !filter_var($url_finale, FILTER_VALIDATE_URL)) {
         $parts = explode('http', $url_finale);
         if (count($parts) > 1) { $url_finale = 'http' . $parts[1]; }
    }
}

if (!empty($url_finale)) {
    // Redirection HTTP 302 vers la source finale
    header("Location: " . $url_finale, true, 302);
    exit;
} else {
    header('HTTP/1.1 502 Bad Gateway');
    die("Erreur : Impossible de générer le lien de streaming.");
}
?>
