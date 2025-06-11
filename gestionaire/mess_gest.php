<?php
// Connexion à la base de données
$conn = new PDO("mysql:host=localhost;dbname=stock_db", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupérer le dernier message
$msg = $conn->query("SELECT * FROM messages_admin ORDER BY date_publication DESC LIMIT 1")->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Gestionnaire</title>
    <style>
        .annonce-admin {
            background-color: #fff3cd;
            border-left: 5px solid #ffeeba;
            padding: 1rem;
            margin: 2rem auto;
            max-width: 800px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .annonce-admin h4 {
            margin-top: 0;
        }
        .annonce-admin small {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="annonce-admin">
        <h4>📢 Annonce de l'Administrateur</h4>
        <?php if ($msg): ?>
            <small>Publié le <?= date("d/m/Y H:i", strtotime($msg['date_publication'])) ?></small>
            <p><?= nl2br(htmlspecialchars($msg['contenu'])) ?></p>
        <?php else: ?>
            <p>Aucune annonce disponible pour le moment.</p>
        <?php endif; ?>
    </div>
</body>
</html>
