<?php
require_once 'config.php';

$apiKey = $_ENV['TMDB_API_KEY'] ?? $_SERVER['TMDB_API_KEY'] ?? getenv('TMDB_API_KEY') ?: '7b311a6f43090b24f188272bcc0655b3';

function fetchTMDB($endpoint, $apiKey) {
    if (!$apiKey) return ['results' => []];
    $url = "https://api.themoviedb.org/3/{$endpoint}?api_key={$apiKey}&language=fr-FR&page=1";
    $opts = [
        "http" => ["method" => "GET", "header" => "User-Agent: PHP\r\n"],
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    return $response ? json_decode($response, true) : ['results' => []];
}

$trendingMovies = fetchTMDB('trending/movie/week', $apiKey)['results'] ?? [];
$popularSeries  = fetchTMDB('tv/popular', $apiKey)['results'] ?? [];
$topRatedMovies = fetchTMDB('movie/top_rated', $apiKey)['results'] ?? [];

$heroMovies = array_slice($trendingMovies, 0, 5);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-PANEL - IPTV Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        body { background-color: #0b0e14; color: #ffffff; overflow-x: hidden; }

        header {
            position: fixed; top: 0; width: 100%; padding: 15px 40px;
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(180deg, rgba(11, 14, 20, 0.95) 0%, rgba(11, 14, 20, 0) 100%);
            z-index: 1000;
        }
        .logo-container img { height: 44px; width: auto; object-fit: contain; }
        .btn-admin {
            background: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
            color: #ffffff; padding: 10px 24px; text-decoration: none; font-weight: 700;
            font-size: 0.95rem; border-radius: 6px; box-shadow: 0 4px 15px rgba(67, 100, 247, 0.4);
            transition: all 0.3s ease;
        }
        .btn-admin:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(67, 100, 247, 0.6); }

        /* HERO BANNER AVEC VIDEO OU IMAGE */
        .hero-container { position: relative; height: 80vh; width: 100%; overflow: hidden; background: #000; }
        
        .hero-media {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1;
        }
        
        .hero-media iframe, .hero-media img {
            width: 100%; height: 100%; object-fit: cover; border: none;
        }

        .hero-overlay {
            position: absolute; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(to top, #0b0e14 10%, transparent 60%),
                        linear-gradient(to right, rgba(11, 14, 20, 0.95) 30%, transparent 80%);
            z-index: 2; pointer-events: none;
        }

        .hero-content {
            position: absolute; bottom: 15%; left: 40px; z-index: 10; max-width: 650px;
        }
        .hero-badge { display: inline-block; background: #4364f7; color: #fff; font-size: 0.8rem; font-weight: 800; padding: 4px 10px; border-radius: 4px; margin-bottom: 12px; }
        .hero-title { font-size: 3.2rem; font-weight: 800; margin-bottom: 12px; text-shadow: 0 2px 10px rgba(0,0,0,0.8); }
        .hero-desc { font-size: 1.05rem; color: #d1d5db; margin-bottom: 22px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        .btn-play {
            background-color: #ffffff; color: #0b0e14; padding: 12px 28px; border-radius: 6px;
            font-weight: 800; font-size: 1.05rem; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; transition: all 0.2s ease;
        }
        .btn-play:hover { opacity: 0.9; transform: scale(1.02); }

        /* SECTIONS & GRIDS */
        .content-section { padding: 0 40px; margin-top: -50px; position: relative; z-index: 20; }
        .section-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 18px; color: #f3f4f6; }
        .movies-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 18px; margin-bottom: 45px; }
        
        .movie-card {
            position: relative; aspect-ratio: 2/3; border-radius: 8px; overflow: hidden;
            cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.3s ease;
        }
        .movie-card img { width: 100%; height: 100%; object-fit: cover; }
        .movie-card:hover { transform: translateY(-6px) scale(1.04); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.8), 0 0 15px rgba(67, 100, 247, 0.4); z-index: 5; }
    </style>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="assets/g-panel-logo-dark.png" alt="G-PANEL IPTV Management System">
        </div>
        <a href="/admin.php" class="btn-admin">Espace Admin</a>
    </header>

    <!-- HERO BANNER DYNAMIQUE -->
    <div class="hero-container" id="heroContainer">
        <div class="hero-media" id="heroMedia">
            <?php 
                $first = $heroMovies[0] ?? null;
                $bg = !empty($first['backdrop_path']) ? "https://image.tmdb.org/t/p/original" . $first['backdrop_path'] : "";
            ?>
            <img id="heroImage" src="<?php echo $bg; ?>" alt="Hero Backdrop">
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="hero-badge" id="heroBadge">À LA UNE</span>
            <h1 class="hero-title" id="heroTitle"><?php echo htmlspecialchars($first['title'] ?? $first['name'] ?? 'Films & Séries'); ?></h1>
            <p class="hero-desc" id="heroDesc"><?php echo htmlspecialchars($first['overview'] ?? ''); ?></p>
            <a href="/admin.php" class="btn-play">▶ Lancer sur le Player</a>
        </div>
    </div>

    <!-- SECTIONS DE FILMS ET SÉRIES -->
    <section class="content-section">
        <h2 class="section-title">🔥 Films Tendances cette semaine</h2>
        <div class="movies-grid">
            <?php foreach ($trendingMovies as $movie): ?>
                <?php if (!empty($movie['poster_path'])): ?>
                    <div class="movie-card" onclick="playInHero('movie', <?php echo $movie['id']; ?>)" title="<?php echo htmlspecialchars($movie['title']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $movie['poster_path']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">📺 Séries Populaires</h2>
        <div class="movies-grid">
            <?php foreach ($popularSeries as $show): ?>
                <?php if (!empty($show['poster_path'])): ?>
                    <div class="movie-card" onclick="playInHero('tv', <?php echo $show['id']; ?>)" title="<?php echo htmlspecialchars($show['name']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $show['poster_path']; ?>" alt="<?php echo htmlspecialchars($show['name']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">⭐ Films les mieux notés</h2>
        <div class="movies-grid">
            <?php foreach ($topRatedMovies as $top): ?>
                <?php if (!empty($top['poster_path'])): ?>
                    <div class="movie-card" onclick="playInHero('movie', <?php echo $top['id']; ?>)" title="<?php echo htmlspecialchars($top['title']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $top['poster_path']; ?>" alt="<?php echo htmlspecialchars($top['title']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <script>
        const API_KEY = '<?php echo $apiKey; ?>';

        // Lancement dynamique de la bande-annonce dans l'arrière-plan du Hero
        async function playInHero(type, id) {
            // Remonter automatiquement tout en haut sur le Hero
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const res = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${API_KEY}&language=fr-FR&append_to_response=videos`);
            const data = await res.json();

            // Mettre à jour les textes du Hero
            document.getElementById('heroBadge').innerText = type === 'movie' ? 'FILM' : 'SÉRIE';
            document.getElementById('heroTitle').innerText = data.title || data.name;
            document.getElementById('heroDesc').innerText = data.overview || 'Aucune description disponible.';

            // Récupérer la vidéo YouTube
            const videos = data.videos ? data.videos.results : [];
            const trailer = videos.find(v => v.site === 'YouTube' && (v.type === 'Trailer' || v.type === 'Teaser')) || videos[0];

            const heroMedia = document.getElementById('heroMedia');

            if (trailer) {
                // Remplacer l'arrière-plan par le player vidéo YouTube en autostart
                heroMedia.innerHTML = `<iframe src="https://www.youtube.com/embed/${trailer.key}?autoplay=1&mute=1&controls=0&loop=1&playlist=${trailer.key}" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
            } else if (data.backdrop_path) {
                // Fallback sur l'image backdrop grand format si pas de vidéo disponible
                heroMedia.innerHTML = `<img src="https://image.tmdb.org/t/p/original${data.backdrop_path}" alt="Hero Backdrop">`;
            }
        }
    </script>
</body>
</html>
