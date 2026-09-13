<?php
// player/index.php - Lecteur Web G-PANEL
require_once '../config.php';

// Optionnel : Récupération d'une chaîne ou vidéo spécifique depuis MySQL si un ID est passé
$streamId = $_GET['id'] ?? null;
$currentStream = null;

if ($streamId && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM streams WHERE id = ? LIMIT 1");
        $stmt->execute([$streamId]);
        $currentStream = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // En cas d'absence de la table
    }
}

// Exemple d'URL de test si aucune entrée SQL sélectionnée
$defaultStreamUrl = $currentStream['stream_url'] ?? "http://exemple-xtream.com:8080/live/user/pass/1234.m3u8";
$proxyStreamUrl = "proxy.php?url=" . urlencode($defaultStreamUrl);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-PANEL Web Player</title>
    <!-- Video.js CSS & JS -->
    <link href="https://vjs.zencdn.net/8.3.0/video-js.css" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        body { background-color: #0b0e14; color: #ffffff; display: flex; flex-direction: column; height: 100vh; }

        header {
            padding: 15px 30px; background: #111622; border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo { font-weight: 800; color: #4364f7; font-size: 1.2rem; text-decoration: none; }
        .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.9rem; }
        .back-btn:hover { color: #fff; }

        .player-container {
            flex-grow: 1; display: flex; align-items: center; justify-content: center;
            padding: 20px; background: #000;
        }

        .video-js {
            width: 100% !important; max-width: 1100px; height: 70vh !important;
            border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }
    </style>
</head>
<body>

    <header>
        <a href="/" class="logo">G-PANEL WEB PLAYER</a>
        <a href="/" class="back-btn">← Retour au catalogue</a>
    </header>

    <div class="player-container">
        <video 
            id="gpanel-player" 
            class="video-js vjs-big-play-centered vjs-theme-city" 
            controls 
            preload="auto"
            autoplay>
            <source src="<?php echo htmlspecialchars($proxyStreamUrl); ?>" type="application/x-mpegURL">
            <p class="vjs-no-js">
                Votre navigateur ne prend pas en charge la lecture vidéo HTML5.
            </p>
        </video>
    </div>

    <script src="https://vjs.zencdn.net/8.3.0/video.min.js"></script>
    <script>
        const player = videojs('gpanel-player');
    </script>
</body>
</html>