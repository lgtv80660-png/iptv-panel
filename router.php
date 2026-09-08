<?php
/**
 * Router Central & Proxy Automatique - G-PANEL IPTV
 */

require_once __DIR__ . '/config.php';

// 1. Récupération des paramètres de la requête
$request_uri = $_SERVER['REQUEST_URI'];
$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? 'IPTV-Proxy';

// Domaine public dynamique
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$public_domain = $scheme . "://" . $_SERVER['HTTP_HOST'];

// 2. Extraire les identifiants
$username = $_GET['username'] ?? $_GET['user'] ?? null;
$password = $_GET['password'] ?? $_GET['pass'] ?? null;

if (!$username || !$password) {
    $uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    if (count($uri_parts) >= 3) {
        $username = $uri_parts[1];
        $password = $uri_parts[2];
    }
}

$stream_url = null;
$source_server_base = null;

// 3. Recherche adaptative en Base de Données
if ($username && $password) {
    try {
        // Tentative 1: Table 'lines'
        $stmt = $pdo->prepare("SELECT * FROM lines WHERE (username = :u1 OR user = :u2) AND (password = :p1 OR pass = :p2) LIMIT 1");
        $stmt->execute(['u1' => $username, 'u2' => $username, 'p1' => $password, 'p2' => $password]);
        $line = $stmt->fetch(PDO::FETCH_ASSOC);

        // Tentative 2: Table 'users' si 'lines' ne renvoie rien
        if (!$line) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = :u1 OR user = :u2) AND (password = :p1 OR pass = :p2) LIMIT 1");
            $stmt->execute(['u1' => $username, 'u2' => $username, 'p1' => $password, 'p2' => $password]);
            $line = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($line) {
            // Identifier le champ contenant l'URL du serveur source
            $server_field = $line['server_url'] ?? $line['host'] ?? $line['server'] ?? $line['dns'] ?? null;
            
            if ($server_field) {
                $source_server_base = rtrim($server_field, '/');
                $stream_url = $source_server_base . $request_uri;
            }
        }
    } catch (Exception $e) {
        error_log("DB Router Error: " . $e->getMessage());
    }
}

// 4. Si aucune correspondance n'est trouvée
if (!$stream_url || !$source_server_base) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "error",
        "message" => "Invalid credentials or source server URL not found in database for user: " . htmlspecialchars($username)
    ]);
    exit();
}

// 5. Traitement Proxy M3U (get.php)
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
        $response = str_replace($source_server_base, $public_domain, $response);

        header('Content-Type: audio/x-mpegurl');
        header('Content-Disposition: attachment; filename="playlist.m3u"');
        echo $response;
        exit();
    } else {
        http_response_code(502);
        echo "Error: Unable to fetch data from source server ($http_code).";
        exit();
    }
}

// 6. Streaming Direct
header("Location: " . $stream_url);
exit();
