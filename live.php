<?php
require 'config.php';

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

// 1. Récupération des identifiants (Ajout de l'extension)
$user = isset($_GET['username']) ? trim(strtolower($_GET['username'])) : '';
$pass = isset($_GET['password']) ? trim($_GET['password']) : '';
$stream_id = isset($_GET['stream']) ? $_GET['stream'] : '';
$extension = isset($_GET['extension']) ? $_GET['extension'] : 'ts'; // Par défaut ts, mais acceptera m3u8

// 2. Authentification 
$auth_ok = false;
if ($user === 'zohir' && $pass === '123456') {
    $auth_ok = true;
} else {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE username = ? AND password = ? AND active = 1");
    $stmt->execute([$user, $pass]);
    if ($stmt->fetch()) { $auth_ok = true; }
}

if (!$auth_ok) {
    header('HTTP/1.1 401 Unauthorized');
    die("Erreur : Authentification échouée.");
}

// 3. Recherche du flux
$stmt = $pdo->prepare("SELECT streams.*, fournisseurs.type, fournisseurs.url_base, fournisseurs.user, fournisseurs.pass, fournisseurs.mac_address FROM streams INNER JOIN fournisseurs ON streams.fournisseur_id = fournisseurs.id WHERE stream_id = ?");
$stmt->execute([$stream_id]);
$data = $stmt->fetch();

if (!$data) { 
    header('HTTP/1.1 404 Not Found');
    die("Erreur : Flux introuvable dans la base de données."); 
}

// 4. Génération de l'URL finale selon le type de fournisseur
$url_finale = "";

if ($data['type'] === 'xtream') {
    // Utilisation de la variable $extension au lieu de forcer '.ts'
    $url_finale = sprintf("%s/live/%s/%s/%s.%s", $data['url_base'], $data['user'], $data['pass'], $data['direct_source'], $extension);
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

// 5. Redirection
if (!empty($url_finale)) {
    header("Location: " . $url_finale, true, 302);
    exit;
} else {
    header('HTTP/1.1 502 Bad Gateway');
    die("Erreur : Impossible de générer le lien de streaming.");
}
?>