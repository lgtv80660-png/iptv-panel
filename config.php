<?php
// Récupération des variables fournies automatiquement par Railway
$host = getenv('MYSQLHOST') ?: ($_ENV['MYSQLHOST'] ?? 'mysql.railway.internal');
$port = getenv('MYSQLPORT') ?: ($_ENV['MYSQLPORT'] ?? '3306');
$db   = getenv('MYSQLDATABASE') ?: ($_ENV['MYSQLDATABASE'] ?? 'railway');
$user = getenv('MYSQLUSER') ?: ($_ENV['MYSQLUSER'] ?? 'root');
$pass = getenv('MYSQLPASSWORD') ?: ($_ENV['MYSQLPASSWORD'] ?? 'PSgIQNIGVYovUuDwltSxQRKwEwcxRfrZ');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
