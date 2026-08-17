<?php
// Importation ciblée : un seul fournisseur à la fois.
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', 1); }
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { @ob_end_flush(); }
ob_implicit_flush(1);

require 'config.php';
require_once 'stalker.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(900);
ini_set('memory_limit', '768M');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fetch_data_stream($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'IPTV-Panel/4.0',
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
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

// Migrations idempotentes.
$migrations = [
    "ALTER TABLE categories MODIFY category_id INT AUTO_INCREMENT",
    "ALTER TABLE streams MODIFY stream_id INT AUTO_INCREMENT",
    "ALTER TABLE categories ADD COLUMN visible TINYINT(1) DEFAULT 1",
    "ALTER TABLE streams ADD COLUMN visible TINYINT(1) DEFAULT 1",
    "ALTER TABLE streams ADD COLUMN fournisseur_id INT",
    "ALTER TABLE streams ADD COLUMN container_extension VARCHAR(16) DEFAULT NULL",
    "ALTER TABLE streams ADD COLUMN remote_stream_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE categories ADD COLUMN fournisseur_id INT",
    "ALTER TABLE categories ADD COLUMN remote_category_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE categories ADD COLUMN content_type VARCHAR(20) DEFAULT NULL",
];
foreach ($migrations as $sql) { try { $pdo->query($sql); } catch (Throwable $e) {} }

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs WHERE active = 1 ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedId = (int)($_GET['fournisseur_id'] ?? $_POST['fournisseur_id'] ?? 0);
$selectedProvider = null;
if ($selectedId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fournisseurs WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$selectedId]);
    $selectedProvider = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$selectedProvider):
