<?php
// logs/purge_logs.php - Purge des anciens journaux
require_once '../config.php';
require_once ROOT_PATH . '/includes/classes/Auth.php';

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

if (!hasPermission('administrateur')) {
    redirect('dashboard/', MSG_PERMISSION_DENIED, 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('logs/', 'Accès direct non autorisé', 'error');
}

try {
    $db = getDB();
    
    $purgeDate = $_POST['purge_date'] ?? date('Y-m-d', strtotime('-90 days'));
    $confirmPurge = isset($_POST['confirm_purge']);
    
    if (!$confirmPurge) {
        throw new Exception("Confirmation de purge requise");
    }
    
    // Validation de la date
    $purgeDateObj = new DateTime($purgeDate);
    $today = new DateTime();
    
    if ($purgeDateObj >= $today) {
        throw new Exception("La date de purge doit être antérieure à aujourd'hui");
    }
    
    // Compte des logs à supprimer
    $countStmt = $db->prepare("SELECT COUNT(*) FROM logs_saisie WHERE DATE(date_action) < ?");
    $countStmt->execute([$purgeDate]);
    $logsToDelete = $countStmt->fetchColumn();
    
    if ($logsToDelete > 0) {
        // Suppression des logs
        $deleteStmt = $db->prepare("DELETE FROM logs_saisie WHERE DATE(date_action) < ?");
        $deleteStmt->execute([$purgeDate]);
        
        // Log de l'action de purge
        logAction('purge_logs', 'logs_saisie', null, "Purge de $logsToDelete logs antérieurs au $purgeDate");
        
        redirect('logs/', "$logsToDelete journaux supprimés avec succès", 'success');
    } else {
        redirect('logs/', "Aucun journal à supprimer pour cette période", 'info');
    }
    
} catch (Exception $e) {
    redirect('logs/', 'Erreur lors de la purge: ' . $e->getMessage(), 'error');
}
?>