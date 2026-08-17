<?php
/**
 * G-PANEL Stalker/MAG integration.
 * Supports common portal.php and load.php variants.
 */

function stalker_normalize_mac($mac) {
    $mac = strtoupper(trim((string)$mac));
    $mac = preg_replace('/[^0-9A-F:]/i', '', $mac);
    return $mac;
}

function stalker_normalize_portal($portal) {
    $portal = trim((string)$portal);
    if ($portal === '') return '';
    $portal = preg_replace('#\s+#', '', $portal);
    $portal = rtrim($portal, '/');
    $portal = preg_replace(
        '#/(?:c(?:/portal\.php)?|portal\.php|stalker_portal(?:/server/load\.php)?|stb(?:/server/load\.php)?)$#i',
        '',
        $portal
    );
    return rtrim($portal, '/');
}

function stalker_build_url($base, $path, array $params = []) {
    $url = rtrim((string)$base, '/') . '/' . ltrim($path, '/');
    return $params ? $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : $url;
}

function stalker_request($url, array $headers = [], $timeout = 30) {
    $ch = curl_init($url);
    $defaultHeaders = [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Connection: keep-alive',
        'X-User-Agent: Model: MAG250; Link: Ethernet',
        'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 4 rev: 1812 Safari/533.3',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $err) return ['ok'=>false,'error'=>$err ?: 'cURL error','http'=>$http];
    return ['ok'=>($http >= 200 && $http < 400),'body'=>$body,'http'=>$http];
}

function stalker_headers($mac, $token = '', $portal = '') {
    $headers = [
        'Cookie: mac=' . $mac . '; stb_lang=en; timezone=Europe/Paris',
        'X-Device-Mac: ' . $mac,
        'Accept: application/json, text/javascript, */*; q=0.01',
    ];
    if ($portal !== '') {
        $headers[] = 'Referer: ' . rtrim($portal, '/') . '/c/';
        $headers[] = 'Origin: ' . rtrim($portal, '/');
    }
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    return $headers;
}

function stalker_decode($body) {
    $data = json_decode((string)$body, true);
    if (is_array($data)) return $data;
    $body = (string)$body;
    $start = strpos($body, '{');
    $end = strrpos($body, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $data = json_decode(substr($body, $start, $end - $start + 1), true);
        if (is_array($data)) return $data;
    }
    return null;
}

function stalker_handshake($portal, $mac) {
    $mac = stalker_normalize_mac($mac);
    $portal = stalker_normalize_portal($portal);
    if ($portal === '' || $mac === '') return ['ok'=>false,'error'=>'Host ou MAC Stalker manquant.'];

    $paths = [
        '/c/portal.php',
        '/portal.php',
        '/stalker_portal/server/load.php',
        '/stb/server/load.php',
    ];

    $diagnostics = [];
    foreach ($paths as $path) {
        $params = [
            'type'=>'stb',
            'action'=>'handshake',
            'token'=>'',
            'prehash'=>'0',
            'JsHttpRequest'=>'1-xml',
        ];
        $url = stalker_build_url($portal, $path, $params);
        $r = stalker_request($url, stalker_headers($mac, '', $portal), 25);
        if (!$r['ok']) {
            $diagnostics[] = $path . ' HTTP ' . ($r['http'] ?? 0);
            continue;
        }
        $data = stalker_decode($r['body']);
        $token = is_array($data) ? ($data['js']['token'] ?? $data['token'] ?? '') : '';
        if (is_string($token) && trim($token) !== '') {
            return ['ok'=>true,'token'=>trim($token),'path'=>$path,'data'=>$data];
        }
        $preview = trim(preg_replace('/\s+/', ' ', strip_tags((string)$r['body'])));
        $diagnostics[] = $path . ' réponse sans token' . ($preview !== '' ? ': ' . substr($preview,0,160) : '');
    }
    return ['ok'=>false,'error'=>'Handshake Stalker impossible. Vérifiez le Host et la MAC. ' . implode(' | ', $diagnostics)];
}

function stalker_load($portal, $mac, $token, $type, $action, array $extra = [], $preferredPath = '') {
    $portal = stalker_normalize_portal($portal);
    $paths = [];
    if ($preferredPath !== '') $paths[] = $preferredPath;
    foreach (['/c/portal.php','/stalker_portal/server/load.php','/stb/server/load.php'] as $p) {
        if (!in_array($p, $paths, true)) $paths[] = $p;
    }

    foreach ($paths as $path) {
        $params = array_merge([
            'type'=>$type,
            'action'=>$action,
            'JsHttpRequest'=>'1-xml',
        ], $extra);
        $r = stalker_request(
            stalker_build_url($portal, $path, $params),
            stalker_headers($mac, $token, $portal)
        );
        if (!$r['ok']) continue;
        $data = stalker_decode($r['body']);
        if (is_array($data)) {
            return ['ok'=>true,'data'=>$data,'path'=>$path];
        }
    }
    return ['ok'=>false,'error'=>'Aucune réponse JSON valide du portail Stalker pour '.$action.'.'];
}

function stalker_js_list($data) {
    if (!is_array($data)) return [];
    $js = $data['js'] ?? $data;
    if (is_array($js) && isset($js['data']) && is_array($js['data'])) return $js['data'];
    if (is_array($js)) {
        $keys = array_keys($js);
        if ($keys === range(0, count($keys)-1)) return $js;
    }
    return [];
}

function stalker_resolve_stream($portal, $mac, $token, $cmd, $preferredPath = '') {
    $portal = stalker_normalize_portal($portal);
    $cmd = trim((string)$cmd);
    if ($cmd === '') return ['ok'=>false,'error'=>'Commande Stalker vide.'];

    $candidate = preg_replace('/^ff(?:mpeg|rt)\s+/i', '', $cmd);
    if (preg_match('#^https?://#i', $candidate)) {
        return ['ok'=>true,'url'=>$candidate,'raw'=>$candidate];
    }

    $result = stalker_load($portal, $mac, $token, 'itv', 'create_link', [
        'cmd'=>$cmd,
        'series'=>'0',
        'forced_storage'=>'undefined',
        'disable_ad'=>'0',
    ], $preferredPath);

    if (!$result['ok']) return $result;

    $js = $result['data']['js'] ?? [];
    $url = '';
    if (is_string($js)) $url=$js;
    elseif (is_array($js)) {
        $url=$js['cmd'] ?? $js['url'] ?? $js['link'] ?? $js['stream_url'] ?? '';
    }

    $url=trim((string)$url);
    $url=preg_replace('/^ff(?:mpeg|rt)\s+/i','',$url);

    if (!preg_match('#^https?://#i',$url) && is_array($js)) {
        foreach (['cmd','url','link','stream_url'] as $k) {
            if (!empty($js[$k])) {
                $tmp=preg_replace('/^ff(?:mpeg|rt)\s+/i','',trim((string)$js[$k]));
                if (preg_match('#^https?://#i',$tmp)) {$url=$tmp;break;}
            }
        }
    }

    if (!preg_match('#^https?://#i',$url)) {
        return ['ok'=>false,'error'=>'create_link Stalker n’a pas retourné une URL vidéo exploitable.','data'=>$result['data']];
    }
    return ['ok'=>true,'url'=>$url,'raw'=>$js];
}
?>
