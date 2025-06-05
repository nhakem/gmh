<?php
// medicaments/supprimer_medicament.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$medicamentId = intval($_GET['id'] ?? 0);

if ($medicamentId) {
    $db = getDB();
    
    try {
        // Vérifier qu'il n'y a pas de prescriptions actives
        $checkSql = "SELECT COUNT(*) FROM prescriptions WHERE medicament_id = :id 
                     AND date_debut <= CURDATE() AND (date_fin IS NULL OR date_fin >= CURDATE())";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':id' => $medicamentId]);
        
        if ($checkStmt->fetchColumn() > 0) {
            redirect('medicaments/gerer.php', 
                'Impossible de supprimer ce médicament car il a des prescriptions actives.', 
                'danger');
        }
        
        // Récupérer le nom avant suppression
        $stmt = $db->prepare("SELECT nom FROM medicaments WHERE id = :id");
        $stmt->execute([':id' => $medicamentId]);
        $medicament = $stmt->fetch();
        
        if ($medicament) {
            // Supprimer le médicament
            $deleteStmt = $db->prepare("DELETE FROM medicaments WHERE id = :id");
            $deleteStmt->execute([':id' => $medicamentId]);
            
            logAction('Suppression médicament', 'medicaments', $medicamentId, 
                "Supprimé: " . $medicament['nom']);
            
            redirect('medicaments/gerer.php', 'Médicament supprimé avec succès.', 'success');
        } else {
            redirect('medicaments/gerer.php', 'Médicament non trouvé.', 'danger');
        }
    } catch (PDOException $e) {
        redirect('medicaments/gerer.php', 'Erreur lors de la suppression.', 'danger');
    }
} else {
    redirect('medicaments/gerer.php');
}