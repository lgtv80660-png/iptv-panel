<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMZ TV - Service IPTV</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }
        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #38bdf8;
        }
        p {
            color: #94a3b8;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .btn {
            display: inline-block;
            background: #38bdf8;
            color: #0f172a;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn:hover {
            background: #7dd3fc;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>GMZ TV</h1>
        <p>Bienvenue sur le portail GMZ TV Panel.</p>
        <a href="/admin.php" class="btn">Accéder au Panneau Admin</a>
    </div>
</body>
</html>
