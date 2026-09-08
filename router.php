<?php
/**
 * Router Central & Proxy Automatique - G-PANEL IPTV
 * Redirige et relaie les requêtes M3U / Xtream Codes
 */

require_once __DIR__ . '/config.php';

// 1. Récupération des paramètres de la requête
$request_uri = $_SERVER['REQUEST_URI'];
$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? 'IPTV-Proxy';

// Détermination dynamique du domaine public (ex: http://www.ztv.work.gd)
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$public_domain = $scheme . "://" . $_SERVER['HTTP_HOST'];

// 2. Extraire les identifiants utilisateur (GET ou URI)
$username = $_GET['username'] ?? null;
$password = $_GET['password'] ?? null;

// Si l'URL utilise le format Xtream Codes (/live/username/password/stream_id.ts)
if (!$username || !$password) {
    $uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    if (count($uri_parts) >= 3) {
        $type = $uri_parts[0]; // live, movie, series
        $username = $uri_parts[1];
        $password = $uri_parts[2];
    }
}

// 3. Vérification des identifiants et récupération du serveur source en Base de Données
$stream_url = null;

if ($username && $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM lines WHERE username = :username AND password = :password LIMIT 1");
        $stmt->execute(['username' => $username, 'password' => $password]);
        $line = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($line && isset($line['server_url'])) {
            $server_base = rtrim($line['server_url'], '/');
            $stream_url  = $server_base . $request_uri;
        }
    } catch (Exception $e) {
        // Fallback si la BDD utilise une structure différente
    }
}

// Si la source n'a pas été trouvée en BDD, utiliser la source par défaut
if (!$stream_url) {
    // Remplacer par votre serveur/IP source par défaut si nécessaire
    $default_source = "http://88.255.216.16:8080"; 
    $stream_url = rtrim($default_source, '/') . $request_uri;
}

// 4. TRAITEMENT PROXY AUTOMATIQUE POUR LES PLAYLISTS M3U (get.php)
if (strpos($request_uri, 'get.php') !== false || isset($_GET['type'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $stream_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response !== false) {
        // Extraction dynamique de l'IP/Domaine source pour la remplacer par le domaine Railway
        $parsed_source = parse_url($stream_url);
        if (isset($parsed_source['host'])) {
            $source_host_port = $parsed_source['scheme'] . '://' . $parsed_source['host'] . (isset($parsed_source['port']) ? ':' . $parsed_source['port'] : '');
            
            // Remplacement automatique de toutes les occurrences de la source par votre domaine
            $response = str_replace($source_host_port, $public_domain, $response);
        }

        // Renvoyer le fichier M3U directement sans redirection
        header('Content-Type: audio/x-mpegurl');
        header('Content-Disposition: attachment; filename="playlist.m3u"');
        echo $response;
        exit();
    }
}

// 5. POUR LES FLUX STREAMING DIRECTS (Live / Vod / TS / MP4)
header("Location: " . $stream_url);
exit();
