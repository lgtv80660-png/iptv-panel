<?php
// V11 - Importation Interactive (Correction Page Blanche)
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', 1); }
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { @ob_end_flush(); }
ob_implicit_flush(1);

require 'config.php';
require 'db_migrations.php';
require_once 'stalker.php';
ensure_panel_schema($pdo);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(3600);
ini_set('memory_limit', '2048M');

// Fonctions globales
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('normalize_ext')) {
    function normalize_ext($ext, $fallback = null) {
        $ext = strtolower(trim((string)$ext));
        $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
        return $ext !== '' ? $ext : $fallback;
    }
}
if (!function_exists('infer_ext_from_url')) {
    function infer_ext_from_url($url) {
        $path = parse_url((string)$url, PHP_URL_PATH);
        if (!$path) return null;
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return normalize_ext($ext, null);
    }
}
if (!function_exists('fetch_data_stream')) {
    function fetch_data_stream($url, $headers = []) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'IPTV-Panel/11.0',
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
if (!function_exists('process_and_insert_streams')) {
    function process_and_insert_streams($pdo, $fid, $kind, $type, $items, $catMap, $insertStream) {
        $count = 0;
        foreach ($items as $s) {
            if (!is_array($s)) continue;
            $plot=$cast=$director=$genre=$release=$rating=$rating5=$added=$backdrop=$trailer=$runtime=null;
            
            if ($type === 'm3u') {
                $name=(string)($s['name']??''); $icon=(string)($s['icon']??''); $source=(string)($s['url']??''); $rid=$source; $catRid=(string)($s['group']??'Général'); $ext=normalize_ext($s['ext'] ?? null, infer_ext_from_url($source));
            } elseif ($type === 'stalker') {
                $name=(string)($s['name']??$s['title']??''); $icon=(string)($s['logo']??$s['icon']??''); $source=(string)($s['cmd']??$s['url']??''); $rid=(string)($s['id']??$s['ch_id']??$s['cmd']??''); $catRid=(string)($s['category_id']??$s['tv_genre_id']??$s['genre_id']??$s['cat_id']??''); $ext=null;
            } else {
                $name=(string)($s['name']??''); $icon=(string)($s['stream_icon']??$s['cover']??$s['cover_big']??''); $source=(string)($s['stream_id']??$s['series_id']??''); $rid=$source; $catRid=(string)($s['category_id']??''); $ext=normalize_ext($s['container_extension'] ?? $s['container_ext'] ?? null, null); if (!$ext && !empty($s['direct_source'])) $ext=infer_ext_from_url($s['direct_source']);
            }
            
            if (!isset($catMap[$catRid]) || $name==='' || $source==='') continue;
            
            $name = mb_substr($name, 0, 900, 'UTF-8');
            $insertStream->execute([$fid,$name,$icon,$kind,$catMap[$catRid],$source,1,$ext,$rid,$plot,$cast,$director,$genre,$release,$rating,$rating5,$added,$backdrop,$trailer,$runtime]);
            $count++;
        }
        return $count;
    }
}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs WHERE active = 1 ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedId = (int)($_GET['fournisseur_id'] ?? $_POST['fournisseur_id'] ?? 0);

// CORRECTION DU BUG : Si on vient de admin.php avec un fournisseur, on passe à l'étape 2
if ($selectedId > 0 && !isset($_POST['step'])) {
    $step = 2;
} else {
    $step = (int)($_POST['step'] ?? 1);
}

$selectedProvider = null;
if ($selectedId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fournisseurs WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$selectedId]);
    $selectedProvider = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==========================================
