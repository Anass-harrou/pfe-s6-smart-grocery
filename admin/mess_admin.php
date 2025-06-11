<?php
// Connexion à la base de données
$conn = new PDO("mysql:host=localhost;dbname=stock_db", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_admin'])) {
    $msg = trim($_POST['message_admin']);
    if (!empty($msg)) {
       $stmt = $conn->prepare("INSERT INTO messages_admin (contenu, date_publication) VALUES (?, NOW())");
        if ($stmt->execute([$msg])) {
            header("Location: dashboard.php"); // adapte la redirection selon ton organisation
            exit;
        } else {
            echo "<p style='color:red;'>Erreur lors de la publication.</p>";
        }
    } else {
        echo "<p style='color:red;'>Le message ne peut pas être vide.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Publier une annonce</title>
</head>
<body>
    <h2>Publier une annonce pour les gestionnaires</h2>
    <form method="POST">
        <textarea name="message_admin" rows="5" cols="50" required placeholder="Écris ton message ici..."></textarea><br><br>
        <button type="submit">Publier</button>
    </form>
</body>
</html>
