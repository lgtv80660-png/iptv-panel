<?php
// player/index.php - G-PANEL Web Player IPTV
ini_set('display_errors', 0);

$configPath = __DIR__ . '/../config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

// 1. Gestion d'un Proxy PHP interne pour contourner le Mixed Content (HTTP sur HTTPS)
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_exec($ch);
        curl_close($ch);
        exit;
    }
}

// 2. Récupération de l'URL de la chaîne / VOD
$streamId = $_GET['id'] ?? null;
$streamUrl = $_GET['url'] ?? "";

if ($streamId && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT stream_url FROM streams WHERE id = ? LIMIT 1");
        $stmt->execute([$streamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $streamUrl = $row['stream_url'];
        }
    } catch (Exception $e) {}
}

// URL de fallback / démonstration
if (!$streamUrl) {
    $streamUrl = "https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8";
}

// Utilisation du proxy si l'URL commence par http://
$finalPlaybackUrl = (strpos($streamUrl, 'http://') === 0) 
    ? "index.php?proxy_url=" . urlencode($streamUrl) 
    : $streamUrl;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-PANEL - Web Player</title>
    <!-- Script HLS.js pour la compatibilité universelle -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        body { background-color: #0b0e14; color: #ffffff; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        header {
            padding: 15px 30px; background: #111622; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .logo { font-weight: 800; color: #4364f7; text-decoration: none; font-size: 1.1rem; }
        .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.9rem; }
        .back-btn:hover { color: #fff; }

        .player-container {
            flex-grow: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; background: #000; position: relative;
        }

        video {
            width: 100%; max-width: 1100px; height: 72vh; border-radius: 8px; background: #000; box-shadow: 0 10px 30px rgba(0,0,0,0.8); outline: none;
        }

        .url-form {
            margin-top: 15px; display: flex; gap: 10px; width: 100%; max-width: 1100px;
        }
        .url-input {
            flex-grow: 1; padding: 10px 15px; background: #151922; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 0.9rem;
        }
        .url-btn {
            background: #4364f7; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer;
        }
    </style>
</head>
<body>

    <header>
        <a href="/" class="logo">G-PANEL WEB PLAYER</a>
        <a href="/" class="back-btn">← Retour à la plateforme</a>
    </header>

    <div class="player-container">
        <video id="videoPlayer" controls autoplay></video>

        <!-- Barre de test d'URL directe -->
        <form class="url-form" method="GET" action="index.php">
            <input type="text" name="url" class="url-input" placeholder="Entrez une URL de flux Xtream (.m3u8, .ts, .mp4)" value="<?php echo htmlspecialchars($streamUrl); ?>">
            <button type="submit" class="url-btn">▶ Lire le flux</button>
        </form>
    </div>

    <script>
        const video = document.getElementById('videoPlayer');
        const videoSrc = <?php echo json_encode($finalPlaybackUrl); ?>;

        if (Hls.isSupported()) {
            const hls = new Hls({
                enableWorker: true,
                lowLatencyMode: true
            });
            hls.loadSource(videoSrc);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(e => console.log("Autoplay bloqué par le navigateur :", e));
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Pour Safari Mac/iOS qui gère HLS en natif
            video.src = videoSrc;
            video.addEventListener('loadedmetadata', function() {
                video.play();
            });
        }
    </script>
</body>
</html>
