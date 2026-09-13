<?php
// 1. Récupération de l'URL de connexion fournie par Railway
$databaseUrl = getenv('MYSQL_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: ($_ENV['MYSQL_URL'] ?? $_ENV['MYSQL_PRIVATE_URL'] ?? null);

if ($databaseUrl) {
    // Décomposition de l'URL mysql://user:pass@host:port/db
    $dbConfig = parse_url($databaseUrl);

    $host = $dbConfig['host'] ?? 'localhost';
    $port = $dbConfig['port'] ?? 3306;
    $user = $dbConfig['user'] ?? 'root';
    $pass = $dbConfig['pass'] ?? '';
    $db   = isset($dbConfig['path']) ? ltrim($dbConfig['path'], '/') : '';

    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données.");
    }
} else {
    die("Erreur : La variable d'environnement MYSQL_URL n'a pas été trouvée.");
}
?>
