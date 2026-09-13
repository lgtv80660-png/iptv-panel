<?php
// player/index.php - G-PANEL Full M3U Xtream Player
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');

// Proxy PHP interne pour contourner le Mixed Content (HTTP sur HTTPS)
if (isset($_GET['proxy_url'])) {
    $rawUrl = urldecode($_GET['proxy_url']);
    if (filter_var($rawUrl, FILTER_VALIDATE_URL)) {
        header('Access-Control-Allow-Origin: *');
        if (strpos($rawUrl, '.m3u8') !== false) {
            header('Content-Type: application/vnd.apple.mpegurl');
        } elseif (strpos($rawUrl, '.mp4') !== false) {
            header('Content-Type: video/mp4');
        } else {
            header('Content-Type: video/mp2t');
        }
        $ch = curl_init($rawUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_exec($ch);
        curl_close($ch);
        exit;
    }
}

// Lien M3U par défaut (le vôtre)
$m3uUrl = $_GET['playlist'] ?? "https://gmztv.vercel.app/get.php?username=akli&password=akli&type=m3u_plus";

// Récupération et parsing du fichier M3U
$channels = [];
$categories = [];

if (!empty($m3uUrl)) {
    $opts = [
        "http" => ["method" => "GET", "header" => "User-Agent: Mozilla/5.0\r\n"],
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ];
    $context = stream_context_create($opts);
    $content = @file_get_contents($m3uUrl, false, $context);

    if ($content) {
        $lines = explode("\n", $content);
        $currentChannel = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#EXTINF:') === 0) {
                $currentChannel = [];
                // Extraction de la catégorie (group-title)
                if (preg_match('/group-title="([^"]+)"/', $line, $matches)) {
                    $currentChannel['category'] = $matches[1];
                } else {
                    $currentChannel['category'] = 'Général';
                }
                // Extraction du logo
                if (preg_match('/tvg-logo="([^"]+)"/', $line, $matches)) {
                    $currentChannel['logo'] = $matches[1];
                } else {
                    $currentChannel['logo'] = '';
                }
                // Extraction du nom de la chaîne
                $parts = explode(',', $line);
                $currentChannel['name'] = end($parts);
            } elseif (!empty($line) && strpos($line, '#') !== 0 && $currentChannel) {
                $currentChannel['url'] = $line;
                $channels[] = $currentChannel;
                $categories[$currentChannel['category']][] = $currentChannel;
                $currentChannel = null;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-PANEL - Web Player IPTV</title>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        body { background-color: #0b0e14; color: #ffffff; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        header {
            padding: 12px 25px; background: #111622; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); flex-shrink: 0;
        }
        .logo { font-weight: 800; color: #4364f7; text-decoration: none; font-size: 1.1rem; }
        .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.85rem; }

        .main-container { display: flex; flex-grow: 1; height: calc(100vh - 55px); overflow: hidden; }

        /* SIDEBAR DES CATÉGORIES ET CHAINES */
        .sidebar { width: 340px; background: #151922; border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; flex-shrink: 0; }
        .search-box { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .search-input { width: 100%; padding: 10px; background: #0b0e14; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 0.9rem; }
        
        .channel-list { flex-grow: 1; overflow-y: auto; padding: 10px; }
        .category-header { font-size: 0.8rem; font-weight: 800; color: #4364f7; text-transform: uppercase; margin: 15px 0 8px 5px; }
        .channel-item { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 6px; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; }
        .channel-item:hover { background: rgba(67, 100, 247, 0.2); }
        .channel-item img { width: 28px; height: 28px; object-fit: contain; border-radius: 4px; background: #000; }

        /* LECTEUR VIDEO PRINCIPAL */
        .player-area { flex-grow: 1; background: #000; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; }
        video { width: 100%; height: 100%; max-height: 85vh; outline: none; }
        .playing-title { position: absolute; top: 15px; left: 20px; background: rgba(0,0,0,0.7); padding: 8px 15px; border-radius: 6px; font-size: 1rem; font-weight: 700; pointer-events: none; z-index: 10; }

        /* Custom Scrollbar */
        .channel-list::-webkit-scrollbar { width: 6px; }
        .channel-list::-webkit-scrollbar-thumb { background: #2a3245; border-radius: 3px; }
    </style>
</head>
<body>

    <header>
        <a href="/" class="logo">G-PANEL PLAYER</a>
        <a href="/" class="back-btn">← Retour à la plateforme</a>
    </header>

    <div class="main-container">
        <!-- SIDEBAR GAUCHE -->
        <div class="sidebar">
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="🔍 Rechercher une chaîne, film, série..." onkeyup="filterChannels()">
            </div>
            <div class="channel-list" id="channelList">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $catName => $items): ?>
                        <div class="category-header"><?php echo htmlspecialchars($catName); ?></div>
                        <?php foreach ($items as $ch): ?>
                            <?php 
                                $proxyUrl = (strpos($ch['url'], 'http://') === 0) ? "index.php?proxy_url=" . urlencode($ch['url']) : $ch['url'];
                            ?>
                            <div class="channel-item" onclick="playStream('<?php echo addslashes($proxyUrl); ?>', '<?php echo addslashes(htmlspecialchars($ch['name'])); ?>')">
                                <?php if (!empty($ch['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($ch['logo']); ?>" onerror="this.style.display='none';">
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($ch['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="padding: 20px; color: #9ca3af; font-size: 0.9rem;">Chargement des chaînes impossible ou playlist vide.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ZONE PLAYER DROITE -->
        <div class="player-area">
            <div class="playing-title" id="playingTitle">Sélectionnez un contenu pour lancer la lecture</div>
            <video id="videoPlayer" controls autoplay></video>
        </div>
    </div>

    <script>
        const video = document.getElementById('videoPlayer');
        let hls = null;

        function playStream(url, name) {
            document.getElementById('playingTitle').innerText = "▶ " + name;

            if (Hls.isSupported()) {
                if (hls) hls.destroy();
                hls = new Hls({ enableWorker: true });
                hls.loadSource(url);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play();
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
                video.play();
            }
        }

        function filterChannels() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const items = document.querySelectorAll('.channel-item');
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });
        }
    </script>
</body>
</html>
