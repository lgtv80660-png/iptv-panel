<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($path, 'get.php') !== false) {
    require_once __DIR__ . '/config.php';

    $user = trim(strtolower((string)($_GET['username'] ?? '')));
    $pass = trim((string)($_GET['password'] ?? ''));

    // Vérification dans la table clients locale
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1 LIMIT 1');
    $stmt->execute([$user, $pass]);
    if (!$stmt->fetch()) {
        http_response_code(401);
        exit('Client introuvable ou inactif.');
    }

    // Récupération des identifiants source
    $stmt = $pdo->query("SELECT url_base, user, pass FROM fournisseurs WHERE type = 'xtream' LIMIT 1");
    $fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fournisseur) {
        http_response_code(404);
        exit('Aucun fournisseur Xtream.');
    }

    $f_url  = rtrim($fournisseur['url_base'], '/');
    $f_user = $fournisseur['user'];
    $f_pass = $fournisseur['pass'];

    $query = $_GET;
    $query['username'] = $f_user;
    $query['password'] = $f_pass;
    $upstream_url = $f_url . '/get.php?' . http_build_query($query);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $upstream_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'VLC/3.0.9 LibVLC/3.0.9');

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response !== false && stripos($response, 'landpage') === false) {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $public_domain = $scheme . "://" . $_SERVER['HTTP_HOST'];

        $search_live = $f_url . '/live/' . $f_user . '/' . $f_pass . '/';
        $replace_live = $public_domain . '/live/' . rawurlencode($user) . '/' . rawurlencode($pass) . '/';
        $response = str_ireplace($search_live, $replace_live, $response);

        $search_movie = $f_url . '/movie/' . $f_user . '/' . $f_pass . '/';
        $replace_movie = $public_domain . '/movie/' . rawurlencode($user) . '/' . rawurlencode($pass) . '/';
        $response = str_ireplace($search_movie, $replace_movie, $response);

        $search_series = $f_url . '/series/' . $f_user . '/' . $f_pass . '/';
        $replace_series = $public_domain . '/series/' . rawurlencode($user) . '/' . rawurlencode($pass) . '/';
        $response = str_ireplace($search_series, $replace_series, $response);

        header('Content-Type: audio/x-mpegurl');
        header('Content-Disposition: attachment; filename="playlist.m3u"');
        echo $response;
        exit;
    } else {
        http_response_code(502);
        exit('Serveur source injoignable ou blocage.');
    }
}

// 1. Interception pour le DIRECT (Live)
if (preg_match('#^/live/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/live.php';
    exit;
}

// 2. Interception pour la VOD (Films)
if (preg_match('#^/movie/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/vod.php';
    exit;
}

// 3. Interception pour les SÉRIES
if (preg_match('#^/series/([^/]+)/([^/]+)/([^/]+)\.(.*)$#i', $path, $matches)) {
    $_GET['username'] = $matches[1];
    $_GET['password'] = $matches[2];
    $_GET['stream']   = $matches[3];
    $_GET['extension']= $matches[4];
    require __DIR__ . '/series.php';
    exit;
}

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
