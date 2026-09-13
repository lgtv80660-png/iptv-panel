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

// Sélection des 5 premiers films pour le slider héroïque
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

        /* HERO SLIDER CONTAINER */
        .hero-slider { position: relative; height: 78vh; width: 100%; overflow: hidden; }
        .slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0; transition: opacity 1s ease-in-out;
            display: flex; align-items: center; padding: 0 40px;
            background-size: cover; background-position: center;
        }
        .slide.active { opacity: 1; }
        .slide-overlay {
            position: absolute; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(to top, #0b0e14 8%, transparent 60%),
                        linear-gradient(to right, rgba(11, 14, 20, 0.9) 25%, transparent 75%);
        }
        .hero-content { position: relative; z-index: 10; max-width: 620px; }
        .hero-title { font-size: 3.2rem; font-weight: 800; margin-bottom: 15px; text-shadow: 0 2px 10px rgba(0,0,0,0.7); }
        .hero-desc { font-size: 1.05rem; color: #d1d5db; margin-bottom: 25px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        .btn-action {
            background-color: #ffffff; color: #0b0e14; padding: 12px 28px; border-radius: 6px;
            font-weight: 800; font-size: 1.05rem; text-decoration: none; border:none; cursor:pointer;
            display: inline-flex; align-items: center; gap: 10px; transition: all 0.2s ease;
        }
        .btn-action:hover { opacity: 0.9; transform: scale(1.02); }

        /* SECTIONS & GRIDS */
        .content-section { padding: 0 40px; margin-top: -60px; position: relative; z-index: 20; }
        .section-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 18px; color: #f3f4f6; }
        .movies-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 18px; margin-bottom: 45px; }
        
        .movie-card {
            position: relative; aspect-ratio: 2/3; border-radius: 8px; overflow: hidden;
            cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.3s ease;
        }
        .movie-card img { width: 100%; height: 100%; object-fit: cover; }
        .movie-card:hover { transform: translateY(-6px) scale(1.03); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.8), 0 0 15px rgba(67, 100, 247, 0.3); z-index: 5; }

        /* MODAL DETAILS & TRAILER */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); z-index: 2000;
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #151922; border-radius: 12px; max-width: 800px; width: 100%;
            overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9);
        }
        .modal-close {
            position: absolute; top: 15px; right: 20px; font-size: 2rem; color: #fff;
            cursor: pointer; z-index: 10; line-height: 1;
        }
        .modal-video-container { position: relative; padding-bottom: 56.25%; height: 0; background: #000; }
        .modal-video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
        .modal-body { padding: 25px; }
        .modal-title { font-size: 1.8rem; font-weight: 800; margin-bottom: 10px; color: #4364f7; }
        .modal-info { display: flex; gap: 15px; color: #9ca3af; font-size: 0.9rem; margin-bottom: 15px; }
        .modal-desc { color: #d1d5db; line-height: 1.6; font-size: 1rem; }
    </style>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="assets/g-panel-logo-dark.png" alt="G-PANEL IPTV Management System">
        </div>
        <a href="/admin.php" class="btn-admin">Espace Admin</a>
    </header>

    <!-- HERO SLIDER AUTOMATIQUE -->
    <div class="hero-slider">
        <?php foreach ($heroMovies as $index => $movie): ?>
            <?php 
                $bg = !empty($movie['backdrop_path']) 
                    ? "https://image.tmdb.org/t/p/original" . $movie['backdrop_path'] 
                    : "https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?q=80&w=1920";
                $title = htmlspecialchars($movie['title'] ?? $movie['name'] ?? '');
                $overview = htmlspecialchars($movie['overview'] ?? '');
                $id = $movie['id'];
                $type = isset($movie['title']) ? 'movie' : 'tv';
            ?>
            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo $bg; ?>');">
                <div class="slide-overlay"></div>
                <div class="hero-content">
                    <h1 class="hero-title"><?php echo $title; ?></h1>
                    <p class="hero-desc"><?php echo $overview; ?></p>
                    <button class="btn-action" onclick="openDetails('<?php echo $type; ?>', <?php echo $id; ?>)">▶ Aperçu & Details</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- SECTIONS CATALOGUE -->
    <section class="content-section">
        <h2 class="section-title">🔥 Films Tendances cette semaine</h2>
        <div class="movies-grid">
            <?php foreach ($trendingMovies as $movie): ?>
                <?php if (!empty($movie['poster_path'])): ?>
                    <div class="movie-card" onclick="openDetails('movie', <?php echo $movie['id']; ?>)" title="<?php echo htmlspecialchars($movie['title']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $movie['poster_path']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">📺 Séries Populaires</h2>
        <div class="movies-grid">
            <?php foreach ($popularSeries as $show): ?>
                <?php if (!empty($show['poster_path'])): ?>
                    <div class="movie-card" onclick="openDetails('tv', <?php echo $show['id']; ?>)" title="<?php echo htmlspecialchars($show['name']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $show['poster_path']; ?>" alt="<?php echo htmlspecialchars($show['name']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">⭐ Films les mieux notés</h2>
        <div class="movies-grid">
            <?php foreach ($topRatedMovies as $top): ?>
                <?php if (!empty($top['poster_path'])): ?>
                    <div class="movie-card" onclick="openDetails('movie', <?php echo $top['id']; ?>)" title="<?php echo htmlspecialchars($top['title']); ?>">
                        <img src="https://image.tmdb.org/t/p/w500<?php echo $top['poster_path']; ?>" alt="<?php echo htmlspecialchars($top['title']); ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- MODAL POPUP (INFOS & BANDE-ANNONCE) -->
    <div class="modal" id="detailsModal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <div class="modal-video-container" id="modalVideo">
                <!-- Iframe YouTube dynamique -->
            </div>
            <div class="modal-body">
                <h2 class="modal-title" id="modalTitle">Titre</h2>
                <div class="modal-info">
                    <span id="modalDate">Date</span> | 
                    <span id="modalVote">Note</span>
                </div>
                <p class="modal-desc" id="modalDesc">Description...</p>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?php echo $apiKey; ?>';

        // 1. CARROUSEL AUTOMATIQUE (Changement toutes les 5 secondes)
        const slides = document.querySelectorAll('.slide');
        let currentSlide = 0;
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 5000);

        // 2. MODALE DÉTAILS & BANDE ANNONCE YOUTUBE
        async function openDetails(type, id) {
            const modal = document.getElementById('detailsModal');
            const videoContainer = document.getElementById('modalVideo');
            
            // Requete détails + videos TMDB
            const res = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${API_KEY}&language=fr-FR&append_to_response=videos`);
            const data = await res.json();

            document.getElementById('modalTitle').innerText = data.title || data.name;
            document.getElementById('modalDate').innerText = data.release_date || data.first_air_date || 'N/A';
            document.getElementById('modalVote').innerText = `⭐ ${data.vote_average ? data.vote_average.toFixed(1) : 'N/A'} / 10`;
            document.getElementById('modalDesc').innerText = data.overview || 'Aucun synopsis disponible.';

            // Trouver la bande-annonce YouTube
            const videos = data.videos ? data.videos.results : [];
            const trailer = videos.find(v => v.site === 'YouTube' && (v.type === 'Trailer' || v.type === 'Teaser')) || videos[0];

            if (trailer) {
                videoContainer.innerHTML = `<iframe src="https://www.youtube.com/embed/${trailer.key}?autoplay=1" allowfullscreen allow="autoplay"></iframe>`;
            } else {
                videoContainer.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;">Pas de bande-annonce disponible</div>`;
            }

            modal.classList.add('active');
        }

        function closeModal(e) {
            const modal = document.getElementById('detailsModal');
            document.getElementById('modalVideo').innerHTML = ''; // Stopper la vidéo
            modal.classList.remove('active');
        }
    </script>
</body>
</html>
