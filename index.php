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

$heroMovies = array_slice($trendingMovies, 0, 10);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-PANEL - IPTV Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        
        html, body { 
            height: 100%; 
            overflow: hidden; 
            background-color: #0b0e14; 
            color: #ffffff; 
        }

        /* HEADER FIXE */
        header {
            position: absolute; top: 0; width: 100%; padding: 12px 30px;
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(180deg, rgba(11, 14, 20, 0.95) 0%, rgba(11, 14, 20, 0) 100%);
            z-index: 1000;
        }
        
        .logo-container img { 
            width: 300px; 
            height: auto; 
            display: block; 
            object-fit: contain; 
        }

        .btn-admin {
            background: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
            color: #ffffff; padding: 8px 20px; text-decoration: none; font-weight: 700;
            font-size: 0.9rem; border-radius: 6px; box-shadow: 0 4px 15px rgba(67, 100, 247, 0.4);
            transition: all 0.3s ease;
        }
        .btn-admin:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(67, 100, 247, 0.6); }

        /* MAIN LAYOUT (HERO FIXE HAUT + CATALOGUE SCROLLABLE BAS) */
        .app-layout {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
        }

        /* BANNIÈRE HERO HAUT (FIXE : 48% DE L'ÉCRAN) */
        .hero-container { 
            position: relative; 
            height: 48vh; 
            width: 100%; 
            overflow: hidden; 
            background: #000; 
            flex-shrink: 0;
            border-bottom: 2px solid rgba(67, 100, 247, 0.2);
        }
        
        .hero-media {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1;
        }
        
        .hero-media iframe, .hero-media img {
            width: 100%; height: 100%; object-fit: cover; border: none;
        }

        .hero-overlay {
            position: absolute; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(to top, #0b0e14 15%, transparent 60%),
                        linear-gradient(to right, rgba(11, 14, 20, 0.95) 30%, transparent 80%);
            z-index: 2; pointer-events: none;
        }

        .hero-content {
            position: absolute; bottom: 8%; left: 30px; z-index: 10; max-width: 600px;
        }
        .hero-badge { display: inline-block; background: #4364f7; color: #fff; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; margin-bottom: 8px; }
        .hero-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 8px; text-shadow: 0 2px 10px rgba(0,0,0,0.8); }
        .hero-desc { font-size: 0.95rem; color: #d1d5db; margin-bottom: 14px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        .btn-play {
            background-color: #ffffff; color: #0b0e14; padding: 8px 20px; border-radius: 6px;
            font-weight: 800; font-size: 0.95rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;
        }
        .btn-play:hover { opacity: 0.9; transform: scale(1.02); }

        /* ZONE DE CATALOGUE DE DÉFILEMENT (SEUL CET ESPACE SCROLLE) */
        .content-scrollable { 
            flex-grow: 1;
            overflow-y: auto; 
            padding: 25px 30px 40px 30px; 
            background: #0b0e14;
        }

        .section-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 15px; color: #f3f4f6; }
        .movies-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; margin-bottom: 35px; }
        
        .movie-card {
            position: relative; aspect-ratio: 2/3; border-radius: 8px; overflow: hidden;
            cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.3s ease;
        }
        .movie-card img { width: 100%; height: 100%; object-fit: cover; }
        .movie-card:hover { transform: translateY(-4px) scale(1.04); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.8), 0 0 12px rgba(67, 100, 247, 0.4); z-index: 5; }

        /* Personnalisation de la barre de scroll */
        .content-scrollable::-webkit-scrollbar { width: 8px; }
        .content-scrollable::-webkit-scrollbar-track { background: #0b0e14; }
        .content-scrollable::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        .content-scrollable::-webkit-scrollbar-thumb:hover { background: #4364f7; }
    </style>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="assets/g-panel-logo-dark.png" alt="G-PANEL IPTV Management System">
        </div>
        <a href="/admin.php" class="btn-admin">Espace Admin</a>
    </header>

    <div class="app-layout">
        <!-- 1. HERO BANNER TOTALEMENT FIXE (TOUJOURS VISIBLE) -->
        <div class="hero-container" id="heroContainer">
            <div class="hero-media" id="heroMedia">
                <?php 
                    $first = $heroMovies[0] ?? null;
                    $bg = (!empty($first['backdrop_path'])) ? "https://image.tmdb.org/t/p/original" . $first['backdrop_path'] : "";
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

        <!-- 2. ZONE SEULEMENT SCROLLABLE AVEC LES GRILLES -->
        <div class="content-scrollable">
            <h2 class="section-title">🔥 Films Tendances cette semaine</h2>
            <div class="movies-grid">
                <?php foreach ($trendingMovies as $movie): ?>
                    <?php if (!empty($movie['poster_path'])): ?>
                        <div class="movie-card" 
                             onmouseenter="onHoverCard('movie', <?php echo htmlspecialchars(json_encode($movie), ENT_QUOTES); ?>)" 
                             onclick="playTrailerInHero('movie', <?php echo $movie['id']; ?>)" 
                             title="<?php echo htmlspecialchars($movie['title']); ?>">
                            <img src="https://image.tmdb.org/t/p/w500<?php echo $movie['poster_path']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <h2 class="section-title">📺 Séries Populaires</h2>
            <div class="movies-grid">
                <?php foreach ($popularSeries as $show): ?>
                    <?php if (!empty($show['poster_path'])): ?>
                        <div class="movie-card" 
                             onmouseenter="onHoverCard('tv', <?php echo htmlspecialchars(json_encode($show), ENT_QUOTES); ?>)" 
                             onclick="playTrailerInHero('tv', <?php echo $show['id']; ?>)" 
                             title="<?php echo htmlspecialchars($show['name']); ?>">
                            <img src="https://image.tmdb.org/t/p/w500<?php echo $show['poster_path']; ?>" alt="<?php echo htmlspecialchars($show['name']); ?>">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <h2 class="section-title">⭐ Films les mieux notés</h2>
            <div class="movies-grid">
                <?php foreach ($topRatedMovies as $top): ?>
                    <?php if (!empty($top['poster_path'])): ?>
                        <div class="movie-card" 
                             onmouseenter="onHoverCard('movie', <?php echo htmlspecialchars(json_encode($top), ENT_QUOTES); ?>)" 
                             onclick="playTrailerInHero('movie', <?php echo $top['id']; ?>)" 
                             title="<?php echo htmlspecialchars($top['title']); ?>">
                            <img src="https://image.tmdb.org/t/p/w500<?php echo $top['poster_path']; ?>" alt="<?php echo htmlspecialchars($top['title']); ?>">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?php echo $apiKey; ?>';
        const heroMovies = <?php echo json_encode($heroMovies); ?>;
        let currentIndex = 0;
        let autoSliderInterval = null;

        function updateHeroDisplay(type, item) {
            document.getElementById('heroBadge').innerText = type === 'movie' ? 'FILM' : 'SÉRIE';
            document.getElementById('heroTitle').innerText = item.title || item.name;
            document.getElementById('heroDesc').innerText = item.overview || 'Aucune description disponible.';

            const heroMedia = document.getElementById('heroMedia');
            if (item.backdrop_path) {
                heroMedia.innerHTML = `<img id="heroImage" src="https://image.tmdb.org/t/p/original${item.backdrop_path}" alt="Hero Backdrop">`;
            }
        }

        function startAutoSlider() {
            stopAutoSlider();
            autoSliderInterval = setInterval(() => {
                currentIndex = (currentIndex + 1) % heroMovies.length;
                const currentItem = heroMovies[currentIndex];
                const type = currentItem.title ? 'movie' : 'tv';
                updateHeroDisplay(type, currentItem);
            }, 5000);
        }

        function stopAutoSlider() {
            if (autoSliderInterval) clearInterval(autoSliderInterval);
        }

        function onHoverCard(type, item) {
            startAutoSlider();
            updateHeroDisplay(type, item);
        }

        async function playTrailerInHero(type, id) {
            stopAutoSlider();

            const res = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${API_KEY}&language=fr-FR&append_to_response=videos`);
            const data = await res.json();

            document.getElementById('heroBadge').innerText = type === 'movie' ? 'FILM' : 'SÉRIE';
            document.getElementById('heroTitle').innerText = data.title || data.name;
            document.getElementById('heroDesc').innerText = data.overview || 'Aucune description disponible.';

            const videos = data.videos ? data.videos.results : [];
            const trailer = videos.find(v => v.site === 'YouTube' && (v.type === 'Trailer' || v.type === 'Teaser')) || videos[0];

            const heroMedia = document.getElementById('heroMedia');

            if (trailer) {
                heroMedia.innerHTML = `<iframe src="https://www.youtube.com/embed/${trailer.key}?autoplay=1&mute=0&controls=1&loop=1&playlist=${trailer.key}" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
            } else if (data.backdrop_path) {
                heroMedia.innerHTML = `<img src="https://image.tmdb.org/t/p/original${data.backdrop_path}" alt="Hero Backdrop">`;
            }
        }

        startAutoSlider();
    </script>
</body>
</html>
