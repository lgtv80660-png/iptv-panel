<?php
// V7 - Importation Interactive + Correction Stalker VOD/Series
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
set_time_limit(1800);
ini_set('memory_limit', '1024M');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function normalize_ext($ext, $fallback = null) {
    $ext = strtolower(trim((string)$ext));
    $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    return $ext !== '' ? $ext : $fallback;
}
function infer_ext_from_url($url) {
    $path = parse_url((string)$url, PHP_URL_PATH);
    if (!$path) return null;
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    return normalize_ext($ext, null);
}
function fetch_data_stream($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'IPTV-Panel/7.0',
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) return ['error' => 'Erreur cURL : ' . $error];
    if ($http < 200 || $http >= 300) return ['error' => 'Code HTTP reçu : ' . $http];
    return $result;
}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs WHERE active = 1 ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedId = (int)($_GET['fournisseur_id'] ?? $_POST['fournisseur_id'] ?? 0);
$step = (int)($_POST['step'] ?? 1);
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
      <div><div class="gp-import-title">Importation Interactive</div><div class="gp-import-sub">Sélectionnez ce que vous souhaitez importer.</div></div>
    </div>
    <div class="gp-import-actions">
      <a class="btn btn-outline-light" href="admin.php"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
  </div>
  <div class="gp-import-card">
    <img src="assets/g-panel-logo.png" class="import-logo" alt="G-PANEL">
    <h3 class="text-center mb-2">Choisir une source</h3>
    <p class="text-center text-muted mb-4" style="font-size: 14px;">La base de données sera protégée. Vous pourrez choisir les bouquets à importer à l'étape suivante.</p>
    <form method="POST">
        <input type="hidden" name="step" value="2">
        <label class="form-label text-muted">Fournisseur :</label>
        <select name="fournisseur_id" class="form-select bg-dark text-white border-secondary mb-4" required>
            <option value="">-- Sélectionner --</option>
            <?php foreach($fournisseurs as $f): ?>
                <option value="<?= (int)$f['id'] ?>"><?= h($f['nom']) ?> — <?= h(strtoupper($f['type'])) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary w-100 py-2"><i class="fas fa-search me-2"></i>Scanner le fournisseur</button>
    </form>
  </div>
</div>
</body></html>
<?php exit; endif; 

// Variables communes
$f = $selectedProvider; $fid = (int)$f['id']; $nom = $f['nom']; $type = strtolower(trim($f['type']));
$remoteCats = ['live'=>[], 'movie'=>[], 'series'=>[]];
$remoteStreams = ['live'=>[], 'movie'=>[], 'series'=>[]];
$fetchErrors = [];

// ==========================================
// MOTEUR DE CONNEXION (Utilisé pour Étape 2 et 3)
// ==========================================
if ($type === 'xtream') {
    $base = rtrim((string)$f['url_base'], '/');
    $api = $base.'/player_api.php?username='.rawurlencode((string)$f['user']).'&password='.rawurlencode((string)$f['pass']);
    foreach (['get_live_categories'=>'live','get_vod_categories'=>'movie','get_series_categories'=>'series'] as $action=>$kind) {
        $resp = fetch_data_stream($api.'&action='.$action);
        if (is_array($resp) && isset($resp['error'])) { $fetchErrors[]=$kind.' categories: '.$resp['error']; continue; }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded)) $remoteCats[$kind] = $decoded;
    }
    foreach (['get_live_streams'=>'live','get_vod_streams'=>'movie','get_series'=>'series'] as $action=>$kind) {
        $resp = fetch_data_stream($api.'&action='.$action);
        if (is_array($resp) && isset($resp['error'])) { $fetchErrors[]=$kind.' streams: '.$resp['error']; continue; }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded)) $remoteStreams[$kind] = $decoded;
    }
} elseif ($type === 'stalker') {
    $portal = stalker_normalize_portal($f['url_base']);
    $mac = stalker_normalize_mac($f['mac_address'] ?? '');
    $proxy = $f['proxy'] ?? ''; 
    if ($mac === '') die('<p style="color:red">❌ Adresse MAC manquante.</p>');
    
    $hs = stalker_handshake($portal, $mac, $proxy);
    if (!$hs['ok']) die('<p style="color:red">❌ Erreur Handshake: '.h($hs['error']).'</p>');
    $token = $hs['token'];
    
    // 1. DIRECT (Live)
    $genres = stalker_load($portal, $mac, $token, 'itv', 'get_genres', [], $hs['path'], $proxy);
    if ($genres['ok']) $remoteCats['live'] = stalker_js_list($genres['data']);
    $channels = stalker_load($portal, $mac, $token, 'itv', 'get_all_channels', [], $hs['path'], $proxy);
    if ($channels['ok']) $remoteStreams['live'] = stalker_js_list($channels['data']);

    // 2. FILMS (VOD)
    $vod_cats = stalker_load($portal, $mac, $token, 'vod', 'get_categories', [], $hs['path'], $proxy);
    if ($vod_cats['ok']) $remoteCats['movie'] = stalker_js_list($vod_cats['data']);
    
    // On force category=* pour que Stalker n'ignore pas la requête
    $vod_streams = stalker_load($portal, $mac, $token, 'vod', 'get_ordered_list', ['category' => '*'], $hs['path'], $proxy);
    if (!$vod_streams['ok'] || empty(stalker_js_list($vod_streams['data']))) {
        // Plan B : Utilisation de get_video si get_ordered_list est vide
        $vod_streams = stalker_load($portal, $mac, $token, 'vod', 'get_video', ['category' => '*'], $hs['path'], $proxy);
    }
    if ($vod_streams['ok']) $remoteStreams['movie'] = stalker_js_list($vod_streams['data']);

    // 3. SÉRIES
    $series_cats = stalker_load($portal, $mac, $token, 'series', 'get_categories', [], $hs['path'], $proxy);
    if ($series_cats['ok']) $remoteCats['series'] = stalker_js_list($series_cats['data']);
    
    $series_streams = stalker_load($portal, $mac, $token, 'series', 'get_ordered_list', ['category' => '*'], $hs['path'], $proxy);
    if ($series_streams['ok']) $remoteStreams['series'] = stalker_js_list($series_streams['data']);
    
} elseif ($type === 'm3u') {
    $resp = fetch_data_stream($f['url_base']);
    if (is_array($resp) && isset($resp['error'])) die('<p style="color:red">❌ Erreur M3U: '.h($resp['error']).'</p>');
    $lines = preg_split('/\r\n|\r|\n/', (string)$resp);
    $current = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (stripos($line, '#EXTINF:') === 0) {
            $current = ['name'=>'','icon'=>'','group'=>'Général','url'=>'','ext'=>null];
            if (preg_match('/group-title="([^"]*)"/i',$line,$m)) $current['group']=$m[1] ?: 'Général';
            $pos = strrpos($line, ',');
            if ($pos !== false) $current['name']=trim(substr($line,$pos+1));
        } elseif ($current && $line[0] !== '#') {
            $current['url']=$line;
            $remoteStreams['live'][]=$current; 
            $current=null;
        }
    }
    foreach ($remoteStreams['live'] as $item) $remoteCats['live'][]=['id'=>$item['group'],'title'=>$item['group']];
    $uniq=[]; foreach($remoteCats['live'] as $c){$k=(string)$c['id'];$uniq[$k]=$c;} $remoteCats['live']=array_values($uniq);
}

