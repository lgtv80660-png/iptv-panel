<?php
// Récupération des variables fournies automatiquement par Railway
$host = getenv('MYSQLHOST') ?: ($_ENV['MYSQLHOST'] ?? 'mypanel-lgtv80660-722d.b.aivencloud.com');
$port = getenv('MYSQLPORT') ?: ($_ENV['MYSQLPORT'] ?? '12071');
$db   = getenv('MYSQLDATABASE') ?: ($_ENV['MYSQLDATABASE'] ?? 'defaultdb');
$user = getenv('MYSQLUSER') ?: ($_ENV['MYSQLUSER'] ?? 'avnadmin');
$pass = getenv('MYSQLPASSWORD') ?: ($_ENV['MYSQLPASSWORD'] ?? 'AVNS_pFjJzncvGshRgYHG0Gr');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
