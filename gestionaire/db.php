<?php
$host = 'localhost';
<<<<<<< HEAD
$dbname = 'stock_db';
=======
$dbname = 'gestion_stock';
>>>>>>> f18da9fe6e62a209298bc5b9edda769309b7ca25
$user = 'root';
$pass = ''; // ou le mot de passe de ton serveur local

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
<<<<<<< HEAD

=======
?>
>>>>>>> f18da9fe6e62a209298bc5b9edda769309b7ca25
