<?php
// On récupère le chemin de l'URL demandée
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ==========================================
// 1. PROXY AUTOMATIQUE M3U (INTERCEPTION FORCEE)
// ==========================================
if (strpos($path, 'get.php') !== false) {
    require_once __DIR__ . '/config.php';

    $username = $_GET['username'] ?? null;
    $password = $_GET['password'] ?? null;

    if ($username && $password) {
        try {
            // Recherche du serveur d'origine en BDD
            $stmt = $pdo->prepare("SELECT * FROM lines WHERE (username = :u1 OR user = :u2) AND (password = :p1 OR pass = :p2) LIMIT 1");
            $stmt->execute(['u1' => $username, 'u2' => $username, 'p1' => $password, 'p2' => $password]);
            $line = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$line) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = :u1 OR user = :u2) AND (password = :p1 OR pass = :p2) LIMIT 1");
                $stmt->execute(['u1' => $username, 'u2' => $username, 'p1' => $password, 'p2' => $password]);
                $line = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($line) {
                $server_field = $line['server_url'] ?? $line['host'] ?? $line['server'] ?? $line['dns'] ?? null;
                
                if ($server_field) {
                    $source_base = rtrim($server_field, '/');
                    $upstream_url = $source_base . $_SERVER['REQUEST_URI'];

                    // Reconstitution avec User-Agent IPTV pour éviter la redirection /landpage
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $upstream_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    // On simule un lecteur IPTV légitime pour que le serveur source envoie le M3U au lieu de /landpage
                    $user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'IPTVSmart/1.0';
                    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

                    $response  = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code === 200 && $response !== false) {
                        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                        $public_domain = $scheme . "://" . $_SERVER['HTTP_HOST'];

                        // Remplacement automatique du serveur distant par le domaine Railway
                        $response = str_replace($source_base, $public_domain, $response);

                        header('Content-Type: audio/x-mpegurl');
                        header('Content-Disposition: attachment; filename="playlist.m3u"');
                        echo $response;
                        exit; // Mettre fin au script ici pour empecher get.php local d'exécuter la redirection /landpage
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Proxy M3U Error: " . $e->getMessage());
        }
    }
}

// 2. Interception pour le DIRECT (Live)
if (preg_match('#^/live/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/live.php';
    exit;
}

// 3. Interception pour la VOD (Films)
if (preg_match('#^/movie/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/vod.php';
    exit;
}

// 4. Interception pour les SÉRIES
if (preg_match('#^/series/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/series.php';
    exit;
}

// Comportement par défaut
$file = __DIR__ . $path;
if (is_file($file)) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
        exit;
    }
    return false; 
}

if (strpos($path, 'player_api.php') !== false) {
    require __DIR__ . '/player_api.php';
    exit;
}

return false;
?>
