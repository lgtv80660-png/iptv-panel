<?php
// Récupération des variables fournies automatiquement par Railway
$host = getenv('MYSQLHOST') ?: ($_ENV['MYSQLHOST'] ?? 'altaria.proxy.rlwy.net');
$port = getenv('MYSQLPORT') ?: ($_ENV['MYSQLPORT'] ?? '34100');
$db   = getenv('MYSQLDATABASE') ?: ($_ENV['MYSQLDATABASE'] ?? 'mysql');
$user = getenv('MYSQLUSER') ?: ($_ENV['MYSQLUSER'] ?? 'root');
$pass = getenv('MYSQLPASSWORD') ?: ($_ENV['MYSQLPASSWORD'] ?? 'MIRl6jpjHX6TwmWCG6C46gOKdSxZm0UF');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
