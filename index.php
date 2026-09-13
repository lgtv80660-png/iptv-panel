<?php
require_once 'config.php';

// Récupération de la clé API (Vercel env ou valeur fallback)
$apiKey = getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? 'VOTRE_CLE_API_TMDB_ICI');

// Fonction helper pour interroger TMDB
function fetchTMDB($endpoint, $apiKey) {
    $url = "https://api.themoviedb.org/3/{$endpoint}?api_key={$apiKey}&language=fr-FR&page=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Récupération des données TMDB
$trendingMovies = fetchTMDB('trending/movie/week', $apiKey)['results'] ?? [];
$popularSeries  = fetchTMDB('tv/popular', $apiKey)['results'] ?? [];

// Image Héro (premier film tendance)
$heroMovie = $trendingMovies[0] ?? null;
$heroBg = $heroMovie && isset($heroMovie['backdrop_path']) 
    ? "https://image.tmdb.org/t/p/original" . $heroMovie['backdrop_path'] 
    : "https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?q=80&w=1920";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMZ TV - Movies & Series</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        body { background-color: #141414; color: #ffffff; overflow-x: hidden; }

        header {
            position: fixed; top: 0; width: 100%; padding: 20px 50px;
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
            z-index: 1000;
        }
        .logo { font-size: 1.8rem; font-weight: 900; color: #e50914; text-transform: uppercase; }
        .btn-admin { background-color: #e50914; color: #fff; padding: 8px 20px; text-decoration: none; font-weight: 600; border-radius: 4px; }

        .hero {
            position: relative; height: 75vh;
            background: linear-gradient(to top, #141414 5%, transparent 60%),
                        linear-gradient(to right, rgba(0,0,0,0.8) 20%, transparent 70%),
                        url('<?php echo $heroBg; ?>') center/cover no-repeat;
            display: flex; align-items: center; padding: 0 50px;
        }
        .hero-content { max-width: 600px; }
        .hero-title { font-size: 3rem; font-weight: 800; margin-bottom: 15px; }
        .hero-desc { font-size: 1rem; color: #e5e5e5; margin-bottom: 25px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .btn-play { background-color: #ffffff; color: #000000; padding: 12px 25px; border-radius: 4px; font-weight: bold; text-decoration: none; display: inline-block; }

        .content-section { padding: 20px 50px; margin-top: -60px; position: relative; z-index: 10; }
        .section-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 15px; color: #e5e5e5; }

        .movies-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; margin-bottom: 40px;
        }
        .movie-card {
            position: relative; aspect-ratio: 2/3; border-radius: 6px; overflow: hidden; cursor: pointer; transition: transform 0.3s ease;
        }
        .movie-card img { width: 100%; height: 100%; object-fit: cover; }
        .movie-card:hover { transform: scale(1.06); z-index: 5; }
    </style>
</head>
<body>

    <header>
        <div class="logo">GMZ TV</div>
        <a href="/admin.php" class="btn-admin">Espace Admin</a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title"><?php echo htmlspecialchars($heroMovie['title'] ?? $heroMovie['name'] ?? 'Films & Séries'); ?></h1>
            <p class="hero-desc"><?php echo htmlspecialchars($heroMovie['overview'] ?? 'Regardez vos programmes préférés en HD sur GMZ TV.'); ?></p>
            <a href="/admin.php" class="btn-play">▶ Regarder maintenant</a>
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