// ÉTAPE 1 : CHOIX DU FOURNISSEUR
// ==========================================
if (!$selectedProvider):
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>G-PANEL — Importation</title><link rel="stylesheet" href="assets/gpanel.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.gp-import-shell{max-width:1180px;margin:28px auto;padding:0 18px}
.gp-import-top{position:sticky;top:0;z-index:1000;display:flex;justify-content:space-between;align-items:center;gap:18px;padding:12px 18px;margin:-24px -24px 24px;border-bottom:1px solid rgba(127,157,190,.18);backdrop-filter:blur(14px)}
.gp-import-brand{display:flex;align-items:center;gap:12px}
.gp-import-brand img{height:34px;width:auto}
.gp-import-title{font-weight:800;font-size:20px;color:var(--gp-text,#eef6ff)}
.gp-import-sub{font-size:12px;color:var(--gp-muted,#8ea0b5)}
.gp-import-actions{display:flex;gap:8px;flex-wrap:wrap}
.gp-import-card{background:var(--gp-panel,rgba(15,27,45,.92));border:1px solid var(--gp-border,#26374d);border-radius:16px;padding:30px;box-shadow:0 14px 40px rgba(0,0,0,.18); max-width: 700px; margin: 0 auto;}
.import-logo{width:180px;display:block;margin:0 auto 15px;}
</style>
</head><body>
<div class="gp-import-shell">
  <div class="gp-import-top">
    <div class="gp-import-brand">
      <img src="assets/g-panel-logo.png" alt="G-PANEL">
      <div><div class="gp-import-title">Importation Interactive (V11)</div><div class="gp-import-sub">Sélectionnez ce que vous souhaitez importer.</div></div>
    </div>
    <div class="gp-import-actions">
      <a class="btn btn-outline-light" href="admin.php"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
  </div>
  <div class="gp-import-card">
    <img src="assets/g-panel-logo.png" class="import-logo" alt="G-PANEL">
    <h3 class="text-center mb-2">Choisir une source</h3>
    <p class="text-center text-muted mb-4" style="font-size: 14px;">Le système chargera uniquement la liste des catégories pour un affichage instantané.</p>
    <form method="POST">
        <input type="hidden" name="step" value="2">
        <label class="form-label text-muted">Fournisseur :</label>
        <select name="fournisseur_id" class="form-select bg-dark text-white border-secondary mb-4" required>
            <option value="">-- Sélectionner --</option>
            <?php foreach($fournisseurs as $f): ?>
                <option value="<?= (int)$f['id'] ?>"><?= h($f['nom']) ?> — <?= h(strtoupper($f['type'])) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary w-100 py-2"><i class="fas fa-bolt me-2"></i>Chargement Rapide</button>
    </form>
  </div>
</div>
</body></html>
<?php exit; endif; 

if ($step > 1) { echo "<!-- " . str_repeat("KEEP_ALIVE", 100) . " -->\n"; flush(); }

$f = $selectedProvider; $fid = (int)$f['id']; $nom = $f['nom']; $type = strtolower(trim($f['type']));
$remoteCats = ['live'=>[], 'movie'=>[], 'series'=>[]];

// ==========================================
// ÉTAPE 2 : CHARGEMENT DES CATÉGORIES (Rapide)
// ==========================================
if ($step === 2) {
    if ($type === 'xtream') {
        $base = rtrim((string)$f['url_base'], '/');
        $api = $base.'/player_api.php?username='.rawurlencode((string)$f['user']).'&password='.rawurlencode((string)$f['pass']);
        foreach (['get_live_categories'=>'live','get_vod_categories'=>'movie','get_series_categories'=>'series'] as $action=>$kind) {
            $resp = fetch_data_stream($api.'&action='.$action);
            if ($resp) { $dec = json_decode($resp, true); if(is_array($dec)) $remoteCats[$kind] = $dec; }
        }
    } elseif ($type === 'stalker') {
        $portal = stalker_normalize_portal($f['url_base']);
        $mac = stalker_normalize_mac($f['mac_address'] ?? '');
        $proxy = $f['proxy'] ?? ''; 
        if ($mac === '') die('<p style="color:red">❌ Adresse MAC manquante.</p>');
        $hs = stalker_handshake($portal, $mac, $proxy);
        if (!$hs['ok']) die('<p style="color:red">❌ Erreur Handshake: '.h($hs['error']).'</p>');
        $token = $hs['token'];
        
        $genres = stalker_load($portal, $mac, $token, 'itv', 'get_genres', [], $hs['path'], $proxy);
        if ($genres['ok']) $remoteCats['live'] = stalker_js_list($genres['data']);
        
        $vod_cats = stalker_load($portal, $mac, $token, 'vod', 'get_categories', [], $hs['path'], $proxy);
        if ($vod_cats['ok']) $remoteCats['movie'] = stalker_js_list($vod_cats['data']);
        
        $series_cats = stalker_load($portal, $mac, $token, 'series', 'get_categories', [], $hs['path'], $proxy);
        if ($series_cats['ok']) $remoteCats['series'] = stalker_js_list($series_cats['data']);
        
    } elseif ($type === 'm3u') {
        $resp = fetch_data_stream($f['url_base']);
        if ($resp) {
            $lines = preg_split('/\r\n|\r|\n/', (string)$resp);
            foreach ($lines as $line) {
                if (stripos(trim($line), '#EXTINF:') === 0) {
                    $group = 'Général';
                    if (preg_match('/group-title="([^"]*)"/i',$line,$m)) $group = $m[1] ?: 'Général';
                    $remoteCats['live'][$group] = ['id'=>$group,'title'=>$group];
                }
            }
            $remoteCats['live'] = array_values($remoteCats['live']);
        }
    }

?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>G-PANEL — Sélection</title><link rel="stylesheet" href="assets/gpanel.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{background:#0f1219;color:#eef6ff;}
.top-bar{background:#1a1d26;padding:15px 30px;border-bottom:1px solid #2d3240;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;}
.cat-box{background:#1a1d26;border:1px solid #2d3240;border-radius:10px;padding:20px;margin-bottom:20px;}
.cat-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;margin-top:15px;max-height:400px;overflow-y:auto;padding-right:10px;}
.cat-item{background:#0b1527;border:1px solid #33465e;padding:10px;border-radius:6px;display:flex;align-items:center;gap:10px; cursor:pointer;}
.cat-item:hover{border-color:#00d2ff;}
.cat-item input{cursor:pointer; width: 18px; height: 18px; accent-color: #00d2ff;}
</style>
</head><body>
<form method="POST" id="importForm">
    <input type="hidden" name="fournisseur_id" value="<?= $fid ?>">
    <input type="hidden" name="step" value="3">
    
    <div class="top-bar">
        <div>
            <h4 style="margin:0;"><i class="fas fa-filter text-info me-2"></i> Sélection des bouquets : <?= h($nom) ?></h4>
            <small class="text-muted">Cochez uniquement les contenus à sauvegarder dans MySQL.</small>
        </div>
        <div>
            <a href="admin.php" class="btn btn-outline-secondary me-2">Annuler</a>
            <button type="submit" class="btn btn-primary px-4" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin me-2\'></i> Importation en cours...';"><i class="fas fa-download me-2"></i> Lancer l'importation SQL</button>
        </div>
    </div>

    <div style="padding: 30px;">
        <?php foreach (['live'=>'Direct (Live)', 'movie'=>'Films (VOD)', 'series'=>'Séries'] as $kind => $label): 
            if (empty($remoteCats[$kind])) continue;
        ?>
        <div class="cat-box">
            <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-2">
                <h5 style="margin:0;color:#00d2ff;"><i class="fas fa-folder-open me-2"></i> <?= $label ?></h5>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="toggleAll('<?= $kind ?>')">Tout (dé)cocher</button>
            </div>
            <div class="cat-list" id="list_<?= $kind ?>">
                <?php foreach ($remoteCats[$kind] as $c): 
                    $rid = trim((string)($c['category_id'] ?? $c['id'] ?? ''));
                    $name = trim((string)($c['category_name'] ?? $c['title'] ?? $c['name'] ?? 'Général'));
                    if ($rid === '') $rid = $name;
                ?>
                <label class="cat-item">
                    <input type="checkbox" name="selected_cats[<?= $kind ?>][]" value="<?= h($rid) ?>">
                    <span style="font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= h($name) ?>"><?= h($name) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</form>
<script>
function toggleAll(kind) {
    let checkboxes = document.querySelectorAll('#list_' + kind + ' input[type="checkbox"]');
    let allChecked = Array.from(checkboxes).every(c => c.checked);
    checkboxes.forEach(c => c.checked = !allChecked);
}
</script>
</body></html>
<?php exit; 
}

// ==========================================
// ÉTAPE 3 : IMPORTATION SQL CIBLÉE (Temps Réel)
// ==========================================
if ($step === 3):
    $selectedCats = $_POST['selected_cats'] ?? [];
    
    if (empty($selectedCats['live']) && empty($selectedCats['movie']) && empty($selectedCats['series'])) {
        die("<div style='background:#1a1d26; color:#fff; padding:30px; text-align:center;'><h2>Aucun bouquet sélectionné.</h2><a href='admin.php' style='color:#00d2ff;'>Retour</a></div>");
    }

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>G-PANEL — Import SQL</title><link rel="stylesheet" href="assets/gpanel.css"></head><body style="background:#0f1219;color:#fff;padding:40px;font-family:monospace;">';
    echo '<h2><i class="fas fa-database text-info"></i> Importation SQL en cours pour '.h($nom).'</h2><hr style="border-color:#2d3240;">'; flush();

    $insertCat = $pdo->prepare("INSERT INTO categories (category_name,parent_id,visible,fournisseur_id,remote_category_id,content_type) VALUES (?,0,1,?,?,?)");
    $insertStream = $pdo->prepare("INSERT INTO streams (fournisseur_id,stream_name,stream_icon,stream_type,category_id,direct_source,visible,container_extension,remote_stream_id,vod_plot,vod_cast,vod_director,vod_genre,vod_release_date,vod_rating,vod_rating_5based,vod_added,vod_backdrop,vod_trailer,vod_runtime) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $remoteCats = ['live'=>[], 'movie'=>[], 'series'=>[]];
    $token = ''; $hs = []; $portal = ''; $mac = ''; $proxy = '';
    $api = '';

    if ($type === 'xtream') {
        $base = rtrim((string)$f['url_base'], '/');
        $api = $base.'/player_api.php?username='.rawurlencode((string)$f['user']).'&password='.rawurlencode((string)$f['pass']);
        foreach (['get_live_categories'=>'live','get_vod_categories'=>'movie','get_series_categories'=>'series'] as $action=>$kind) {
            $resp = fetch_data_stream($api.'&action='.$action);
            if ($resp) { $dec = json_decode($resp, true); if(is_array($dec)) $remoteCats[$kind] = $dec; }
        }
    } elseif ($type === 'stalker') {
        $portal = stalker_normalize_portal($f['url_base']);
        $mac = stalker_normalize_mac($f['mac_address'] ?? '');
        $proxy = $f['proxy'] ?? ''; 
        $hs = stalker_handshake($portal, $mac, $proxy);
        if ($hs['ok']) {
            $token = $hs['token'];
            $res = stalker_load($portal, $mac, $token, 'itv', 'get_genres', [], $hs['path'], $proxy);
            if ($res['ok']) $remoteCats['live'] = stalker_js_list($res['data']);
            $res = stalker_load($portal, $mac, $token, 'vod', 'get_categories', [], $hs['path'], $proxy);
            if ($res['ok']) $remoteCats['movie'] = stalker_js_list($res['data']);
            $res = stalker_load($portal, $mac, $token, 'series', 'get_categories', [], $hs['path'], $proxy);
            if ($res['ok']) $remoteCats['series'] = stalker_js_list($res['data']);
        }
    } elseif ($type === 'm3u') {
        $resp = fetch_data_stream($f['url_base']);
        $m3u_lines = $resp ? preg_split('/\r\n|\r|\n/', (string)$resp) : [];
        foreach ($m3u_lines as $line) {
            if (stripos(trim($line), '#EXTINF:') === 0) {
                $group = 'Général';
                if (preg_match('/group-title="([^"]*)"/i',$line,$m)) $group = $m[1] ?: 'Général';
                $remoteCats['live'][$group] = ['id'=>$group,'title'=>$group];
            }
        }
        $remoteCats['live'] = array_values($remoteCats['live']);
    }

    foreach (['live', 'movie', 'series'] as $kind) {
        if (empty($selectedCats[$kind])) continue;

        echo "<h3 style='color:#00d2ff; margin-top:30px;'>Traitement $kind…</h3>"; flush();
        
        try {
            $pdo->beginTransaction();
            if ($kind === 'series') $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ? AND stream_type = 'episode'")->execute([$fid]);
            $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ? AND stream_type = ?")->execute([$fid, $kind]);
            $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ? AND content_type = ?")->execute([$fid, $kind]);

            $catMap = [];
            foreach ($remoteCats[$kind] as $c) {
                $rid = trim((string)($c['category_id'] ?? $c['id'] ?? ''));
                $name = trim((string)($c['category_name'] ?? $c['title'] ?? $c['name'] ?? 'Général'));
                if ($rid === '') $rid = $name;
                if (in_array($rid, $selectedCats[$kind])) {
                    $display = mb_substr('['.$nom.'] '.$name, 0, 250, 'UTF-8');
                    $insertCat->execute([$display, $fid, $rid, $kind]);
                    $catMap[$rid] = (int)$pdo->lastInsertId();
                }
            }
            
            $total_inserted = 0;

            if ($type === 'xtream') {
                $action = ($kind==='live') ? 'get_live_streams' : (($kind==='movie') ? 'get_vod_streams' : 'get_series');
                echo "<p style='color:#8b92a5;'>Téléchargement des flux depuis l'API Xtream...</p>"; flush();
                $resp = fetch_data_stream($api.'&action='.$action);
                if ($resp) {
                    $items = json_decode($resp, true);
                    if (is_array($items)) {
                        $total_inserted += process_and_insert_streams($pdo, $fid, $kind, $type, $items, $catMap, $insertStream);
                    }
                }
            } elseif ($type === 'm3u') {
                echo "<p style='color:#8b92a5;'>Analyse du fichier M3U...</p>"; flush();
                $items = []; $current = null;
                foreach ($m3u_lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    if (stripos($line, '#EXTINF:') === 0) {
                        $current = ['name'=>'','icon'=>'','group'=>'Général','url'=>'','ext'=>null];
                        if (preg_match('/tvg-logo="([^"]*)"/i',$line,$m)) $current['icon']=$m[1];
                        if (preg_match('/group-title="([^"]*)"/i',$line,$m)) $current['group']=$m[1] ?: 'Général';
                        $pos = strrpos($line, ',');
                        if ($pos !== false) $current['name']=trim(substr($line,$pos+1));
                    } elseif ($current && $line[0] !== '#') {
                        $current['url']=$line;
                        $items[] = $current; $current=null;
                    }
                }
                $total_inserted += process_and_insert_streams($pdo, $fid, $kind, $type, $items, $catMap, $insertStream);
                
            } elseif ($type === 'stalker') {
                if ($kind === 'live') {
                    echo "<p style='color:#8b92a5;'>Téléchargement des chaînes Live...</p>"; flush();
                    $res = stalker_load($portal, $mac, $token, 'itv', 'get_all_channels', [], $hs['path'], $proxy);
                    if ($res['ok']) {
                        $total_inserted += process_and_insert_streams($pdo, $fid, $kind, $type, stalker_js_list($res['data']), $catMap, $insertStream);
                    }
                } else {
                    $stalker_type = ($kind === 'movie') ? 'vod' : 'series';
                    foreach ($selectedCats[$kind] as $cat_id) {
                        echo "<p style='margin:2px 0; color:#8ea0b5;'>Téléchargement du bouquet ID : $cat_id...</p>"; flush();
                        
                        $res = stalker_load($portal, $mac, $token, $stalker_type, 'get_ordered_list', ['category' => $cat_id, 'p' => 1, 'limit' => 99999], $hs['path'], $proxy);
                        if ($res['ok']) {
                            $items = stalker_js_list($res['data']);
                            $inserted = process_and_insert_streams($pdo, $fid, $kind, $type, $items, $catMap, $insertStream);
                            echo "<script>window.scrollTo(0,document.body.scrollHeight);</script>"; flush();
                            $total_inserted += $inserted;
                        }
                    }
                }
            }

            $pdo->commit();
            echo "<p style='color:#16a34a; font-weight:bold;'>✔ $kind : $total_inserted éléments insérés avec succès.</p>"; flush();
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            echo "<p style='color:#dc2626;'>❌ Erreur SQL sur $kind : ".h($e->getMessage())."</p>"; flush();
        }
    }

    echo '<div style="margin-top:40px; padding:20px; background:#16a34a; color:#fff; border-radius:8px; text-align:center;">';
    echo '<h3><i class="fas fa-check-circle"></i> Importation terminée avec succès !</h3>';
    echo '<p>Votre base de données contient désormais uniquement les éléments que vous avez sélectionnés.</p>';
    echo '<a href="admin.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#fff; color:#000; text-decoration:none; border-radius:5px; font-weight:bold;">Retour au Panel</a>';
    echo '</div></body></html>';
endif; 
?>
