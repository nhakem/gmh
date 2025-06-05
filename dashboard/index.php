<?php
// dashboard/index.php - Redirection automatique
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('../login.php');
}

// Rediriger selon le rôle
if ($_SESSION['user_role'] === 'administrateur') {
    header('Location: admin.php');
} else {
    header('Location: agent.php');
}
exit();
