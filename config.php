<?php
$host = 'localhost';
$db   = 'iptv_panel'; // Remplacez par le nom de votre base
$user = 'root';       // Identifiant de votre base
$pass = '';           // Mot de passe

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>