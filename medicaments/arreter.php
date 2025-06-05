<?php
// medicaments/arreter.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$prescriptionId = intval($_GET['id'] ?? 0);

if ($prescriptionId) {
    $db = getDB();
    
    try {
        // Récupérer les informations de la prescription
        $sql = "SELECT p.*, pers.nom, pers.prenom, m.nom as medicament_nom 
                FROM prescriptions p 
                JOIN personnes pers ON p.personne_id = pers.id
                JOIN medicaments m ON p.medicament_id = m.id
                WHERE p.id = :id 
                AND p.date_debut <= CURDATE() 
                AND (p.date_fin IS NULL OR p.date_fin >= CURDATE())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $prescriptionId]);
        $prescription = $stmt->fetch();
        
        if ($prescription) {
            // Arrêter la prescription en mettant la date de fin à aujourd'hui
            $updateSql = "UPDATE prescriptions SET date_fin = CURDATE() WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([':id' => $prescriptionId]);
            
            // Journaliser
            $details = sprintf("Arrêt prescription: %s pour %s %s", 
                $prescription['medicament_nom'],
                $prescription['prenom'], 
                $prescription['nom']
            );
            logAction('Arrêt prescription', 'prescriptions', $prescriptionId, $details);
            
            redirect('medicaments/', 
                sprintf('Prescription de %s arrêtée pour %s %s.', 
                    $prescription['medicament_nom'],
                    $prescription['prenom'],
                    $prescription['nom']
                ), 
                'success'
            );
        } else {
            redirect('medicaments/', 'Prescription non trouvée ou déjà terminée.', 'danger');
        }
    } catch (PDOException $e) {
        error_log("Erreur arrêt prescription : " . $e->getMessage());
        redirect('medicaments/', 'Erreur lors de l\'arrêt de la prescription.', 'danger');
    }
} else {
    redirect('medicaments/');
}