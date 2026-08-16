<?php
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

header('Content-Type: application/json; charset=utf-8');

$user = isset($_REQUEST['username']) ? trim(strtolower($_REQUEST['username'])) : '';
$pass = isset($_REQUEST['password']) ? trim($_REQUEST['password']) : '';
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'user_info';
$cat_id = isset($_REQUEST['category_id']) ? $_REQUEST['category_id'] : '';

// --- AUTHENTIFICATION CLIENT VIA LA BASE DE DONNÉES ---
$stmt = $pdo->prepare("SELECT * FROM clients WHERE LOWER(username) = ? AND password = ? AND active = 1");
$stmt->execute([$user, $pass]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) { 
    echo json_encode([]); 
    exit; 
}

// --- FONCTION PROXY CURL ---
function fetch_data_proxy($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IPTVSmartersPro'); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: */*', 'Connection: keep-alive']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$base_proxy_url = "https://" . $_SERVER['HTTP_HOST'];

// ==========================================
// 1. GESTION DES CHAÎNES EN DIRECT (LIVE)
// ==========================================
if ($action === 'get_live_categories') {
    $stmt = $pdo->query("SELECT DISTINCT c.category_id, c.category_name, c.parent_id FROM categories c INNER JOIN streams s ON c.category_id = s.category_id WHERE s.stream_type = 'live' AND c.visible = 1 AND s.visible = 1");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = array_map(function($c) { 
        return [
            'category_id' => (string)$c['category_id'], 
            'category_name' => $c['category_name'], 
            'parent_id' => (int)$c['parent_id']
        ]; 
    }, $categories);
    echo json_encode($result);
} 
elseif ($action === 'get_live_streams') {
    if ($cat_id !== '' && $cat_id !== 'all' && $cat_id !== '0') {
        $stmt = $pdo->prepare("SELECT s.stream_id, s.stream_name, s.stream_icon, s.stream_type, s.category_id FROM streams s INNER JOIN categories c ON s.category_id = c.category_id WHERE s.stream_type = 'live' AND s.visible = 1 AND c.visible = 1 AND s.category_id = ?");
        $stmt->execute([$cat_id]);
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $streams = $pdo->query("SELECT s.stream_id, s.stream_name, s.stream_icon, s.stream_type, s.category_id FROM streams s INNER JOIN categories c ON s.category_id = c.category_id WHERE s.stream_type = 'live' AND s.visible = 1 AND c.visible = 1")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $result = array_map(function($s) use ($base_proxy_url, $user, $pass) {
        return [
            'num' => 0, 
            'name' => $s['stream_name'], 
            'stream_type' => 'live', 
            'stream_id' => (int)$s['stream_id'], 
            'stream_icon' => $s['stream_icon'] ?? '', 
            'category_id' => (string)($s['category_id'] ?? 1), 
            'epg_channel_id' => null, 
            'added' => (string)time(), 
            'custom_sid' => '', 
            'tv_archive' => 0, 
            'direct_source' => $base_proxy_url . '/live.php?username=' . urlencode($user) . '&password=' . urlencode($pass) . '&stream=' . $s['stream_id'] . '&extension=ts', 
            'tv_archive_duration' => 0
        ];
    }, $streams);
    echo json_encode($result);
} 

// ==========================================
// 2. GESTION DES FILMS (VOD)
// ==========================================
elseif ($action === 'get_vod_categories') {
    $stmt = $pdo->query("SELECT DISTINCT c.category_id, c.category_name, c.parent_id FROM categories c INNER JOIN streams s ON c.category_id = s.category_id WHERE s.stream_type = 'movie' AND c.visible = 1 AND s.visible = 1");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = array_map(function($c) { 
        return [
            'category_id' => (string)$c['category_id'], 
            'category_name' => $c['category_name'], 
            'parent_id' => (int)$c['parent_id']
        ]; 
    }, $categories);
    echo json_encode($result);
}
elseif ($action === 'get_vod_streams') {
    if ($cat_id !== '' && $cat_id !== 'all' && $cat_id !== '0') {
        $stmt = $pdo->prepare("SELECT s.stream_id, s.stream_name, s.stream_icon, s.stream_type, s.category_id FROM streams s INNER JOIN categories c ON s.category_id = c.category_id WHERE s.stream_type = 'movie' AND s.visible = 1 AND c.visible = 1 AND s.category_id = ?");
        $stmt->execute([$cat_id]);
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $streams = $pdo->query("SELECT s.stream_id, s.stream_name, s.stream_icon, s.stream_type, s.category_id FROM streams s INNER JOIN categories c ON s.category_id = c.category_id WHERE s.stream_type = 'movie' AND s.visible = 1 AND c.visible = 1")->fetchAll(PDO::FETCH_ASSOC);
    }

    $result = array_map(function($s) use ($base_proxy_url, $user, $pass) {
        return [
            'num' => 1, 
            'name' => (string)$s['stream_name'], 
            'title' => (string)$s['stream_name'], 
            'stream_type' => 'movie', 
            'stream_id' => (int)$s['stream_id'], 
            'stream_icon' => (string)($s['stream_icon'] ?? ''), 
            'plot' => 'Film disponible en streaming.',
            'cast' => 'Non spécifié',
            'director' => 'Non spécifié',
            'genre' => 'Films VOD',
            'releaseDate' => '2026',
            'rating' => '5.0', 
            'rating_5based' => 5, 
            'added' => (string)time(), 
            'is_adult' => '0',
            'category_id' => (string)($s['category_id'] ?? '1'), 
            'container_extension' => 'mp4', 
            'custom_sid' => '', 
            'direct_source' => $base_proxy_url . '/vod.php?username=' . urlencode($user) . '&password=' . urlencode($pass) . '&stream=' . $s['stream_id'] . '&extension=mp4'
        ];
    }, $streams);
    
    echo json_encode($result);
    exit;
}

// ==========================================
// 3. GESTION DES SÉRIES & ÉPISODES
// ==========================================
elseif ($action === 'get_series_categories') {
    $stmt = $pdo->query("SELECT DISTINCT c.category_id, c.category_name, c.parent_id FROM categories c INNER JOIN streams s ON c.category_id = s.category_id WHERE s.stream_type = 'series' AND c.visible = 1 AND s.visible = 1");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = array_map(function($c) { 
        return [
            'category_id' => (string)$c['category_id'], 
            'category_name' => $c['category_name'], 
            'parent_id' => (int)$c['parent_id']
        ]; 
    }, $categories);
    echo json_encode($result);
}
elseif ($action === 'get_series') {
    if ($cat_id !== '' && $cat_id !== 'all' && $cat_id !== '0') {
        $stmt = $pdo->prepare("SELECT s.stream_id, s.stream_name, s.stream_icon, s.stream_type, s.category_id FROM streams s INNER JOIN categories c ON s.category_id = c.category_id WHERE s.stream_type = 'series' AND s.visible = 1 AND c.visible = 1 AND s.category_id = ?");
        $stmt->execute([$cat_id]);
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $streams = $pdo->query("SELECT s.stream_id, s.stream_name, s.stream_icon, s.stream_type, s.category_id FROM streams s INNER JOIN categories c ON s.category_id = c.category_id WHERE s.stream_type = 'series' AND s.visible = 1 AND c.visible = 1")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $result = array_map(function($s) {
        return [
            'num' => 0, 
            'name' => $s['stream_name'], 
            'series_id' => (int)$s['stream_id'], 
            'cover' => $s['stream_icon'] ?? '', 
            'plot' => '', 
            'cast' => '', 
            'director' => '', 
            'genre' => '', 
            'releaseDate' => '', 
            'last_modified' => '0', 
            'rating' => '0', 
            'rating_5based' => 0, 
            'backdrop_path' => [], 
            'youtube_trailer' => '', 
            'episode_run_time' => '', 
            'category_id' => (string)($s['category_id'] ?? 1),
            'added' => (string)time()
        ];
    }, $streams);
    echo json_encode($result);
}
elseif ($action === 'get_series_info') {
    $local_series_id = isset($_REQUEST['series_id']) ? $_REQUEST['series_id'] : '';
    
    $stmt = $pdo->prepare("SELECT s.*, f.url_base, f.user, f.pass, f.type FROM streams s INNER JOIN fournisseurs f ON s.fournisseur_id = f.id WHERE s.stream_id = ?");
    $stmt->execute([$local_series_id]);
    $series = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($series && $series['type'] === 'xtream') {
        $remote_url = sprintf("%s/player_api.php?username=%s&password=%s&action=get_series_info&series_id=%s", rtrim($series['url_base'], '/'), $series['user'], $series['pass'], $series['direct_source']);
        $json = fetch_data_proxy($remote_url);
        
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['episodes']) && is_array($data['episodes'])) {
                $stmt_check = $pdo->prepare("SELECT stream_id FROM streams WHERE fournisseur_id = ? AND stream_type = 'episode' AND direct_source = ?");
                $stmt_insert = $pdo->prepare("INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES (?, ?, ?, 'episode', ?, ?, 1)");
                
                foreach ($data['episodes'] as $season_key => $episodes_list) {
                    if (is_array($episodes_list)) {
                        foreach ($episodes_list as $ep_index => $ep) {
                            if (isset($ep['id'])) {
                                $remote_ep_id = $ep['id'];
                                $ep_title = $ep['title'] ?? 'Episode';
                                $ep_icon = $ep['info']['movie_image'] ?? $series['stream_icon'] ?? '';
                                $ep_ext = !empty($ep['container_extension']) ? $ep['container_extension'] : 'mp4';

                                $stmt_check->execute([$series['fournisseur_id'], $remote_ep_id]);
                                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
                                
                                if ($existing) { 
                                    $local_ep_id = $existing['stream_id']; 
                                } else { 
                                    $stmt_insert->execute([$series['fournisseur_id'], $ep_title, $ep_icon, $series['category_id'], $remote_ep_id]); 
                                    $local_ep_id = $pdo->lastInsertId(); 
                                }
                                
                                $data['episodes'][$season_key][$ep_index]['id'] = (string)$local_ep_id;
                                $data['episodes'][$season_key][$ep_index]['container_extension'] = $ep_ext;
                                $data['episodes'][$season_key][$ep_index]['custom_sid'] = '';
                            }
                        }
                    }
                }
                echo json_encode($data); 
                exit;
            }
        }
    }
    echo json_encode(['episodes' => [], 'info' => []]);
    exit;
}

// ==========================================
// 4. RÉPONSE D'AUTHENTIFICATION HTTPS FORCÉE
// ==========================================
else {
    $host = $_SERVER['HTTP_HOST'];

    echo json_encode([
        'user_info' => [
            'username' => $client['username'], 
            'password' => $client['password'], 
            'auth' => 1, 
            'status' => 'Active', 
            'max_connections' => '1', 
            'allowed_output_formats' => ['m3u8', 'ts']
        ], 
        'server_info' => [
            'url' => $host, 
            'port' => '443', 
            'server_protocol' => 'https'
        ]
    ]);
}
?>
