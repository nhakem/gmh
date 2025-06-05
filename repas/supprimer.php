<?php
// repas/supprimer.php - Suppression d'un repas
require_once '../config.php';
require_once '../includes/classes/Auth.php';

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

if (!$auth->hasRole('administrateur')) {
    redirect('dashboard/', MSG_PERMISSION_DENIED, 'error');
}

$repasId = intval($_GET['id'] ?? 0);

if ($repasId) {
    try {
        $db = getDB();
        
        // Récupérer les infos avant suppression pour le log
        $stmt = $db->prepare("
            SELECT r.*, p.nom, p.prenom, tr.nom as type_repas_nom 
            FROM repas r 
            JOIN personnes p ON r.personne_id = p.id 
            JOIN types_repas tr ON r.type_repas_id = tr.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $repasId]);
        $repas = $stmt->fetch();
        
        if ($repas) {
            // Supprimer le repas
            $deleteStmt = $db->prepare("DELETE FROM repas WHERE id = :id");
            $deleteStmt->execute([':id' => $repasId]);
            
            // Journaliser la suppression
            $details = sprintf("Suppression repas: %s %s, %s, %s", 
                $repas['prenom'], 
                $repas['nom'], 
                $repas['type_repas_nom'],
                formatDate($repas['date_repas'], 'd/m/Y')
            );
            logAction('Suppression repas', 'repas', $repasId, $details);
            
            redirect('repas/', 'Repas supprimé avec succès.', 'success');
        } else {
            redirect('repas/', 'Repas non trouvé.', 'error');
        }
        
    } catch (PDOException $e) {
        error_log("Erreur suppression repas: " . $e->getMessage());
        redirect('repas/', 'Erreur lors de la suppression du repas.', 'error');
    }
} else {
    redirect('repas/', 'ID de repas invalide.', 'error');
}
?>