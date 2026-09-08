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

// 2. Extraire les identifiants utilisateur (GET ou URL)
$username = $_GET['username'] ?? null;
$password = $_GET['password'] ?? null;

// Si l'URL utilise le format Xtream Codes (/live/username/password/stream_id.ts)
if (!$username || !$password) {
    $uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    if (count($uri_parts) >= 3) {
        $username = $uri_parts[1];
        $password = $uri_parts[2];
    }
}

// 3. Récupération DYNAMIQUE du serveur source depuis la BDD uniquement
$stream_url = null;
$source_server_base = null;

if ($username && $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM lines WHERE username = :username AND password = :password LIMIT 1");
        $stmt->execute(['username' => $username, 'password' => $password]);
        $line = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($line && !empty($line['server_url'])) {
            $source_server_base = rtrim($line['server_url'], '/');
            $stream_url = $source_server_base . $request_uri;
        }
    } catch (Exception $e) {
        // Log d'erreur BDD si nécessaire
    }
}

// Si aucun serveur source n'est trouvé dans la BDD pour cet utilisateur, on stoppe net (pas de fallback en dur)
if (!$stream_url || !$source_server_base) {
    http_response_code(404);
    echo "Error: Invalid credentials or missing source server configuration.";
    exit();
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
        // Remplacement dynamique : remplace l'URL source de la BDD par votre domaine public
        $response = str_replace($source_server_base, $public_domain, $response);

        // Renvoyer le fichier M3U directement au lecteur
        header('Content-Type: audio/x-mpegurl');
        header('Content-Disposition: attachment; filename="playlist.m3u"');
        echo $response;
        exit();
    } else {
        http_response_code(502);
        echo "Error: Unable to fetch data from upstream source.";
        exit();
    }
}

// 5. POUR LES FLUX STREAMING DIRECTS
header("Location: " . $stream_url);
exit();
