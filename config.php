<?php
// On tente de lire la variable d'environnement injectée par le serveur
$databaseUrl = getenv('MYSQL_URL') 
    ?: ($_ENV['MYSQL_URL'] ?? null) 
    ?: ($_SERVER['MYSQL_URL'] ?? null);

if (!$databaseUrl) {
    // Message générique : aucun mot de passe ni identifiant n'est révélé
    die("Erreur de configuration serveur : Impossible de charger la base de données.");
}

$dbConfig = parse_url($databaseUrl);

$host = $dbConfig['host'] ?? '';
$port = $dbConfig['port'] ?? 3306;
$user = $dbConfig['user'] ?? '';
$pass = $dbConfig['pass'] ?? '';
$path = isset($dbConfig['path']) ? ltrim($dbConfig['path'], '/') : '';
$db   = !empty($path) ? $path : 'mysql';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Masquage absolu des détails PDO en production
    die("Erreur de connexion à la base de données.");
}
?>
