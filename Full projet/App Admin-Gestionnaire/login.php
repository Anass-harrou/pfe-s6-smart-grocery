<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Date: 2025-06-22 23:29:03
// User: Anass-harrou

// 1. CONNEXION DB (à adapter)
$db_host = 'localhost';
$db_name = 'gestion_stock';
$db_user = 'root';
$db_pass = ''; // Mettez votre mot de passe MySQL ici

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERREUR DB: " . $e->getMessage());
}

// 2. TRAITEMENT LOGIN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // REQUETE PRÉPARÉE
    $stmt = $db->prepare("SELECT id, username, password, role FROM utilisateurs WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // VÉRIFICATION
    if ($user) {
        if ($password === $user['password']) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];
            
            // REDIRECTION
            header('Location: '.$user['role'].'/dashboard.php');
            exit();
        } else {
            $error = "Mot de passe incorrect";
        }
    } else {
        $error = "Utilisateur non trouvé";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Smart Grocery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6200EE;
            --primary-dark: #3700B3;
            --secondary-color: #03DAC6;
            --text-color: #333;
            --bg-color: #f8f9ff;
            --card-bg: #ffffff;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }
        
        .background-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(98, 0, 238, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(3, 218, 198, 0.05) 0%, transparent 50%);
            z-index: -1;
        }
        
        .login-container {
            max-width: 1000px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            background-color: var(--card-bg);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .login-content {
            display: flex;
            flex-direction: row;
        }
        
        .login-image {
            flex: 1;
            background-color: var(--primary-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px;
            color: white;
        }
        
        .login-form {
            flex: 1;
            padding: 30px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(98, 0, 238, 0.25);
        }
        
        .form-floating>label {
            padding-left: 15px;
        }
        
        .login-btn {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover, .login-btn:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(98, 0, 238, 0.2);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .features {
            margin-top: 40px;
        }
        
        .feature {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .feature h5 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .feature p {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .tablet-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--secondary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .tablet-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 50%;
        }
        
        .alert {
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        @media (max-width: 768px) {
            .login-content {
                flex-direction: column;
            }
            
            .login-image {
                padding: 20px;
            }
            
            .features {
                display: none;
            }
            
            .login-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>

    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card login-card">
                    <div class="login-content">
                        <div class="login-image">
                            <div class="login-logo">
                                <img src="smart_cart_transparent.png" alt="Smart Grocery Logo" class="img-fluid" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 80 80%22><text x=%2250%%22 y=%2250%%22 font-size=%2230%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 fill=%22%236200EE%22>SG</text></svg>'">
                            </div>
                            <h2 class="mb-4">Smart Grocery Admin</h2>
                            <p class="mb-5">Plateforme d'administration et de gestion des stocks du système Smart Grocery</p>
                            
                            <div class="features">
                                <div class="row">
                                    <div class="col-md-4 feature">
                                        <div class="feature-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <h5>Tableaux de bord</h5>
                                        <p>Visualisez les statistiques et l'analyse des ventes</p>
                                    </div>
                                    <div class="col-md-4 feature">
                                        <div class="feature-icon">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <h5>Gestion de stock</h5>
                                        <p>Contrôlez votre inventaire et les niveaux de stock</p>
                                    </div>
                                    <div class="col-md-4 feature">
                                        <div class="feature-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h5>Gestion clients</h5>
                                        <p>Gérez les comptes et les cartes RFID</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="login-form">
                            <h3 class="mb-4 text-center">Connexion Administration</h3>
                            
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger mb-4">
                                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="form-floating mb-3">
                                    <input type="text" name="username" class="form-control" id="username" placeholder="Nom d'utilisateur" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                    <label for="username"><i class="fas fa-user me-2"></i> Nom d'utilisateur</label>
                                    <div class="invalid-feedback">
                                        Veuillez saisir votre nom d'utilisateur
                                    </div>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <input type="password" name="password" class="form-control" id="password" placeholder="Mot de passe" required>
                                    <label for="password"><i class="fas fa-lock me-2"></i> Mot de passe</label>
                                    <div class="invalid-feedback">
                                        Veuillez saisir votre mot de passe
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary login-btn">
                                        <i class="fas fa-sign-in-alt me-2"></i> Se connecter
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="tablet/index.php" class="tablet-link">
                                    <i class="fas fa-tablet-alt me-2"></i> Accéder à l'interface tablette
                                </a>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="text-center text-muted small">
                                <p class="mb-0">Administration Smart Grocery &copy; 2025</p>
                                <p>Accès réservé au personnel autorisé</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation des formulaires Bootstrap
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>