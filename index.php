<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMZ TV - Streaming Premium & IPTV</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #141414;
            color: #ffffff;
            overflow-x: hidden;
        }

        /* En-tête / Navbar */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 20px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
            z-index: 1000;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 900;
            color: #e50914;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .btn-admin {
            background-color: #e50914;
            color: #fff;
            padding: 8px 20px;
            text-decoration: none;
            font-weight: 600;
            border-radius: 4px;
            transition: background 0.2s ease;
        }

        .btn-admin:hover {
            background-color: #f40612;
        }

        /* Banner Hero (Style Netflix) */
        .hero {
            position: relative;
            height: 80vh;
            background: linear-gradient(to top, #141414 5%, transparent 60%),
                        linear-gradient(to right, rgba(0,0,0,0.8) 20%, transparent 70%),
                        url('https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?q=80&w=1920&auto=format&fit=crop') center/cover no-repeat;
            display: flex;
            align-items: center;
            padding: 0 50px;
        }

        .hero-content {
            max-width: 600px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.6);
        }

        .hero-desc {
            font-size: 1.2rem;
            color: #e5e5e5;
            margin-bottom: 25px;
            line-height: 1.4;
        }

        .btn-play {
            background-color: #ffffff;
            color: #000000;
            padding: 12px 30px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.2s ease;
        }

        .btn-play:hover {
            opacity: 0.8;
        }

        /* Sections de Films / Séries */
        .content-section {
            padding: 20px 50px;
            margin-top: -80px;
            position: relative;
            z-index: 10;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #e5e5e5;
        }

        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .movie-card {
            position: relative;
            aspect-ratio: 2/3;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .movie-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .movie-card:hover {
            transform: scale(1.06);
            box-shadow: 0 10px 20px rgba(0,0,0,0.8);
            z-index: 5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            header, .hero, .content-section {
                padding-left: 20px;
                padding-right: 20px;
            }
            .hero-title {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo">GMZ TV</div>
        <a href="/admin.php" class="btn-admin">Espace Admin</a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Vos films et séries en ultra HD</h1>
            <p class="hero-desc">Accédez à l'ensemble du catalogue, aux chaînes en direct et aux dernières nouveautés en streaming illimité.</p>
            <a href="/admin.php" class="btn-play">▶ Lancer le Player</a>
        </div>
    </section>

    <section class="content-section">
        <h2 class="section-title">Tendance actuellement</h2>
        <div class="movies-grid">
            <div class="movie-card">
                <img src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop" alt="Affiche Film 1">
            </div>
            <div class="movie-card">
                <img src="https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=400&auto=format&fit=crop" alt="Affiche Film 2">
            </div>
            <div class="movie-card">
                <img src="https://images.unsplash.com/photo-1485846234645-a62644f84728?q=80&w=400&auto=format&fit=crop" alt="Affiche Film 3">
            </div>
            <div class="movie-card">
                <img src="https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=400&auto=format&fit=crop" alt="Affiche Film 4">
            </div>
            <div class="movie-card">
                <img src="https://images.unsplash.com/photo-1518676599625-5847466548a8?q=80&w=400&auto=format&fit=crop" alt="Affiche Film 5">
            </div>
            <div class="movie-card">
                <img src="https://images.unsplash.com/photo-1535016120720-40c646be5580?q=80&w=400&auto=format&fit=crop" alt="Affiche Film 6">
            </div>
        </div>
    </section>

</body>
</html>
