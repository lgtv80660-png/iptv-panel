<?php
require_once 'config.php';

// Récupération de la clé TMDB (Vercel ENV avec fallback sur votre clé d'API)
$apiKey = $_ENV['TMDB_API_KEY'] ?? $_SERVER['TMDB_API_KEY'] ?? getenv('TMDB_API_KEY') ?: '7b311a6f43090b24f188272bcc0655b3';

// Fonction de requête TMDB compatible Vercel Serverless (sans dépendre du cURL natif)
function fetchTMDB($endpoint, $apiKey) {
    if (!$apiKey) return ['results' => []];

    $url = "https://api.themoviedb.org/3/{$endpoint}?api_key={$apiKey}&language=fr-FR&page=1";
    
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP\r\n"
        ],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false
        ]
    ];
    
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    return $response ? json_decode($response, true) : ['results' => []];
}

// Récupération des films tendances et séries populaires
$trendingData      = fetchTMDB('trending/movie/week', $apiKey);
$popularSeriesData = fetchTMDB('tv/popular', $apiKey);

$trendingMovies = $trendingData['results'] ?? [];
$popularSeries  = $popularSeriesData['results'] ?? [];

// Film à l'affiche pour le Banner Hero
$heroMovie = $trendingMovies[0] ?? null;
$heroBg = ($heroMovie && !empty($heroMovie['backdrop_path']))
    ? "https://image.tmdb.org/t/p/original" . $heroMovie['backdrop_path'] 
    : "https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?q=80&w=1920";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-PANEL - IPTV Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #0b0e14;
            color: #ffffff;
            overflow-x: hidden;
        }

        /* Header Navigation */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(180deg, rgba(11, 14, 20, 0.95) 0%, rgba(11, 14, 20, 0) 100%);
            z-index: 1000;
        }

        .logo-container img {
            height: 44px;
            width: auto;
            display: block;
            object-fit: contain;
        }

        .btn-admin {
            background: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
            color: #ffffff;
            padding: 10px 24px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(67, 100, 247, 0.4);
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 100, 247, 0.6);
        }

        /* Hero Banner */
        .hero {
            position: relative;
            height: 78vh;
            background: linear-gradient(to top, #0b0e14 8%, transparent 60%),
                        linear-gradient(to right, rgba(11, 14, 20, 0.9) 25%, transparent 75%),
                        url('<?php echo $heroBg; ?>') center/cover no-repeat;
            display: flex;
            align-items: center;
            padding: 0 40px;
        }

        .hero-content {
            max-width: 620px;
        }

        .hero-title {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.7);
        }

        .hero-desc {
            font-size: 1.05rem;
            color: #d1d5db;
            margin-bottom: 25px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-play {
            background-color: #ffffff;
            color: #0b0e14;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 1.05rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .btn-play:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        /* Grid Content */
        .content-section {
            padding: 0 40px;
            margin-top: -60px;
            position: relative;
            z-index: 10;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 18px;
            color: #f3f4f6;
        }

        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 18px;
            margin-bottom: 45px;
        }

        .movie-card {
            position: relative;
            aspect-ratio: 2/3;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .movie-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .movie-card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.8), 0 0 15px rgba(67, 100, 247, 0.3);
            z-index: 5;
        }

        @media (max-width: 768px) {
            header, .hero, .content-section {
                padding-left: 20px;
                padding-right: 20px;
            }
            .hero-title {
                font-size: 2.2rem;
            }
            .logo-container img {
                height: 34px;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="assets/g-panel-logo-dark.png" alt="G-PANEL IPTV Management System">
        </div>
        <a href="/admin.php" class="btn-admin">Espace Admin</a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title"><?php echo htmlspecialchars($heroMovie['title'] ?? $heroMovie['name'] ?? 'Films & Séries'); ?></h1>
            <p class="hero-desc"><?php echo htmlspecialchars($heroMovie['overview'] ?? 'Accédez à vos contenus, chaînes en direct et catalogue VOD en Ultra HD avec G-PANEL.'); ?></p>
            <a href="/admin.php" class="btn-play">▶ Accéder au Player</a>
        </div>
    </section>

    <section class="content-section">
        <h2 class="section-title">🔥 Films Tendances cette semaine</h2>
        <div class="movies-grid">
            <?php foreach (array_slice($trendingMovies, 0, 12) as $movie): ?>
                <?php if (!empty($movie['poster_path'])): ?>
                    <div class="movie-card" title="<?php echo htmlspecialchars($movie['title']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $movie['poster_path']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">📺 Séries Populaires</h2>
        <div class="movies-grid">
            <?php foreach (array_slice($popularSeries, 0, 12) as $show): ?>
                <?php if (!empty($show['poster_path'])): ?>
                    <div class="movie-card" title="<?php echo htmlspecialchars($show['name']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $show['poster_path']; ?>" alt="<?php echo htmlspecialchars($show['name']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

</body>
</html>