?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Importation</title>
<style>body{font-family:Arial;background:#0f1219;color:#e8edf5;padding:40px}.box{max-width:720px;margin:auto;background:#1a1d26;border:1px solid #2d3240;border-radius:14px;padding:30px}select,button{width:100%;padding:13px;border-radius:8px;border:1px solid #394152;background:#0f1219;color:#fff;margin-top:10px}button{background:#1677ff;border:0;font-weight:700;cursor:pointer}.small{color:#9aa4b5;font-size:13px;line-height:1.6}</style></head><body><div class="box"><h1>Importer un fournisseur</h1><p class="small">Un seul fournisseur est importé. Les autres sources et leurs filtres ne sont jamais touchés.</p><form method="GET"><select name="fournisseur_id" required><option value="">-- Choisir --</option><?php foreach($fournisseurs as $f): ?><option value="<?= (int)$f['id'] ?>"><?= h($f['nom']) ?> — <?= h(strtoupper($f['type'])) ?></option><?php endforeach; ?></select><button>Lancer l'importation</button></form></div></body></html><?php exit; endif;

$f = $selectedProvider; $fid = (int)$f['id']; $nom = $f['nom']; $type = strtolower($f['type']);
echo '<h2>Importation ciblée : '.h($nom).' ('.h(strtoupper($type)).')</h2>';
echo '<p style="color:#777">Aucun autre fournisseur ne sera modifié. Les états Masquer/Afficher sont conservés.</p>'; flush();

// Sauvegarde des filtres avant l'import.
$oldStreamVisibility = [];
$stmt = $pdo->prepare("SELECT stream_type, direct_source, remote_stream_id, visible FROM streams WHERE fournisseur_id = ?");
$stmt->execute([$fid]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $oldStreamVisibility['remote|'.strtolower((string)$r['stream_type']).'|'.(string)$r['remote_stream_id']] = (int)$r['visible'];
    $oldStreamVisibility['source|'.strtolower((string)$r['stream_type']).'|'.(string)$r['direct_source']] = (int)$r['visible'];
}
$oldCategoryVisibility = [];
$stmt = $pdo->prepare("SELECT category_name, remote_category_id, content_type, visible FROM categories WHERE fournisseur_id = ?");
$stmt->execute([$fid]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $oldCategoryVisibility['remote|'.strtolower((string)$r['content_type']).'|'.(string)$r['remote_category_id']] = (int)$r['visible'];
    $oldCategoryVisibility['name|'.(string)$r['category_name']] = (int)$r['visible'];
}

// Toutes les données distantes sont récupérées AVANT de supprimer quoi que ce soit.
$remoteCats = ['live'=>[], 'movie'=>[], 'series'=>[]];
$remoteStreams = ['live'=>[], 'movie'=>[], 'series'=>[]];

if ($type === 'xtream') {
    $base = rtrim($f['url_base'], '/');
    $api = $base.'/player_api.php?username='.rawurlencode($f['user']).'&password='.rawurlencode($f['pass']);
    $actions = ['get_live_categories'=>'live','get_vod_categories'=>'movie','get_series_categories'=>'series'];
    foreach ($actions as $action=>$kind) {
        $resp = fetch_data_stream($api.'&action='.$action);
        if (is_array($resp) && isset($resp['error'])) { echo '<p style="color:red">⚠️ '.h($resp['error']).'</p>'; continue; }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded)) $remoteCats[$kind] = $decoded;
        echo '<p>Catégories '.h($kind).' : '.count($remoteCats[$kind]).'</p>'; flush();
    }
    $actions = ['get_live_streams'=>'live','get_vod_streams'=>'movie','get_series'=>'series'];
    foreach ($actions as $action=>$kind) {
        $resp = fetch_data_stream($api.'&action='.$action);
        if (is_array($resp) && isset($resp['error'])) { echo '<p style="color:red">⚠️ '.h($resp['error']).'</p>'; continue; }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded)) $remoteStreams[$kind] = $decoded;
        echo '<p>Flux '.h($kind).' : '.count($remoteStreams[$kind]).'</p>'; flush();
    }
    if (empty($remoteCats['live']) && empty($remoteCats['movie']) && empty($remoteCats['series']) && empty($remoteStreams['live']) && empty($remoteStreams['movie']) && empty($remoteStreams['series'])) {
        die('<p style="color:red">❌ Aucun contenu récupéré. Import annulé : les données existantes ont été conservées.</p>');
    }
} elseif ($type === 'stalker') {
    $portal = stalker_normalize_portal($f['url_base']);
    $mac = stalker_normalize_mac($f['mac_address'] ?? '');
    if ($mac === '') die('<p style="color:red">❌ Adresse MAC Stalker manquante.</p>');
    $hs = stalker_handshake($portal, $mac);
    if (!$hs['ok']) die('<p style="color:red">❌ '.h($hs['error']).'<br>Import annulé : les données existantes ont été conservées.</p>');
    $token = $hs['token'];
    echo '<p style="color:green">✔ Handshake Stalker OK.</p>'; flush();
    $genres = stalker_load($portal,$mac,$token,'itv','get_genres');
    if ($genres['ok']) $remoteCats['live'] = stalker_js_list($genres['data']);
    $channels = stalker_load($portal,$mac,$token,'itv','get_all_channels');
    if (!$channels['ok']) die('<p style="color:red">❌ '.h($channels['error']).'<br>Import annulé : les données existantes ont été conservées.</p>');
    $remoteStreams['live'] = stalker_js_list($channels['data']);
    echo '<p>Genres : '.count($remoteCats['live']).' — Chaînes : '.count($remoteStreams['live']).'</p>'; flush();
} elseif ($type === 'm3u') {
    $resp = fetch_data_stream($f['url_base']);
    if (is_array($resp) && isset($resp['error'])) die('<p style="color:red">❌ '.h($resp['error']).'<br>Import annulé.</p>');
    $lines = preg_split('/\r\n|\r|\n/', (string)$resp);
    $current = null;
    $catMap = [];
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
            $current['url']=$line;
            $remoteStreams['live'][]=$current;
            $current=null;
        }
    }
    if (!$remoteStreams['live']) die('<p style="color:red">❌ Aucun flux M3U trouvé. Import annulé.</p>');
    foreach ($remoteStreams['live'] as $item) $remoteCats['live'][]=['id'=>$item['group'],'title'=>$item['group']];
    $uniq=[]; foreach($remoteCats['live'] as $c){$k=(string)$c['id'];$uniq[$k]=$c;} $remoteCats['live']=array_values($uniq);
}

