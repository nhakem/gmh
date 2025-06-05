<?php
// index.php - Redirection vers la page de connexion ou tableau de bord
require_once 'config.php';
require_once 'includes/classes/Auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    // Rediriger selon le rôle
    if ($_SESSION['user_role'] === 'administrateur') {
        redirect('dashboard/admin.php');
    } else {
        redirect('dashboard/agent.php');
    }
} else {
    redirect('login.php');
}