// Calculer le nombre de flux par catégorie (en RAM)
$catCounts = ['live'=>[], 'movie'=>[], 'series'=>[]];
foreach ($remoteStreams as $kind => $items) {
    foreach ($items as $s) {
        $catRid = '';
        if ($type === 'm3u') $catRid = (string)($s['group'] ?? 'Général');
        elseif ($type === 'stalker') $catRid = (string)($s['tv_genre_id'] ?? $s['genre_id'] ?? $s['category_id'] ?? '');
        else $catRid = (string)($s['category_id'] ?? '');
        
        if (!isset($catCounts[$kind][$catRid])) $catCounts[$kind][$catRid] = 0;
        $catCounts[$kind][$catRid]++;
    }
}

// ==========================================
// ÉTAPE 2 : INTERFACE DE SÉLECTION
// ==========================================
if ($step === 2):
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
.badge-count{background:rgba(0,210,255,0.1);color:#00d2ff;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:bold;margin-left:auto;}
.badge-count-empty{background:rgba(255,71,87,0.1);color:#ff4757;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:bold;margin-left:auto;}
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
            <a href="importer.php" class="btn btn-outline-secondary me-2">Annuler</a>
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-download me-2"></i> Lancer l'importation SQL</button>
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
                    
                    $count = $catCounts[$kind][$rid] ?? 0;
                    // On affiche désormais les bouquets même à 0 pour le diagnostic
                    $badgeClass = ($count > 0) ? 'badge-count' : 'badge-count-empty';
                ?>
                <label class="cat-item">
                    <input type="checkbox" name="selected_cats[<?= $kind ?>][]" value="<?= h($rid) ?>">
                    <span style="font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= h($name) ?>"><?= h($name) ?></span>
                    <span class="<?= $badgeClass ?>"><?= $count ?></span>
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
<?php exit; endif; 

// ==========================================
// ÉTAPE 3 : IMPORTATION SQL DES CHOIX
// ==========================================
if ($step === 3):
    $selectedCats = $_POST['selected_cats'] ?? [];
    
    // Garder les visibilités précédentes
    $oldStreamVisibility = [];
    $stmt = $pdo->prepare("SELECT stream_type, remote_stream_id, visible FROM streams WHERE fournisseur_id = ?");
    $stmt->execute([$fid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $oldStreamVisibility['remote|'.strtolower((string)$r['stream_type']).'|'.trim((string)$r['remote_stream_id'])] = (int)$r['visible'];
    }

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>G-PANEL — Import SQL</title><link rel="stylesheet" href="assets/gpanel.css"></head><body style="background:#0f1219;color:#fff;padding:40px;font-family:monospace;">';
    echo '<h2><i class="fas fa-database text-info"></i> Importation SQL en cours pour '.h($nom).'</h2><hr style="border-color:#2d3240;">'; flush();

    $insertCat = $pdo->prepare("INSERT INTO categories (category_name,parent_id,visible,fournisseur_id,remote_category_id,content_type) VALUES (?,0,1,?,?,?)");
    $insertStream = $pdo->prepare("INSERT INTO streams (fournisseur_id,stream_name,stream_icon,stream_type,category_id,direct_source,visible,container_extension,remote_stream_id,vod_plot,vod_cast,vod_director,vod_genre,vod_release_date,vod_rating,vod_rating_5based,vod_added,vod_backdrop,vod_trailer,vod_runtime) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($remoteStreams as $kind => $items) {
        $allowedCats = $selectedCats[$kind] ?? [];
        if (empty($allowedCats)) {
            echo "<p style='color:#8b92a5;'>↷ $kind : ignoré (aucun bouquet sélectionné).</p>"; flush();
            continue;
        }

        echo "<h3 style='color:#00d2ff;'>Traitement $kind…</h3>"; flush();
        $pdo->beginTransaction();
        try {
            // Nettoyage préalable (uniquement le type en cours pour ce fournisseur)
            if ($kind === 'series') $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ? AND stream_type = 'episode'")->execute([$fid]);
            $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ? AND stream_type = ?")->execute([$fid, $kind]);
            $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ? AND content_type = ?")->execute([$fid, $kind]);

            // Création des catégories SQL filtrées
            $catMap = [];
            foreach ($remoteCats[$kind] as $c) {
                $rid = trim((string)($c['category_id'] ?? $c['id'] ?? ''));
                $name = trim((string)($c['category_name'] ?? $c['title'] ?? $c['name'] ?? 'Général'));
                if ($rid === '') $rid = $name;
                
                if (in_array($rid, $allowedCats)) {
                    $display = mb_substr('['.$nom.'] '.$name, 0, 250, 'UTF-8');
                    $insertCat->execute([$display, $fid, $rid, $kind]);
                    $catMap[$rid] = (int)$pdo->lastInsertId();
                }
            }

            // Insertion des flux filtrés
            $count = 0;
            foreach ($items as $s) {
                if (!is_array($s)) continue;
                $plot=$cast=$director=$genre=$release=$rating=$rating5=$added=$backdrop=$trailer=$runtime=null;
                if ($type === 'm3u') {
                    $name=(string)($s['name']??''); $icon=(string)($s['icon']??''); $source=(string)($s['url']??''); $rid=$source; $catRid=(string)($s['group']??'Général'); $ext=normalize_ext($s['ext'] ?? null, infer_ext_from_url($source));
                } elseif ($type === 'stalker') {
                    $name=(string)($s['name']??$s['title']??''); $icon=(string)($s['logo']??$s['icon']??''); $source=(string)($s['cmd']??$s['url']??''); $rid=(string)($s['id']??$s['ch_id']??$s['cmd']??''); $catRid=(string)($s['tv_genre_id']??$s['genre_id']??$s['category_id']??''); $ext=null;
                } else {
                    $name=(string)($s['name']??'');
                    $icon=(string)($s['stream_icon']??$s['cover']??$s['cover_big']??'');
                    $source=(string)($s['stream_id']??$s['series_id']??'');
                    $rid=$source;
                    $catRid=(string)($s['category_id']??'');
                    $ext=normalize_ext($s['container_extension'] ?? $s['container_ext'] ?? null, null);
                    if (!$ext && !empty($s['direct_source'])) $ext=infer_ext_from_url($s['direct_source']);
                }
                
                // Si la catégorie du flux n'a pas été cochée, on ignore ce flux !
                if (!isset($catMap[$catRid])) continue;

                if ($name==='' || $source==='') continue;
                
                // Sécurité base de données
                $name = mb_substr($name, 0, 900, 'UTF-8');
                
                $vis = $oldStreamVisibility['remote|'.$kind.'|'.$rid] ?? 1;
                $insertStream->execute([$fid,$name,$icon,$kind,$catMap[$catRid],$source,$vis,$ext,$rid,$plot,$cast,$director,$genre,$release,$rating,$rating5,$added,$backdrop,$trailer,$runtime]);
                
                $count++;
                if($count % 500 === 0){ echo "<p style='margin:2px 0;'>… ".number_format($count)." $kind insérés en base.</p>"; flush(); }
            }
            $pdo->commit();
            echo "<p style='color:#16a34a; font-weight:bold;'>✔ $kind : $count éléments insérés avec succès.</p><br>"; flush();
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            echo "<p style='color:#dc2626;'>❌ Erreur SQL sur $kind : ".h($e->getMessage())."</p>"; flush();
        }
    }

    echo '<div style="margin-top:40px; padding:20px; background:#16a34a; color:#fff; border-radius:8px; text-align:center;">';
    echo '<h3><i class="fas fa-check-circle"></i> Terminé avec succès !</h3>';
    echo '<p>Votre base de données est désormais propre et optimisée.</p>';
    echo '<a href="admin.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#fff; color:#000; text-decoration:none; border-radius:5px; font-weight:bold;">Retour au Panel</a>';
    echo '</div></body></html>';
endif; 
?>