// Nettoyage ciblé uniquement après validation du fournisseur.
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM streams WHERE fournisseur_id = ?")->execute([$fid]);
    $pdo->prepare("DELETE FROM categories WHERE fournisseur_id = ?")->execute([$fid]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die('<p style="color:red">Erreur nettoyage : '.h($e->getMessage()).'</p>');
}

$insertCat = $pdo->prepare("INSERT INTO categories (category_name,parent_id,visible,fournisseur_id,remote_category_id,content_type) VALUES (?,0,?,?,?,?)");
$insertStream = $pdo->prepare("INSERT INTO streams (fournisseur_id,stream_name,stream_icon,stream_type,category_id,direct_source,visible,container_extension,remote_stream_id) VALUES (?,?,?,?,?,?,?,?,?)");

$catMap = ['live'=>[],'movie'=>[],'series'=>[]];
foreach ($remoteCats as $kind=>$cats) {
    foreach ($cats as $c) {
        if (!is_array($c)) continue;
        $rid = (string)($c['category_id'] ?? $c['id'] ?? '');
        $name = (string)($c['category_name'] ?? $c['title'] ?? $c['name'] ?? 'Général');
        if ($rid === '') $rid = $name;
        $display = ($type === 'm3u' || $type === 'stalker' || $type === 'xtream') ? '['.$nom.'] '.$name : $name;
        $vis = $oldCategoryVisibility['remote|'.$kind.'|'.$rid] ?? ($oldCategoryVisibility['name|'.$display] ?? 1);
        $insertCat->execute([$display, $vis, $fid, $rid, $kind]);
        $catMap[$kind][$rid] = (int)$pdo->lastInsertId();
    }
    if (!$catMap[$kind]) {
        $display='['.$nom.'] Général ('.strtoupper($kind).')';
        $vis=$oldCategoryVisibility['name|'.$display] ?? 1;
        $insertCat->execute([$display,$vis,$fid,'default',$kind]);
        $catMap[$kind]['default']=(int)$pdo->lastInsertId();
    }
}

foreach ($remoteStreams as $kind=>$items) {
    $count=0;
    $pdo->beginTransaction();
    try {
        $fallback = reset($catMap[$kind]) ?: 1;
        foreach ($items as $s) {
            if (!is_array($s)) continue;
            if ($type === 'm3u') {
                $name=(string)($s['name']??''); $icon=(string)($s['icon']??''); $source=(string)($s['url']??''); $rid=$source; $catRid=(string)($s['group']??'Général'); $ext=null;
            } elseif ($type === 'stalker') {
                $name=(string)($s['name']??$s['title']??''); $icon=(string)($s['logo']??$s['icon']??''); $source=(string)($s['cmd']??$s['url']??''); $rid=(string)($s['id']??$s['ch_id']??$s['cmd']??''); $catRid=(string)($s['tv_genre_id']??$s['genre_id']??$s['category_id']??''); $ext=null;
            } else {
                $name=(string)($s['name']??''); $icon=(string)($s['stream_icon']??$s['cover']??''); $source=(string)($s['stream_id']??$s['series_id']??''); $rid=$source; $catRid=(string)($s['category_id']??''); $ext=($s['container_extension']??null);
            }
            if ($name==='' || $source==='') continue;
            $localCat=$catMap[$kind][$catRid] ?? $fallback;
            $vis=$oldStreamVisibility['remote|'.$kind.'|'.$rid] ?? ($oldStreamVisibility['source|'.$kind.'|'.$source] ?? 1);
            $insertStream->execute([$fid,$name,$icon,$kind,$localCat,$source,$vis,$ext,$rid]);
            $count++;
            if($count%500===0){$pdo->commit();$pdo->beginTransaction();}
        }
        $pdo->commit();
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); echo '<p style="color:red">Erreur '.h($kind).' : '.h($e->getMessage()).'</p>'; continue; }
    echo '<p style="color:green">✔ '.h($kind).' : '.$count.' éléments</p>'; flush();
}

echo '<hr><p><b style="color:green">✅ Importation de « '.h($nom).' » terminée.</b></p>';
echo '<p><a href="admin.php">← Retour admin</a> &nbsp; <a href="editor.php">Ouvrir les filtres</a></p>';
?>
