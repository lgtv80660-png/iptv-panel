<?php
// Désactiver le buffering pour afficher la progression en direct
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', 1); }
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

require 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
error_reporting(E_ALL);
ini_set('display_errors', 1);

set_time_limit(300);
ini_set('memory_limit', '256M');

function fetch_data_stream($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'); 
    curl_setopt($ch, CURLOPT_ENCODING, ""); 
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Validation de la structure BDD
try { $pdo->query("ALTER TABLE categories ADD COLUMN visible TINYINT(1) DEFAULT 1"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE streams ADD COLUMN visible TINYINT(1) DEFAULT 1"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE streams ADD COLUMN fournisseur_id INT"); } catch(Exception $e){}
try { $pdo->query("ALTER TABLE categories ADD COLUMN fournisseur_id INT"); } catch(Exception $e){}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs WHERE active = 1")->fetchAll();

$stmt_insert_cat = $pdo->prepare("INSERT INTO categories (category_name, parent_id, visible, fournisseur_id) VALUES (?, 0, 1, ?)");
$stmt_insert_stream = $pdo->prepare("INSERT INTO streams (fournisseur_id, stream_name, stream_icon, stream_type, category_id, direct_source, visible) VALUES (?, ?, ?, ?, ?, ?, 1)");

foreach ($fournisseurs as $f) {
    $fid = $f['id'];
    $nom_fournisseur = $f['nom'];
    echo "<h3>Importation source : " . htmlspecialchars($nom_fournisseur) . " (" . strtoupper($f['type']) . ")</h3>";
    flush();

    // Nettoyage ciblé de la source
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

        foreach ($types_cat as $action => $type) {
            $json = fetch_data_stream("$baseUrl&action=$action");
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
            // Création d'une catégorie par défaut si vide
            if (empty($cat_map[$type])) {
                $stmt_insert_cat->execute(["[" . $nom_fournisseur . "] Général (" . strtoupper($type) . ")", $fid]);
                $cat_map[$type]['default'] = $pdo->lastInsertId();
            }
        }

        $types_streams = [
            'get_live_streams' => 'live', 
            'get_vod_streams' => 'movie', 
            'get_series' => 'series'
        ];

        foreach ($types_streams as $action => $type) {
            echo "Téléchargement des flux ($type)... ";
            flush();
            $json = fetch_data_stream("$baseUrl&action=$action");
            if ($json) {
                $streams = json_decode($json, true);
                if (is_array($streams)) {
                    $pdo->beginTransaction();
                    $count = 0;
                    $default_cat = reset($cat_map[$type]) ?: 1;

                    foreach ($streams as $s) {
                        $name = $s['name'] ?? '';
                        $icon = $s['stream_icon'] ?? $s['cover'] ?? '';
                        $remote_cat = trim((string)($s['category_id'] ?? ''));
                        $source_id = $s['stream_id'] ?? $s['series_id'] ?? '';

                        $local_cat = $cat_map[$type][$remote_cat] ?? $default_cat;

                        if ($name && $source_id) {
                            try {
                                $stmt_insert_stream->execute([$fid, $name, $icon, $type, $local_cat, $source_id]);
                                $count++;
                                if ($count % 500 === 0) {
                                    $pdo->commit();
                                    $pdo->beginTransaction();
                                }
                            } catch (Exception $e) {}
                        }
                    }
                    $pdo->commit();
                    echo "OK ($count éléments)<br>";
                    flush();
                }
            }
        }
        echo "✔ Xtream importé.<br><hr>";
        flush();
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
        $handshakeResp = fetch_data_stream($handshakeUrl);
        $handshakeData = json_decode($handshakeResp, true);
        $token = $handshakeData['js']['token'] ?? '';

        $headers = [];
        if ($token) { $headers[] = "Authorization: Bearer " . $token; }
        $headers[] = "Cookie: mac=" . urlencode($mac) . "; stb_lang=fr; timezone=Europe/Paris";

        $cat_map = [];

        // Création forcée d'une catégorie par défaut pour éviter tout crash 'category_id'
        $stmt_insert_cat->execute(["[" . $nom_fournisseur . "] Général (Stalker)", $fid]); 
        $default_cat_id = $pdo->lastInsertId();

        $genresUrl = "$portalUrl/c/portal.php?type=itv&action=get_genres";
        $genresResp = fetch_data_stream($genresUrl, $headers);
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

        $channelsUrl = "$portalUrl/c/portal.php?type=itv&action=get_all_channels";
        $channelsResp = fetch_data_stream($channelsUrl, $headers);
        $channelsData = json_decode($channelsResp, true);

        if (isset($channelsData['js']) && is_array($channelsData['js'])) {
            $channels = isset($channelsData['js']['data']) ? $channelsData['js']['data'] : $channelsData['js'];

            $pdo->beginTransaction();
            $count = 0;
            foreach ($channels as $ch) {
                $name = $ch['name'] ?? '';
                $icon = $ch['logo'] ?? '';
                $remote_cat = (string)($ch['tv_genre_id'] ?? $ch['genre_id'] ?? $ch['category_id'] ?? '');
                $cmd = $ch['cmd'] ?? '';
                
                // Affecte la catégorie spécifique ou la catégorie par défaut (garantit category_id NOT NULL)
                $local_cat = !empty($cat_map[$remote_cat]) ? $cat_map[$remote_cat] : $default_cat_id;

                if ($name && $cmd) {
                    try {
                        $stmt_insert_stream->execute([$fid, $name, $icon, 'live', $local_cat, $cmd]);
                        $count++;
                        if ($count % 500 === 0) {
                            $pdo->commit();
                            $pdo->beginTransaction();
                        }
                    } catch (Exception $e) {}
                }
            }
            $pdo->commit();
            echo "OK ($count chaînes Stalker)<br>";
            flush();
        }
        echo "✔ Stalker importé.<br><hr>";
        flush();
    }
}

echo "<br><b>✅ Importation terminée avec succès !</b>";
?>
