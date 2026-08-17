<?php
/**
 * Stalker/MAG portal helpers.
 * Supports common /c/portal.php and /stalker_portal/server/load.php endpoints.
 */

function stalker_normalize_mac($mac) {
    $mac = strtoupper(trim((string)$mac));
    $mac = preg_replace('/[^0-9A-F:]/i', '', $mac);
    return $mac;
}

function stalker_normalize_portal($portal) {
    $portal = rtrim(trim((string)$portal), '/');
    $portal = preg_replace('#/(?:c|stalker_portal/server|stalker_portal)$#i', '', $portal);
    return rtrim($portal, '/');
}

function stalker_build_url($base, $path, array $params = []) {
    $base = rtrim((string)$base, '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
}

function stalker_request($url, array $headers = [], $timeout = 30) {
    $ch = curl_init($url);
    $defaultHeaders = [
        'Accept: */*',
        'Connection: keep-alive',
        'X-User-Agent: Model: MAG250; Link: Ethernet',
        'User-Agent: Mozilla/5.0 (MAG250; Stalker Portal)',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $err) return ['ok' => false, 'error' => $err ?: 'cURL error', 'http' => $http];
    return ['ok' => ($http >= 200 && $http < 400), 'body' => $body, 'http' => $http];
}

function stalker_headers($mac, $token = '') {
    $headers = [
        'Cookie: mac=' . rawurlencode($mac) . '; stb_lang=en; timezone=Europe/Paris',
        'Referer: /c/',
    ];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    return $headers;
}

function stalker_decode($body) {
    $data = json_decode((string)$body, true);
    if (is_array($data)) return $data;
    // Some installations wrap JSON in a small JS/HTML response; try to find JSON.
    $start = strpos((string)$body, '{');
    $end = strrpos((string)$body, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $data = json_decode(substr($body, $start, $end - $start + 1), true);
        if (is_array($data)) return $data;
    }
    return null;
}

function stalker_handshake($portal, $mac) {
    $mac = stalker_normalize_mac($mac);
    $portal = stalker_normalize_portal($portal);
    $paths = ['/c/portal.php', '/stalker_portal/server/load.php'];
    foreach ($paths as $path) {
        $params = [
            'type' => 'stb',
            'action' => 'handshake',
            'token' => '',
            'mac' => $mac,
            'JsHttpRequest' => '1-xml',
        ];
        $r = stalker_request(stalker_build_url($portal, $path, $params), stalker_headers($mac));
        if (!$r['ok']) continue;
        $data = stalker_decode($r['body']);
        $token = $data['js']['token'] ?? $data['token'] ?? '';
        if ($token !== '') return ['ok' => true, 'token' => $token, 'path' => $path, 'data' => $data];
    }
    return ['ok' => false, 'error' => 'Handshake Stalker impossible. Vérifiez le Host, la MAC et le portail.'];
}

function stalker_load($portal, $mac, $token, $type, $action, array $extra = []) {
    $portal = stalker_normalize_portal($portal);
    $paths = ['/c/portal.php', '/stalker_portal/server/load.php'];
    foreach ($paths as $path) {
        $params = array_merge([
            'type' => $type,
            'action' => $action,
            'JsHttpRequest' => '1-xml',
            'mac' => $mac,
            'token' => $token,
        ], $extra);
        $r = stalker_request(stalker_build_url($portal, $path, $params), stalker_headers($mac, $token));
        if (!$r['ok']) continue;
        $data = stalker_decode($r['body']);
        if (is_array($data)) return ['ok' => true, 'data' => $data, 'path' => $path];
    }
    return ['ok' => false, 'error' => 'Aucune réponse valide du portail Stalker pour ' . $action];
}

function stalker_js_list($data) {
    if (!is_array($data)) return [];
    $js = $data['js'] ?? $data;
    if (is_array($js) && isset($js['data']) && is_array($js['data'])) return $js['data'];
    if (is_array($js)) { $keys = array_keys($js); if ($keys === range(0, count($keys)-1)) return $js; }
    return is_array($js) ? [$js] : [];
}

function stalker_resolve_stream($portal, $mac, $token, $cmd) {
    $portal = stalker_normalize_portal($portal);
    $cmd = trim((string)$cmd);
    if ($cmd === '') return ['ok' => false, 'error' => 'Commande Stalker vide'];

    // If the portal already supplied a direct HTTP(S) URL, use it.
    $candidate = preg_replace('/^ffmpeg\s+/i', '', $cmd);
    if (preg_match('#^https?://#i', $candidate)) {
        return ['ok' => true, 'url' => $candidate, 'raw' => $candidate];
    }

    $result = stalker_load($portal, $mac, $token, 'itv', 'create_link', [
        'cmd' => $cmd,
        'series' => '0',
    ]);
    if (!$result['ok']) return $result;

    $js = $result['data']['js'] ?? [];
    $url = '';
    if (is_string($js)) $url = $js;
    elseif (is_array($js)) {
        $url = $js['cmd'] ?? $js['url'] ?? $js['link'] ?? $js['stream_url'] ?? '';
    }
    $url = trim((string)$url);
    $url = preg_replace('/^ffmpeg\s+/i', '', $url);

    // Some portals return a nested command.
    if (!preg_match('#^https?://#i', $url) && is_array($js)) {
        foreach (['cmd', 'url', 'link'] as $k) {
            if (!empty($js[$k])) {
                $tmp = preg_replace('/^ffmpeg\s+/i', '', trim((string)$js[$k]));
                if (preg_match('#^https?://#i', $tmp)) { $url = $tmp; break; }
            }
        }
    }

    if (!preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'error' => 'create_link Stalker n’a pas retourné une URL vidéo exploitable.', 'data' => $result['data']];
    }
    return ['ok' => true, 'url' => $url, 'raw' => $js];
}
