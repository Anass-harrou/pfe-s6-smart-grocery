<?php

session_start();        // Démarre la session
session_destroy();      // Supprime toutes les données de session
header('Location: login.php'); // Redirige vers la page de connexion
exit();
