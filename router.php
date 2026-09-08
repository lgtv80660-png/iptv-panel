<?php
ob_start(); // Empêche toute erreur d'en-tête (headers) qui forcerait l'affichage HTML

// On récupère le chemin de l'URL demandée
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);[cite: 2]

// ==========================================
// 1. PROXY AUTOMATIQUE M3U (GET.PHP)
// ==========================================
if (strpos($path, 'get.php') !== false) {
    require_once __DIR__ . '/config.php';

    $username = $_GET['username'] ?? $_GET['user'] ?? null;
    $password = $_GET['password'] ?? $_GET['pass'] ?? null;

    $source_base = null;

    if ($username && $password) {
        try {
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
                }
            }

            if (!$source_base) {
                try {
                    $stmt = $pdo->query("SELECT domain_name FROM servers LIMIT 1");
                    $server_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($server_row && !empty($server_row['domain_name'])) {
                        $source_base = rtrim($server_row['domain_name'], '/');
                    }
                } catch (Exception $e) {}
            }

            if ($source_base) {
                $upstream_url = $source_base . $_SERVER['REQUEST_URI'];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $upstream_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                // L'ASTUCE EST ICI : Se faire passer pour une application IPTV légitime
                curl_setopt($ch, CURLOPT_USERAGENT, 'IPTVSmartersPro');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: */*', 'Connection: keep-alive'));

                $response  = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                ob_clean(); // Nettoie la mémoire tampon pour forcer le téléchargement strict

                // SÉCURITÉ : Si le serveur a quand même envoyé le Javascript Landpage
                if (stripos($response, '<script') !== false || stripos($response, 'landpage') !== false) {
                    http_response_code(403);
                    header('Content-Type: text/plain');
                    echo "BLOCAGE ANTI-DDOS : Le serveur source (88.255.216.16) refuse de donner le fichier et force l'affichage de sa page de sécurité (/landpage).\n";
                    echo "Solution : Demandez à l'administrateur du serveur source d'autoriser l'User-Agent 'IPTVSmartersPro' ou de désactiver 'Force Landpage'.";
                    exit;
                }

                if ($http_code === 200 && $response !== false) {
                    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $public_domain = $scheme . "://" . $_SERVER['HTTP_HOST'];

                    // Remplacement des liens internes
                    $response = str_replace($source_base, $public_domain, $response);

                    header('Content-Type: audio/x-mpegurl');
                    header('Content-Disposition: attachment; filename="playlist.m3u"');
                    echo $response;
                    exit; // Stoppe l'exécution ici
                }
            }
        } catch (Exception $e) {
            error_log("Proxy Error: " . $e->getMessage());
        }
    }
    
    ob_clean();
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Erreur : Impossible d'identifier la source ou accès refusé.";
    exit;
}

// 2. Interception pour le DIRECT (Live)[cite: 2]
if (preg_match('#^/live/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {[cite: 2]
    $_GET['username'] = $matches[1];[cite: 2]
    $_GET['password'] = $matches[2];[cite: 2]
    $_GET['stream']   = $matches[3];[cite: 2]
    $_GET['extension']= $matches[4];[cite: 2]
    require __DIR__ . '/live.php';[cite: 2]
    exit;[cite: 2]
}

// 3. Interception pour la VOD (Films)[cite: 2]
if (preg_match('#^/movie/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {[cite: 2]
    $_GET['username'] = $matches[1];[cite: 2]
    $_GET['password'] = $matches[2];[cite: 2]
    $_GET['stream']   = $matches[3];[cite: 2]
    $_GET['extension']= $matches[4];[cite: 2]
    require __DIR__ . '/vod.php';[cite: 2]
    exit;[cite: 2]
}

// 4. Interception pour les SÉRIES[cite: 2]
if (preg_match('#^/series/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {[cite: 2]
    $_GET['username'] = $matches[1];[cite: 2]
    $_GET['password'] = $matches[2];[cite: 2]
    $_GET['stream']   = $matches[3];[cite: 2]
    $_GET['extension']= $matches[4];[cite: 2]
    require __DIR__ . '/series.php';[cite: 2]
    exit;[cite: 2]
}

// Comportement par défaut : charger le fichier PHP demandé s'il existe[cite: 2]
$file = __DIR__ . $path;[cite: 2]
if (is_file($file)) {[cite: 2]
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {[cite: 2]
        require $file;[cite: 2]
        exit;[cite: 2]
    }
    return false;[cite: 2]
}

// Sécurité par défaut : Rediriger vers l'API si le lien n'est pas clair[cite: 2]
if (strpos($path, 'player_api.php') !== false) {[cite: 2]
    require __DIR__ . '/player_api.php';[cite: 2]
    exit;[cite: 2]
}

return false;[cite: 2]
?>
