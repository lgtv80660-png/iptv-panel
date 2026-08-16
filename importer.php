<?php
require 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration optimale des ressources sur le serveur Railway
set_time_limit(300);
ini_set('memory_limit', '512M');

function fetch_data($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stb appstore safari/533.3'); 
    curl_setopt($ch, CURLOPT_ENCODING, ""); 
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
    
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Mise à jour de la structure de la base de données si nécessaire
try { $pdo->query("ALTER TABLE categories ADD COLUMN visible TINYINT(1) DEFAULT 1"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE streams ADD COLUMN visible TINYINT(1) DEFAULT 1"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE streams ADD COLUMN fournisseur_id INT"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE categories ADD COLUMN fournisseur_id INT"); } catch(Exception $e){}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs WHERE active = 1")->fetchAll();

foreach ($fournisseurs as $f) {
    $fid = $f['id'];
    $nom_fournisseur = $f['nom'];
    echo "<h3>Importation source : " . htmlspecialchars($nom_fournisseur) . " (" . strtoupper($f['type']) . ")</h3>";

    // Nettoyage ciblé du fournisseur courant uniquement (préserve les IDs des autres sources)
    $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ?")->execute([$fid]);
    $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ?")->execute([$fid]);

    // ==========================================
    // 1. TRAITEMENT XTREAM CODES
    // ==========================================
    if ($f['type'] === 'xtream') {
        $baseUrl = sprintf("%s/player_api.php?username=%s&password=%s", rtrim($f['url_base'], '/'), $f['user'], $f['pass']);
        $cat_map = ['live' => [], 'movie' => [], 'series' => []]; 

        $types_cat = [
            'get_live_categories' => 'live', 
            'get_vod_categories' => 'movie', 
            'get_series_categories' => 'series'
        ];
        
        $stmt_cat = $pdo->prepare("INSERT INTO categories (category_name, parent_id, visible, fournisseur_id) VALUES (?, 0, 1, ?)");

        foreach ($types_cat as $action => $type) {
            $json = fetch_data("$baseUrl&action=$action");
            if ($json) {
                $cats = json_decode($json, true);
                if (is_array($cats)) {
                    foreach ($cats as $c) {
                        if (isset($c['category_id'])) {
                            $remote_id = trim((string)$c['category_id']);
                            $cat_name = $c['category_name'] ?? 'Général';
                            
                            try { 
                                $stmt_cat->execute(["[" . $nom_fournisseur . "] " . $cat_name, $fid]); 
                                $local_id = $pdo->lastInsertId();
                                $cat_map[$type][$remote_id] = $local_id;
                            } catch (Exception $e) {}
                        }
                    }
                }
            }
        }

        $types_streams = [
            'get_live_streams' => 'live', 
            'get_vod_streams' => 'movie', 
            'get_series' => 'series'
        ];

        foreach ($types_streams as $action => $type) {
            $json = fetch_data("$baseUrl&action=$action");
            if ($json) {
                $streams = json_decode($json, true);
                if (is_array($streams)) {
                    $values = [];
                    $params = [];
                    foreach ($streams as $s) {
                        $name = $s['name'] ?? '';
                        $icon = $s['stream_icon'] ?? $s['cover'] ?? '';
                        $remote_cat = trim((string)($s['category_id'] ?? '1'));
                        
                        if (isset($cat_map[$type][$remote_cat])) {
                            $local_cat = $cat_map[$type][$remote_cat];
                            $source_id = $s['stream_id'] ?? $s['series_id'] ?? '';

                            if ($name && $source_id) {
                                $values[] = "(?, ?, ?, ?, ?, ?, 1)";
                                array_push($params, $fid, $name, $icon, $type, $local_cat, $source_id);
                                
                                if (count($values) >= 500) {
                                    $sql = "INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES " . implode(',', $values);
                                    $pdo->prepare($sql)->execute($params);
                                    $values = [];
                                    $params = [];
                                }
                            }
                        }
                    }
                    if (!empty($values)) {
                        $sql = "INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES " . implode(',', $values);
                        $pdo->prepare($sql)->execute($params);
                    }
                }
            }
        }
        echo "✔ Xtream importé avec succès.<br><hr>";
    } 

    // ==========================================
    // 2. TRAITEMENT STALKER (MAG)
    // ==========================================
    elseif ($f['type'] === 'stalker') {
        $portalUrl = rtrim($f['url_base'], '/');
        $mac = $f['mac_address'] ?? '';

        if (empty($mac)) {
            echo "❌ Erreur : Adresse MAC manquante.<br><hr>";
            continue;
        }

        $handshakeUrl = "$portalUrl/c/portal.php?type=stb&action=handshake&mac=" . urlencode($mac);
        $handshakeResp = fetch_data($handshakeUrl);
        $handshakeData = json_decode($handshakeResp, true);
        $token = $handshakeData['js']['token'] ?? '';

        $headers = [];
        if ($token) { $headers[] = "Authorization: Bearer " . $token; }
        $headers[] = "Cookie: mac=" . urlencode($mac) . "; stb_lang=fr; timezone=Europe/Paris";

        $stmt_cat = $pdo->prepare("INSERT INTO categories (category_name, parent_id, visible, fournisseur_id) VALUES (?, 0, 1, ?)");
        $cat_map = [];

        $genresUrl = "$portalUrl/c/portal.php?type=itv&action=get_genres";
        $genresResp = fetch_data($genresUrl, $headers);
        $genresData = json_decode($genresResp, true);

        if (isset($genresData['js']) && is_array($genresData['js'])) {
            $genres = isset($genresData['js']['data']) ? $genresData['js']['data'] : $genresData['js'];
            foreach ($genres as $genre) {
                $remote_id = (string)($genre['id'] ?? '');
                $cat_title = $genre['title'] ?? $genre['name'] ?? 'Inconnu';
                
                if ($remote_id !== '') {
                    try { 
                        $stmt_cat->execute(["[" . $nom_fournisseur . "] " . $cat_title, $fid]); 
                        $local_id = $pdo->lastInsertId();
                        $cat_map[$remote_id] = $local_id;
                    } catch (Exception $e) {}
                }
            }
        }

        $stmt_cat->execute(["[" . $nom_fournisseur . "] Général (Stalker)", $fid]); 
        $default_cat_id = $pdo->lastInsertId();

        $channelsUrl = "$portalUrl/c/portal.php?type=itv&action=get_all_channels";
        $channelsResp = fetch_data($channelsUrl, $headers);
        $channelsData = json_decode($channelsResp, true);

        if (isset($channelsData['js']) && is_array($channelsData['js'])) {
            $channels = isset($channelsData['js']['data']) ? $channelsData['js']['data'] : $channelsData['js'];

            $values = [];
            $params = [];
            foreach ($channels as $ch) {
                $name = $ch['name'] ?? '';
                $icon = $ch['logo'] ?? '';
                
                $remote_cat = (string)($ch['tv_genre_id'] ?? $ch['genre_id'] ?? $ch['category_id'] ?? '');
                $cmd = $ch['cmd'] ?? '';

                $local_cat = $cat_map[$remote_cat] ?? $default_cat_id;

                if ($name && $cmd) {
                    $values[] = "(?, ?, ?, 'live', ?, ?, 1)";
                    array_push($params, $fid, $name, $icon, $local_cat, $cmd);

                    if (count($values) >= 500) {
                        $sql = "INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES " . implode(',', $values);
                        $pdo->prepare($sql)->execute($params);
                        $values = [];
                        $params = [];
                    }
                }
            }

            if (!empty($values)) {
                $sql = "INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES " . implode(',', $values);
                $pdo->prepare($sql)->execute($params);
            }
        }
        echo "✔ Stalker importé avec succès.<br><hr>";
    }
}

echo "<br><b>✅ Importation terminée avec succès !</b>";
?>
