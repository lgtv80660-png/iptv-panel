<?php
// On récupère le chemin de l'URL demandée
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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
            // 1. Recherche de la ligne dans 'lines'
            $stmt = $pdo->prepare("SELECT * FROM lines WHERE (username = :u1 OR user = :u2) AND (password = :p1 OR pass = :p2) LIMIT 1");
            $stmt->execute(['u1' => $username, 'u2' => $username, 'p1' => $password, 'p2' => $password]);
            $line = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Recherche dans 'users'
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

            // 3. Fallback : Si non trouvé dans la ligne, chercher le serveur principal dans la BDD
            if (!$source_base) {
                try {
                    $stmt = $pdo->query("SELECT domain_name FROM servers LIMIT 1");
                    $server_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($server_row && !empty($server_row['domain_name'])) {
                        $source_base = rtrim($server_row['domain_name'], '/');
                    }
                } catch (Exception $e) {}
            }

            // 4. Exécution du Proxy si la source a été identifiée
            if ($source_base) {
                $upstream_url = $source_base . $_SERVER['REQUEST_URI'];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $upstream_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                // Utilisation d'un User-Agent générique de lecteur IPTV pour bloquer la redirection /landpage
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');

                $response  = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code === 200 && $response !== false) {
                    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $public_domain = $scheme . "://" . $_SERVER['HTTP_HOST'];

                    // Remplace le serveur d'origine par le domaine Railway dans le fichier M3U
                    $response = str_replace($source_base, $public_domain, $response);

                    header('Content-Type: audio/x-mpegurl');
                    header('Content-Disposition: attachment; filename="playlist.m3u"');
                    echo $response;
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log("Proxy M3U Error: " . $e->getMessage());
        }
    }

    // SI LE PROXY N'A PAS PU RÉCUPÉRER LA PLAYLIST
    // On bloque ici avec un message texte propre au lieu de laisser exécuter get.php local
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Erreur : Compte invalide ou impossible de contacter le serveur source.";
    exit;
}

// 2. Interception pour le DIRECT (Live)
if (preg_match('#^/live/cite: 2]([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/live.php';
    exit;
}

// 3. Interception pour la VOD (Films)
if (preg_match('#^/movie/cite: 2]([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/vod.php';
    exit;
}

// 4. Interception pour les SÉRIES
if (preg_match('#^/series/cite: 2]([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/series.php';
    exit;
}

// Comportement par défaut pour les autres fichiers
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
