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
