<?php
// Lecture sécurisée des variables depuis le serveur (Vercel)
$host = getenv('MYSQLHOST') ?: ($_ENV['MYSQLHOST'] ?? ($_SERVER['MYSQLHOST'] ?? null));
$port = getenv('MYSQLPORT') ?: ($_ENV['MYSQLPORT'] ?? ($_SERVER['MYSQLPORT'] ?? '34100'));
$user = getenv('MYSQLUSER') ?: ($_ENV['MYSQLUSER'] ?? ($_SERVER['MYSQLUSER'] ?? null));
$pass = getenv('MYSQLPASSWORD') ?: ($_ENV['MYSQLPASSWORD'] ?? ($_SERVER['MYSQLPASSWORD'] ?? null));
$db   = getenv('MYSQLDATABASE') ?: ($_ENV['MYSQLDATABASE'] ?? ($_SERVER['MYSQLDATABASE'] ?? 'mysql'));

if (!$host || !$user || !$pass) {
    die("Erreur de configuration serveur : Variables d'environnement manquantes.");
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Masque les détails PDO sensibles en cas d'échec
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
