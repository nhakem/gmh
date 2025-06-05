<?php
// logout.php - Déconnexion de l'utilisateur
require_once 'config.php';
require_once 'includes/classes/Auth.php';

$auth = new Auth();
$auth->logout();

redirect('login.php', 'Vous avez été déconnecté avec succès.', 'success');