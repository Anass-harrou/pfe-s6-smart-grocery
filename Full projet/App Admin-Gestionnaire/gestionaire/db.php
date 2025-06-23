<?php
$host = 'localhost';
$dbname = 'gestion_stock';
$user = 'root';
$pass = ''; // ou le mot de passe de ton serveur local

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

