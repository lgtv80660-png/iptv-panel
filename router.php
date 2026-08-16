<?php
// Railway uses PHP's built-in server (`php -S`), which does NOT read .htaccess.
// This router makes Xtream-style /live/..., /movie/... and /series/... URLs work.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Leave real files/directories to PHP's normal static handling.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

// Xtream-style media URLs:
// /live/{username}/{password}/{stream_id}.{ext}
// /movie/{username}/{password}/{stream_id}.{ext}
// /series/{username}/{password}/{stream_id}.{ext}
if (preg_match(
    '#^/(live|movie|series)/([^/]+)/([^/]+)/([^/.]+)\.([A-Za-z0-9]+)$#',
    $path,
    $m
)) {
    $type = $m[1];

    $_GET['username'] = rawurldecode($m[2]);
    $_GET['password'] = rawurldecode($m[3]);
    $_GET['stream']   = rawurldecode($m[4]);
    $_GET['extension'] = strtolower($m[5]);

    $script = __DIR__ . '/' . (
        $type === 'live' ? 'live.php' :
        ($type === 'movie' ? 'vod.php' : 'series.php')
    );

    require $script;
    exit;
}

// Let direct PHP endpoints such as /player_api.php?action=... work.
if (preg_match('#^/[A-Za-z0-9_-]+\.php(?:/.*)?$#', $path)) {
    $file = __DIR__ . $path;
    if (is_file($file)) {
        return false;
    }
}

// Basic root response.
if ($path === '/') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "IPTV Panel API";
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found";
exit;
?>
