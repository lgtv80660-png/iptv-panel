<?php
// player/proxy.php - Relais de flux vidéo Xtream / IPTV
require_once '../config.php';

$streamUrl = $_GET['url'] ?? null;

if (!$streamUrl) {
    http_response_code(400);
    die("URL de flux manquante.");
}

// Sécurisation de l'URL reçue
$streamUrl = filter_var($streamUrl, FILTER_VALIDATE_URL);
if (!$streamUrl) {
    http_response_code(400);
    die("URL invalide.");
}

// Initialisation du relais cURL pour le streaming vidéo
$ch = curl_init($streamUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

// Transmettre le bon type MIME selon le format (HLS .m3u8, TS, MP4)
if (strpos($streamUrl, '.m3u8') !== false) {
    header('Content-Type: application/vnd.apple.mpegurl');
} elseif (strpos($streamUrl, '.mp4') !== false) {
    header('Content-Type: video/mp4');
} else {
    header('Content-Type: video/mp2t');
}

header('Access-Control-Allow-Origin: *');

curl_exec($ch);
curl_close($ch);
?>