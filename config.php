<?php
$databaseUrl = getenv('MYSQL_URL') ?: ($_ENV['MYSQL_URL'] ?? null);

if ($databaseUrl) {
    $dbConfig = parse_url($databaseUrl);

    $host = $dbConfig['host'] ?? 'localhost';
    $port = $dbConfig['port'] ?? 3306;
    $user = $dbConfig['user'] ?? 'root';
    $pass = $dbConfig['pass'] ?? '';
    
    // Si la base n'est pas précisée dans l'URL, on utilise 'mysql'
    $path = isset($dbConfig['path']) ? ltrim($dbConfig['path'], '/') : '';
    $db   = !empty($path) ? $path : 'mysql';

    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Erreur de connexion PDO : " . $e->getMessage());
    }
} else {
    die("Erreur : La variable d'environnement MYSQL_URL n'a pas été trouvée.");
}
?>
