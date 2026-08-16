<?php
require 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Mise à jour de la structure de la BDD si nécessaire
try { $pdo->query("ALTER TABLE categories ADD COLUMN visible TINYINT(1) DEFAULT 1"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE streams ADD COLUMN visible TINYINT(1) DEFAULT 1"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE streams ADD COLUMN fournisseur_id INT"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE categories ADD COLUMN fournisseur_id INT"); } catch(Exception $e){}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs WHERE active = 1")->fetchAll();

// Préparation des requêtes SQL réutilisables (sécurisées contre les caractères spéciaux)
$stmt_insert_cat = $pdo->prepare("INSERT INTO categories (category_name, parent_id, visible, fournisseur_id) VALUES (?, 0, 1, ?)");
$stmt_insert_stream = $pdo->prepare("INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES (?, ?, ?, ?, ?, ?, 1)");

foreach ($fournisseurs as $f) {
    $fid = $f['id'];
    $nom_fournisseur = $f['nom'];
    echo "<h3>Importation source : " . htmlspecialchars($nom_fournisseur) . " (" . strtoupper($f['type']) . ")</h3>";

    // Nettoyage des anciennes données pour ce fournisseur uniquement
    $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ?")->execute([$fid]);
    $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ?")->execute([$fid]);

    // ==========================================
    // 1. IMPORTATION XTREAM CODES
    // ==========================================
    if ($f['type'] === 'xtream') {
        $baseUrl = sprintf("%s/player_api.php?username=%s&password=%s", rtrim($f['url_base'], '/'), $f['user'], $f['pass']);
        $cat_map = ['live' => [], 'movie' => [], 'series' => []]; 

        $types_cat = [
            'get_live_categories' => 'live', 
            'get_vod_categories' => 'movie', 
            'get_series_categories' => 'series'
        ];

        // Importation des catégories
        foreach ($types_cat as $action => $type) {
            $json = fetch_data("$baseUrl&action=$action");
            if ($json) {
                $cats = json_decode($json, true);
                if (is_array($cats)) {
                    foreach ($cats as $c) {
                        if (isset($c['category_id'])) {
                            $remote_id = trim((string)$c['category_id']);
                            $cat_name = "[" . $nom_fournisseur . "] " . ($c['category_name'] ?? 'Général');
                            
                            try { 
                                $stmt_insert_cat->execute([$cat_name, $fid]); 
                                $local_id = $pdo->lastInsertId();
                                $cat_map[$type][$remote_id] = $local_id;
                            } catch (Exception $e) {}
                        }
                    }
                }
            }
        }

        // Importation des flux (Live, Vod, Séries)
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
                    $pdo->beginTransaction(); // Transaction pour accélérer l'insertion
                    foreach ($streams as $s) {
                        $name = $s['name'] ?? '';
                        $icon = $s['stream_icon'] ?? $s['cover'] ?? '';
                        $remote_cat = trim((string)($s['category_id'] ?? '1'));
                        $source_id = $s['stream_id'] ?? $s['series_id'] ?? '';

                        if ($name && $source_id && isset($cat_map[$type][$remote_cat])) {
                            $local_cat = $cat_map[$type][$remote_cat];
                            try {
                                $stmt_insert_stream->execute([$fid, $name, $icon, $type, $local_cat, $source_id]);
                            } catch (Exception $e) {}
                        }
                    }
                    $pdo->commit();
                }
            }
        }
        echo "✔ Source Xtream importée avec succès.<br><hr>";
    } 

    // ==========================================
    // 2. IMPORTATION STALKER (MAG)
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

        $cat_map = [];

        // Genres / Catégories
        $genresUrl = "$portalUrl/c/portal.php?type=itv&action=get_genres";
        $genresResp = fetch_data($genresUrl, $headers);
        $genresData = json_decode($genresResp, true);

        if (isset($genresData['js']) && is_array($genresData['js'])) {
            $genres = isset($genresData['js']['data']) ? $genresData['js']['data'] : $genresData['js'];
            foreach ($genres as $genre) {
                $remote_id = (string)($genre['id'] ?? '');
                $cat_title = "[" . $nom_fournisseur . "] " . ($genre['title'] ?? $genre['name'] ?? 'Inconnu');
                
                if ($remote_id !== '') {
                    try { 
                        $stmt_insert_cat->execute([$cat_title, $fid]); 
                        $local_id = $pdo->lastInsertId();
                        $cat_map[$remote_id] = $local_id;
                    } catch (Exception $e) {}
                }
            }
        }

        $stmt_insert_cat->execute(["[" . $nom_fournisseur . "] Général (Stalker)", $fid]); 
        $default_cat_id = $pdo->lastInsertId();

        // Chaînes
        $channelsUrl = "$portalUrl/c/portal.php?type=itv&action=get_all_channels";
        $channelsResp = fetch_data($channelsUrl, $headers);
        $channelsData = json_decode($channelsResp, true);

        if (isset($channelsData['js']) && is_array($channelsData['js'])) {
            $channels = isset($channelsData['js']['data']) ? $channelsData['js']['data'] : $channelsData['js'];

            $pdo->beginTransaction();
            foreach ($channels as $ch) {
                $name = $ch['name'] ?? '';
                $icon = $ch['logo'] ?? '';
                $remote_cat = (string)($ch['tv_genre_id'] ?? $ch['genre_id'] ?? $ch['category_id'] ?? '');
                $cmd = $ch['cmd'] ?? '';
                $local_cat = $cat_map[$remote_cat] ?? $default_cat_id;

                if ($name && $cmd) {
                    try {
                        $stmt_insert_stream->execute([$fid, $name, $icon, 'live', $local_cat, $cmd]);
                    } catch (Exception $e) {}
                }
            }
            $pdo->commit();
        }
        echo "✔ Source Stalker importée avec succès.<br><hr>";
    }
}

echo "<br><b>✅ Importation terminée avec succès !</b>";
?>
