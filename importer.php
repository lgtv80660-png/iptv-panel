<?php
// V5 - Importation ciblée, transactionnelle et sans perte des filtres.
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
        CURLOPT_USERAGENT => 'IPTV-Panel/5.0',
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
$selectedProvider = null;
if ($selectedId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fournisseurs WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$selectedId]);
    $selectedProvider = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$selectedProvider):
?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>G-PANEL — Importation</title><link rel="stylesheet" href="assets/gpanel.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>.import-shell{max-width:980px;margin:0 auto;padding:28px}.import-card{background:linear-gradient(145deg,rgba(16,31,52,.95),rgba(8,18,33,.95));border:1px solid var(--gp-border);border-radius:20px;padding:30px;box-shadow:var(--gp-shadow)}.import-logo{width:210px;display:block;margin:0 auto 8px}.import-select{font-size:15px!important}.import-btn{width:100%;padding:13px!important}</style>
<style>
.gp-import-shell{max-width:1180px;margin:28px auto;padding:0 18px}
.gp-import-top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px}
.gp-import-brand{display:flex;align-items:center;gap:12px}
.gp-import-brand img{height:34px;width:auto}
.gp-import-title{font-weight:800;font-size:20px;color:var(--gp-text,#eef6ff)}
.gp-import-sub{font-size:12px;color:var(--gp-muted,#8ea0b5)}
.gp-import-actions{display:flex;gap:8px;flex-wrap:wrap}
.gp-import-actions a{display:inline-flex;align-items:center;gap:7px;text-decoration:none}
.gp-import-card{background:var(--gp-panel,rgba(15,27,45,.92));border:1px solid var(--gp-border,#26374d);border-radius:16px;padding:22px;box-shadow:0 14px 40px rgba(0,0,0,.18)}
.gp-import-status{margin-top:16px}
.gp-import-status p{margin:8px 0}
@media(max-width:700px){.gp-import-top{align-items:flex-start;flex-direction:column}.gp-import-actions{width:100%}.gp-import-actions a{flex:1;justify-content:center}}
</style>

<style>
.gp-import-top{position:sticky;top:0;z-index:1000;display:flex;justify-content:space-between;align-items:center;gap:18px;padding:12px 18px;margin:-24px -24px 24px;border-bottom:1px solid rgba(127,157,190,.18);backdrop-filter:blur(14px)}
.gp-import-actions{display:flex;gap:8px;flex-wrap:wrap}
.gp-import-actions .btn{white-space:nowrap}
.gp-import-complete{text-align:center;padding:28px 12px 10px}
.gp-complete-icon{width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;background:#16a34a;color:#fff;font-size:28px;font-weight:800}
.gp-complete-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:20px}
.gp-auto-return{margin-top:16px;color:var(--gp-muted);font-size:13px}
@media(max-width:700px){.gp-import-top{align-items:flex-start;flex-direction:column}.gp-import-actions{width:100%}.gp-import-actions .btn{flex:1}}
</style>
</head><body>
<div class="gp-import-shell">
  <div class="gp-import-top">
    <div class="gp-import-brand">
      <img src="assets/g-panel-logo.png" alt="G-PANEL">
      <div><div class="gp-import-title">Importation & synchronisation</div><div class="gp-import-sub">Une seule source à la fois — les autres fournisseurs restent inchangés.</div></div>
    </div>
    <div class="gp-import-actions"><a class="btn btn-outline-light" href="admin.php"><i class="fas fa-home"></i> Panel</a><a class="btn btn-outline-light" href="editor.php"><i class="fas fa-sliders-h"></i> Éditeur</a><a class="btn btn-outline-info" href="test_stalker.php"><i class="fas fa-plug"></i> Test Stalker</a>
      <a class="btn btn-primary" href="admin.php"><i class="fas fa-arrow-left"></i> Retour au Panel</a><a class="btn btn-outline-info" href="editor.php"><i class="fas fa-sliders-h"></i> Éditeur</a>
    </div>
  </div>
  <div class="gp-import-card">
<div class="import-shell"><div class="import-card"><img src="assets/g-panel-logo.png" class="import-logo" alt="G-PANEL"><div class="gp-brand-mini">IPTV MANAGEMENT SYSTEM</div><div class="gp-page-title"><div><div class="eyebrow">G-PANEL / Synchronisation</div><h1>Importer un fournisseur</h1></div><a href="admin.php" class="gp-chip text-decoration-none"><i class="fas fa-arrow-left"></i> Admin</a></div><p class="small" style="color:var(--gp-muted);margin:18px 0 22px;">Importation ciblée : une seule source est synchronisée. Les autres fournisseurs, leurs contenus et leurs filtres restent inchangés.</p><form method="GET"><label class="form-label">Fournisseur à synchroniser</label><select name="fournisseur_id" class="form-select import-select" required><option value="">-- Choisir un fournisseur --</option><?php foreach($fournisseurs as $f): ?><option value="<?= (int)$f['id'] ?>"><?= h($f['nom']) ?> — <?= h(strtoupper($f['type'])) ?></option><?php endforeach; ?></select><button class="btn btn-primary import-btn mt-3"><i class="fas fa-rotate me-2"></i>Lancer la synchronisation</button></form></div></div><script src="assets/gpanel-ui.js"></script>
</div></div></body></html><?php exit; endif;

$f = $selectedProvider; $fid = (int)$f['id']; $nom = $f['nom']; $type = strtolower(trim($f['type']));
echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>G-PANEL — Synchronisation</title><link rel="stylesheet" href="assets/gpanel.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head><body><div class="import-shell"><div class="import-card"><img src="assets/g-panel-logo.png" class="import-logo" style="width:190px;display:block;margin:0 auto 5px;" alt="G-PANEL"><div class="gp-brand-mini">IPTV MANAGEMENT SYSTEM</div><div class="gp-page-title"><div><div class="eyebrow">G-PANEL / Import en cours</div><h1>Synchronisation de '.h($nom).'</h1></div><span class="gp-chip"><i class="fas fa-server"></i> '.h(strtoupper($type)).'</span></div><div class="glass-panel" style="margin-top:22px;padding:18px!important;">';
echo '<h2 style="font-size:18px;margin:0;">Importation ciblée : '.h($nom).' ('.h(strtoupper($type)).')</h2>';
echo '<p style="color:#777">Un seul fournisseur est remplacé. Les états Masquer/Afficher sont conservés. En cas d\'erreur d\'un type, son ancien contenu est conservé.</p>'; flush();

// Save visibility before replacement. Match by remote ID first, then source/name as fallback.
$oldStreamVisibility = [];
$stmt = $pdo->prepare("SELECT stream_type, direct_source, remote_stream_id, visible FROM streams WHERE fournisseur_id = ?");
$stmt->execute([$fid]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $kind = strtolower((string)$r['stream_type']);
    $rid = trim((string)$r['remote_stream_id']);
    $src = trim((string)$r['direct_source']);
    if ($rid !== '') $oldStreamVisibility['remote|'.$kind.'|'.$rid] = (int)$r['visible'];
    if ($src !== '') $oldStreamVisibility['source|'.$kind.'|'.$src] = (int)$r['visible'];
}
$oldCategoryVisibility = [];
$stmt = $pdo->prepare("SELECT category_name, remote_category_id, content_type, visible FROM categories WHERE fournisseur_id = ?");
$stmt->execute([$fid]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $kind = strtolower((string)$r['content_type']);
    $rid = trim((string)$r['remote_category_id']);
    if ($rid !== '') $oldCategoryVisibility['remote|'.$kind.'|'.$rid] = (int)$r['visible'];
    $oldCategoryVisibility['name|'.(string)$r['category_name']] = (int)$r['visible'];
}

$remoteCats = ['live'=>[], 'movie'=>[], 'series'=>[]];
$remoteStreams = ['live'=>[], 'movie'=>[], 'series'=>[]];
$fetchErrors = [];

if ($type === 'xtream') {
    $base = rtrim((string)$f['url_base'], '/');
    $api = $base.'/player_api.php?username='.rawurlencode((string)$f['user']).'&password='.rawurlencode((string)$f['pass']);
    $actions = ['get_live_categories'=>'live','get_vod_categories'=>'movie','get_series_categories'=>'series'];
    foreach ($actions as $action=>$kind) {
        $resp = fetch_data_stream($api.'&action='.$action);
        if (is_array($resp) && isset($resp['error'])) { $fetchErrors[]=$kind.' categories: '.$resp['error']; continue; }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded)) $remoteCats[$kind] = $decoded;
        echo '<p>Catégories '.h($kind).' : '.count($remoteCats[$kind]).'</p>'; flush();
    }
    $actions = ['get_live_streams'=>'live','get_vod_streams'=>'movie','get_series'=>'series'];
    foreach ($actions as $action=>$kind) {
        $resp = fetch_data_stream($api.'&action='.$action);
        if (is_array($resp) && isset($resp['error'])) { $fetchErrors[]=$kind.' streams: '.$resp['error']; continue; }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded)) $remoteStreams[$kind] = $decoded;
        echo '<p>Flux '.h($kind).' : '.count($remoteStreams[$kind]).'</p>'; flush();
    }
    $total = array_sum(array_map('count', $remoteStreams));
    if ($total === 0) die('<p style="color:red">❌ Aucun contenu récupéré. Import annulé : les données existantes sont conservées.</p>');
    if ($fetchErrors) { echo '<div style="color:#b36b00"><b>Attention :</b><br>'.h(implode(' | ', $fetchErrors)).'</div>'; flush(); }
} elseif ($type === 'stalker') {
    $portal = stalker_normalize_portal($f['url_base']);
    $mac = stalker_normalize_mac($f['mac_address'] ?? '');
    if ($mac === '') die('<p style="color:red">❌ Adresse MAC Stalker manquante.</p>');
    $hs = stalker_handshake($portal, $mac);
    if (!$hs['ok']) die('<p style="color:red">❌ '.h($hs['error']).'<br>Import annulé : les données existantes ont été conservées.</p>');
    $token = $hs['token'];
    echo '<p style="color:green">✔ Handshake Stalker OK — portail utilisé : '.h($hs['path']).'</p>'; flush();
    $genres = stalker_load($portal,$mac,$token,'itv','get_genres',[], $hs['path']);
    if ($genres['ok']) $remoteCats['live'] = stalker_js_list($genres['data']);
    $channels = stalker_load($portal,$mac,$token,'itv','get_all_channels',[], $hs['path']);
    if (!$channels['ok']) die('<p style="color:red">❌ '.h($channels['error']).'<br>Import annulé : les données existantes ont été conservées.</p>');
    $remoteStreams['live'] = stalker_js_list($channels['data']);
    echo '<p>Genres : '.count($remoteCats['live']).' — Chaînes : '.count($remoteStreams['live']).'</p>'; flush();
} elseif ($type === 'm3u') {
    $resp = fetch_data_stream($f['url_base']);
    if (is_array($resp) && isset($resp['error'])) die('<p style="color:red">❌ '.h($resp['error']).'<br>Import annulé.</p>');
    $lines = preg_split('/\r\n|\r|\n/', (string)$resp);
    $current = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (stripos($line, '#EXTINF:') === 0) {
            $current = ['name'=>'','icon'=>'','group'=>'Général','url'=>'','ext'=>null];
            if (preg_match('/tvg-logo="([^"]*)"/i',$line,$m)) $current['icon']=$m[1];
            if (preg_match('/group-title="([^"]*)"/i',$line,$m)) $current['group']=$m[1] ?: 'Général';
            $pos = strrpos($line, ',');
            if ($pos !== false) $current['name']=trim(substr($line,$pos+1));
        } elseif ($current && $line[0] !== '#') {
            $current['url']=$line; $current['ext']=infer_ext_from_url($line);
            $remoteStreams['live'][]=$current; $current=null;
        }
    }
    if (!$remoteStreams['live']) die('<p style="color:red">❌ Aucun flux M3U trouvé. Import annulé.</p>');
    foreach ($remoteStreams['live'] as $item) $remoteCats['live'][]=['id'=>$item['group'],'title'=>$item['group']];
    $uniq=[]; foreach($remoteCats['live'] as $c){$k=(string)$c['id'];$uniq[$k]=$c;} $remoteCats['live']=array_values($uniq);
} else {
    die('<p style="color:red">❌ Type de fournisseur non supporté.</p>');
}

// Import each content type independently. A failure rolls back ONLY that type,
// so existing data from that type remains intact.
$insertCat = $pdo->prepare("INSERT INTO categories (category_name,parent_id,visible,fournisseur_id,remote_category_id,content_type) VALUES (?,0,?,?,?,?)");
$insertStream = $pdo->prepare("INSERT INTO streams (fournisseur_id,stream_name,stream_icon,stream_type,category_id,direct_source,visible,container_extension,remote_stream_id,vod_plot,vod_cast,vod_director,vod_genre,vod_release_date,vod_rating,vod_rating_5based,vod_added,vod_backdrop,vod_trailer,vod_runtime) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

foreach ($remoteStreams as $kind=>$items) {
    if (!$items && empty($remoteCats[$kind])) {
        echo '<p style="color:#777">↷ '.h($kind).' : aucune donnée reçue, ancien contenu conservé.</p>'; flush();
        continue;
    }

    echo '<h3>Traitement '.h($kind).'…</h3>'; flush();
    $pdo->beginTransaction();
    try {
        // Delete episodes before series categories/series streams are replaced.
        if ($kind === 'series') {
            $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ? AND stream_type = 'episode'")->execute([$fid]);
        }
        $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ? AND stream_type = ?")->execute([$fid, $kind]);
        $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ? AND content_type = ?")->execute([$fid, $kind]);

        $catMap = [];
        foreach ($remoteCats[$kind] as $c) {
            if (!is_array($c)) continue;
            $rid = trim((string)($c['category_id'] ?? $c['id'] ?? ''));
            $name = trim((string)($c['category_name'] ?? $c['title'] ?? $c['name'] ?? 'Général'));
            if ($rid === '') $rid = $name;
            if ($name === '') $name = 'Général';
            $display = '['.$nom.'] '.$name;
            $vis = $oldCategoryVisibility['remote|'.$kind.'|'.$rid] ?? ($oldCategoryVisibility['name|'.$display] ?? 1);
            $insertCat->execute([$display, $vis, $fid, $rid, $kind]);
            $catMap[$rid] = (int)$pdo->lastInsertId();
        }
        if (!$catMap) {
            $display='['.$nom.'] Général ('.strtoupper($kind).')';
            $vis=$oldCategoryVisibility['name|'.$display] ?? 1;
            $insertCat->execute([$display,$vis,$fid,'default',$kind]);
            $catMap['default']=(int)$pdo->lastInsertId();
        }

        $fallback = reset($catMap) ?: 0;
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
                if ($kind === 'movie') {
                    $plot=(string)($s['plot']??''); $cast=(string)($s['cast']??''); $director=(string)($s['director']??''); $genre=(string)($s['genre']??'');
                    $release=(string)($s['releaseDate']??$s['releasedate']??''); $rating=(string)($s['rating']??''); $rating5=isset($s['rating_5based'])?(float)$s['rating_5based']:null;
                    $added=(string)($s['added']??''); $backdrop=$s['backdrop_path']??''; if(is_array($backdrop))$backdrop=$backdrop[0]??'';
                    $trailer=(string)($s['youtube_trailer']??''); $runtime=(string)($s['episode_run_time']??'');
                }
            }
            if ($name==='' || $source==='') continue;
            $localCat=$catMap[$catRid] ?? $fallback;
            $vis=$oldStreamVisibility['remote|'.$kind.'|'.$rid] ?? ($oldStreamVisibility['source|'.$kind.'|'.$source] ?? 1);
            $insertStream->execute([$fid,$name,$icon,$kind,$localCat,$source,$vis,$ext,$rid,$plot,$cast,$director,$genre,$release,$rating,$rating5,$added,$backdrop,$trailer,$runtime]);
            $count++;
            if($count % 1000 === 0){ echo '<p>… '.number_format($count).' '.h($kind).' importés</p>'; flush(); }
        }
        $pdo->commit();
        echo '<p style="color:green">✔ '.h($kind).' : '.number_format($count).' éléments importés. Filtres conservés.</p>'; flush();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo '<p style="color:red">❌ '.h($kind).' échoué : '.h($e->getMessage()).'<br>Anciennes données de ce type conservées.</p>'; flush();
    }
}

echo '<hr><div class="gp-import-complete"><div class="gp-complete-icon">✓</div><h2>Importation terminée</h2><p>Le fournisseur <b>'.h($nom).'</b> a été traité. Les autres fournisseurs n\'ont pas été modifiés.</p><div class="gp-complete-actions"><a class="btn btn-primary btn-lg" href="admin.php"><i class="fas fa-home"></i> Retour au Panel</a><a class="btn btn-outline-info btn-lg" href="editor.php"><i class="fas fa-sliders-h"></i> Ouvrir l\'Éditeur</a></div><p class="gp-auto-return">Retour automatique au Panel dans <span id="gp-countdown">15</span> secondes.</p></div>';

echo '</div></div></div>';
?>
<script src="assets/gpanel-ui.js"></script>
<script>
(function(){
  var el=document.getElementById('gp-countdown');
  if(!el) return;
  var n=15;
  var timer=setInterval(function(){
    n--;
    el.textContent=n;
    if(n<=0){
      clearInterval(timer);
      window.location.href='admin.php';
    }
  },1000);
})();
</script>

</body></html>';
?>
