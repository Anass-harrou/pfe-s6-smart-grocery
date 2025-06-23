<?php
session_start();
require_once '../config.php'; // Inclure votre fichier de configuration BD

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);

// Marquer que l'utilisateur a vu le guide (si demandé)
if (isset($_GET['mark_seen'])) {
    if ($isLoggedIn) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE client SET guide_viewed = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['guide_viewed'] = 1;
    }
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide d'Utilisation - Smart Grocery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    
    <!-- Animation Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
    :root {
        --primary-color: #6200EE;
        --secondary-color: #03DAC6;
        --background-color: #f9f9f9;
        --text-color: #333333;
        --border-color: #e0e0e0;
        --success-color: #4CAF50;
        --warning-color: #FFC107;
        --error-color: #F44336;
    }
    
    body {
        font-family: 'Roboto', sans-serif;
        line-height: 1.6;
        color: var(--text-color);
        background-color: var(--background-color);
        margin: 0;
        padding: 0;
    }
    
    .header {
        background-color: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    
    .guide-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .guide-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .guide-header h1 {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 10px;
    }
    
    .guide-header p {
        font-size: 1.1rem;
        color: #666;
        max-width: 700px;
        margin: 0 auto;
    }
    
    .step-indicator {
        display: flex;
        justify-content: center;
        margin: 30px 0;
    }
    
    .step-dot {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background-color: #ddd;
        margin: 0 8px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    
    .step-dot.active {
        background-color: var(--primary-color);
        transform: scale(1.2);
    }
    
    .guide-content {
        position: relative;
        min-height: 500px;
    }
    
    .guide-step {
        position: absolute;
        width: 100%;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.5s ease, transform 0.5s ease;
        transform: translateX(50px);
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .guide-step.active {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
    }
    
    .step-content {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        padding: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .step-text {
        flex: 1;
        min-width: 300px;
        padding: 20px;
    }
    
    .step-text h2 {
        font-size: 1.7rem;
        color: var(--primary-color);
        margin-bottom: 15px;
        position: relative;
        padding-left: 30px;
    }
    
    .step-text h2::before {
        content: attr(data-number);
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 24px;
        height: 24px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    
    .step-text p {
        font-size: 1rem;
        line-height: 1.7;
        color: #666;
    }
    
    .step-text ul {
        padding-left: 20px;
    }
    
    .step-text li {
        margin-bottom: 10px;
        position: relative;
        padding-left: 5px;
    }
    
    .step-image {
        flex: 1;
        min-width: 300px;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .step-image img {
        max-width: 100%;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .navigation-buttons {
        display: flex;
        justify-content: space-between;
        width: 100%;
        margin-top: 20px;
    }
    
    .guide-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 30px;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
    }
    
    .prev-btn {
        background-color: #f0f0f0;
        color: #666;
    }
    
    .prev-btn:hover {
        background-color: #e0e0e0;
    }
    
    .next-btn, .finish-btn {
        background-color: var(--primary-color);
        color: white;
    }
    
    .next-btn:hover, .finish-btn:hover {
        background-color: #5000c5;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(98, 0, 238, 0.3);
    }
    
    .guide-btn i {
        margin: 0 8px;
    }
    
    .text-highlight {
        color: var(--primary-color);
        font-weight: 500;
    }
    
    .tip-box {
        background-color: rgba(3, 218, 198, 0.1);
        border-left: 4px solid var(--secondary-color);
        padding: 15px;
        margin: 20px 0;
        border-radius: 0 8px 8px 0;
    }
    
    .tip-box h4 {
        margin-top: 0;
        color: var(--secondary-color);
        font-size: 1.1rem;
    }
    
    .tip-box p {
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .step-content {
            flex-direction: column;
        }
        
        .step-text, .step-image {
            width: 100%;
        }
        
        .guide-header h1 {
            font-size: 1.8rem;
        }
    }
    
    /* Animation pour la carte RFID */
    @keyframes pulse-animation {
        0% { transform: scale(0.8); opacity: 0.8; }
        50% { transform: scale(1.2); opacity: 0.2; }
        100% { transform: scale(0.8); opacity: 0.8; }
    }
    
    .rfid-card {
        position: relative;
        width: 200px;
        height: 120px;
        background: linear-gradient(45deg, #6200EE, #9e47ff);
        border-radius: 10px;
        margin: 20px auto;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    .rfid-animation {
        position: absolute;
        top: 50%;
        right: 20px;
        transform: translateY(-50%);
    }
    
    .rfid-pulse {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        position: absolute;
        animation: pulse-animation 2s infinite;
    }
    
    .rfid-card i {
        position: absolute;
        top: 50%;
        left: 25px;
        transform: translateY(-50%);
        color: white;
        font-size: 28px;
    }
    
    .rfid-card .card-chip {
        position: absolute;
        top: 25px;
        left: 25px;
        width: 30px;
        height: 25px;
        background: #ffd700;
        border-radius: 3px;
    }
    </style>
</head>
<body>
    <div class="header">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 15px;">
            <img src="images/logo.png" alt="Smart Grocery Logo" style="height: 40px;">
            <div>
                <a href="index.php" style="text-decoration: none; color: var(--primary-color); margin-left: 20px; font-weight: 500;">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <?php if ($isLoggedIn): ?>
                <a href="index.php?logout=true" style="text-decoration: none; color: var(--text-color); margin-left: 20px; font-weight: 500;">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="guide-container">
        <div class="guide-header">
            <h1 class="animate__animated animate__fadeInDown">Bienvenue sur Smart Grocery</h1>
            <p class="animate__animated animate__fadeIn animate__delay-1s">Découvrez comment utiliser notre application innovante pour une expérience d'achat fluide et personnalisée.</p>
        </div>
        
        <div class="step-indicator">
            <div class="step-dot active" data-step="1"></div>
            <div class="step-dot" data-step="2"></div>
            <div class="step-dot" data-step="3"></div>
            <div class="step-dot" data-step="4"></div>
            <div class="step-dot" data-step="5"></div>
        </div>
        
        <div class="guide-content">
            <!-- Étape 1: Connexion -->
            <div class="guide-step active" data-step="1">
                <div class="step-content">
                    <div class="step-text">
                        <h2 data-number="1">Connexion à votre compte</h2>
                        <p>Connectez-vous facilement à votre compte Smart Grocery de trois façons différentes :</p>
                        <ul>
                            <li><span class="text-highlight">QR Code</span> - Scannez le code QR avec l'application mobile Smart Grocery</li>
                            <li><span class="text-highlight">Carte RFID</span> - Présentez votre carte au lecteur RFID</li>
                            <li><span class="text-highlight">Identifiant et mot de passe</span> - Utilisez votre identifiant et mot de passe traditionnels</li>
                        </ul>
                        <div class="tip-box">
                            <h4><i class="fas fa-lightbulb"></i> Conseil</h4>
                            <p>La connexion par carte RFID est la méthode la plus rapide pour accéder à votre compte en magasin.</p>
                        </div>
                    </div>
                    <div class="step-image">
                        <img src="images/guide/login.png" alt="Méthodes de connexion" style="max-width: 300px;">
                    </div>
                </div>
                <div class="navigation-buttons">
                    <button class="guide-btn prev-btn" disabled><i class="fas fa-chevron-left"></i> Précédent</button>
                    <button class="guide-btn next-btn">Suivant <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <!-- Étape 2: Parcourir les produits -->
            <div class="guide-step" data-step="2">
                <div class="step-content">
                    <div class="step-text">
                        <h2 data-number="2">Parcourir les produits</h2>
                        <p>Notre interface intuitive vous permet de parcourir facilement tous nos produits disponibles :</p>
                        <ul>
                            <li>Visualisez les <span class="text-highlight">images des produits</span>, leurs descriptions et prix</li>
                            <li>Les produits sont organisés par <span class="text-highlight">catégories</span> pour faciliter vos recherches</li>
                            <li>Vérifiez la <span class="text-highlight">disponibilité en temps réel</span> des articles</li>
                        </ul>
                        <div class="tip-box">
                            <h4><i class="fas fa-lightbulb"></i> Conseil</h4>
                            <p>Utilisez la fonction de recherche pour trouver rapidement des produits spécifiques.</p>
                        </div>
                    </div>
                    <div class="step-image">
                        <img src="images/guide/products.png" alt="Parcourir les produits" style="max-width: 300px;">
                    </div>
                </div>
                <div class="navigation-buttons">
                    <button class="guide-btn prev-btn"><i class="fas fa-chevron-left"></i> Précédent</button>
                    <button class="guide-btn next-btn">Suivant <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <!-- Étape 3: Ajouter au panier -->
            <div class="guide-step" data-step="3">
                <div class="step-content">
                    <div class="step-text">
                        <h2 data-number="3">Gérer votre panier</h2>
                        <p>Ajoutez des produits à votre panier et gérez-les facilement :</p>
                        <ul>
                            <li>Cliquez sur <span class="text-highlight">Ajouter au panier</span> pour sélectionner un produit</li>
                            <li>Ajustez les <span class="text-highlight">quantités</span> selon vos besoins</li>
                            <li>Le <span class="text-highlight">total de votre panier</span> est calculé automatiquement</li>
                            <li>Les produits peuvent être <span class="text-highlight">supprimés</span> ou leur quantité modifiée à tout moment</li>
                        </ul>
                        <div class="tip-box">
                            <h4><i class="fas fa-lightbulb"></i> Conseil</h4>
                            <p>Vous pouvez également scanner le code QR des produits en magasin pour les ajouter directement à votre panier.</p>
                        </div>
                    </div>
                    <div class="step-image">
                        <img src="images/guide/cart.png" alt="Gérer votre panier" style="max-width: 300px;">
                    </div>
                </div>
                <div class="navigation-buttons">
                    <button class="guide-btn prev-btn"><i class="fas fa-chevron-left"></i> Précédent</button>
                    <button class="guide-btn next-btn">Suivant <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <!-- Étape 4: Paiement -->
            <div class="guide-step" data-step="4">
                <div class="step-content">
                    <div class="step-text">
                        <h2 data-number="4">Paiement simple et rapide</h2>
                        <p>Finalisez votre achat avec notre système de paiement sécurisé :</p>
                        <ul>
                            <li>Choisissez entre <span class="text-highlight">paiement par QR code</span> ou <span class="text-highlight">carte RFID</span></li>
                            <li>Votre compte est <span class="text-highlight">débité automatiquement</span> du montant des achats</li>
                            <li>Recevez une <span class="text-highlight">confirmation immédiate</span> de votre paiement</li>
                        </ul>
                        <p>Pour payer avec votre carte RFID :</p>
                        
                        <div class="rfid-card">
                            <div class="card-chip"></div>
                            <i class="fas fa-wifi"></i>
                            <div class="rfid-animation">
                                <div class="rfid-pulse"></div>
                            </div>
                        </div>
                        
                        <div class="tip-box">
                            <h4><i class="fas fa-lightbulb"></i> Conseil</h4>
                            <p>Vérifiez toujours votre solde avant de procéder au paiement. Vous pouvez le recharger à tout moment.</p>
                        </div>
                    </div>
                    <div class="step-image">
                        <img src="images/guide/payment.png" alt="Paiement" style="max-width: 300px;">
                    </div>
                </div>
                <div class="navigation-buttons">
                    <button class="guide-btn prev-btn"><i class="fas fa-chevron-left"></i> Précédent</button>
                    <button class="guide-btn next-btn">Suivant <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <!-- Étape 5: Gestion de compte -->
            <div class="guide-step" data-step="5">
                <div class="step-content">
                    <div class="step-text">
                        <h2 data-number="5">Gérer votre compte</h2>
                        <p>Accédez à toutes les fonctionnalités de gestion de votre compte :</p>
                        <ul>
                            <li>Consultez votre <span class="text-highlight">historique d'achats</span></li>
                            <li>Gérez votre <span class="text-highlight">carte RFID</span> et associez-la à votre compte</li>
                            <li>Rechargez votre <span class="text-highlight">solde</span> pour vos achats futurs</li>
                            <li>Mettez à jour vos <span class="text-highlight">informations personnelles</span></li>
                        </ul>
                        <div class="tip-box">
                            <h4><i class="fas fa-lightbulb"></i> Conseil</h4>
                            <p>Pour une meilleure sécurité, déconnectez-vous toujours de votre compte lorsque vous avez terminé vos achats.</p>
                        </div>
                    </div>
                    <div class="step-image">
                        <img src="images/guide/account.png" alt="Gestion de compte" style="max-width: 300px;">
                    </div>
                </div>
                <div class="navigation-buttons">
                    <button class="guide-btn prev-btn"><i class="fas fa-chevron-left"></i> Précédent</button>
                    <a href="index.php?mark_seen=1" class="guide-btn finish-btn">Terminer <i class="fas fa-check"></i></a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Navigation entre les étapes
        let currentStep = 1;
        const totalSteps = 5;
        
        // Fonction pour changer d'étape
        function goToStep(step) {
            // Vérifier si l'étape est valide
            if (step < 1 || step > totalSteps) return;
            
            // Masquer l'étape actuelle et afficher la nouvelle
            $('.guide-step').removeClass('active');
            $(`.guide-step[data-step="${step}"]`).addClass('active');
            
            // Mettre à jour les indicateurs d'étape
            $('.step-dot').removeClass('active');
            $(`.step-dot[data-step="${step}"]`).addClass('active');
            
            // Mettre à jour l'étape actuelle
            currentStep = step;
            
            // Désactiver le bouton précédent si on est à la première étape
            if (currentStep === 1) {
                $('.prev-btn').prop('disabled', true);
            } else {
                $('.prev-btn').prop('disabled', false);
            }
            
            // Changer le texte du bouton suivant si on est à la dernière étape
            if (currentStep === totalSteps) {
                $('.next-btn').text('Terminer').addClass('finish-btn');
            } else {
                $('.next-btn').text('Suivant ').removeClass('finish-btn')
                    .append('<i class="fas fa-chevron-right"></i>');
            }
        }
        
        // Gestionnaire d'événements pour les boutons
        $('.next-btn').click(function() {
            goToStep(currentStep + 1);
            
            // Animation de l'étape active
            const activeStep = $(`.guide-step[data-step="${currentStep}"]`);
            activeStep.find('.step-text h2').addClass('animate__animated animate__fadeInLeft');
            activeStep.find('.step-image').addClass('animate__animated animate__fadeInRight');
        });
        
        $('.prev-btn').click(function() {
            goToStep(currentStep - 1);
            
            // Animation de l'étape active
            const activeStep = $(`.guide-step[data-step="${currentStep}"]`);
            activeStep.find('.step-text h2').addClass('animate__animated animate__fadeInLeft');
            activeStep.find('.step-image').addClass('animate__animated animate__fadeInRight');
        });
        
        // Cliquer sur les points d'étape
        $('.step-dot').click(function() {
            const step = $(this).data('step');
            goToStep(step);
            
            // Animation de l'étape active
            const activeStep = $(`.guide-step[data-step="${step}"]`);
            activeStep.find('.step-text h2').addClass('animate__animated animate__fadeInLeft');
            activeStep.find('.step-image').addClass('animate__animated animate__fadeInRight');
        });
        
        // Initialiser la première étape avec animation
        setTimeout(function() {
            const firstStep = $(`.guide-step[data-step="1"]`);
            firstStep.find('.step-text h2').addClass('animate__animated animate__fadeInLeft');
            firstStep.find('.step-image').addClass('animate__animated animate__fadeInRight');
        }, 500);
    });
    </script>
</body>
</html>