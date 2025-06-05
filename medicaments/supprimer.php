<?php
// medicaments/supprimer.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$prescriptionId = intval($_GET['id'] ?? 0);

if ($prescriptionId) {
    $db = getDB();
    
    try {
        // Récupérer les infos avant suppression pour le log
        $stmt = $db->prepare("SELECT p.*, pers.nom, pers.prenom, m.nom as medicament_nom 
                             FROM prescriptions p 
                             JOIN personnes pers ON p.personne_id = pers.id
                             JOIN medicaments m ON p.medicament_id = m.id
                             WHERE p.id = :id");
        $stmt->execute([':id' => $prescriptionId]);
        $prescription = $stmt->fetch();
        
        if ($prescription) {
            // Supprimer la prescription
            $deleteStmt = $db->prepare("DELETE FROM prescriptions WHERE id = :id");
            $deleteStmt->execute([':id' => $prescriptionId]);
            
            // Journaliser
            $details = sprintf("Suppression prescription: %s pour %s %s", 
                $prescription['medicament_nom'],
                $prescription['prenom'], 
                $prescription['nom']
            );
            logAction('Suppression prescription', 'prescriptions', $prescriptionId, $details);
            
            redirect('medicaments/', 'Prescription supprimée avec succès.', 'success');
        } else {
            redirect('medicaments/', 'Prescription non trouvée.', 'danger');
        }
    } catch (PDOException $e) {
        redirect('medicaments/', 'Erreur lors de la suppression.', 'danger');
    }
} else {
    redirect('medicaments/');
}