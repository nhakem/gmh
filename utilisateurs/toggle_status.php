<?php
// utilisateurs/toggle_status.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    $status = $_POST['status'] == '1';
    
    if ($userId && $userId != $_SESSION['user_id']) {
        $result = $auth->toggleUserStatus($userId, $status);
        redirect('utilisateurs/', $result['message'], $result['success'] ? 'success' : 'danger');
    } else {
        redirect('utilisateurs/', 'Action non autorisée.', 'danger');
    }
} else {
    redirect('utilisateurs/');
}