<?php
// hebergement/liberer.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$nuiteeId = intval($_GET['id'] ?? 0);

if ($nuiteeId) {
    $db = getDB();
    
    try {
        // Récupérer les informations avant libération
        $sql = "SELECT n.*, p.nom, p.prenom, c.numero as chambre_numero 
                FROM nuitees n 
                JOIN personnes p ON n.personne_id = p.id
                JOIN chambres c ON n.chambre_id = c.id
                WHERE n.id = :id AND n.actif = TRUE";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $nuiteeId]);
        $nuitee = $stmt->fetch();
        
        if ($nuitee) {
            // Calculer la durée du séjour
            $dateDebut = new DateTime($nuitee['date_debut']);
            $dateFin = new DateTime();
            $duree = $dateFin->diff($dateDebut)->days + 1;
            
            // Mettre à jour la nuitée
            $updateSql = "UPDATE nuitees SET actif = FALSE, date_fin = CURDATE() WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([':id' => $nuiteeId]);
            
            // Journaliser
            $details = sprintf("Libération chambre %s - %s %s - Durée: %d jours", 
                $nuitee['chambre_numero'],
                $nuitee['prenom'], 
                $nuitee['nom'], 
                $duree
            );
            logAction('Libération chambre', 'nuitees', $nuiteeId, $details);
            
            redirect('hebergement/', 
                sprintf('Chambre %s libérée. %s %s a séjourné %d jour%s.', 
                    $nuitee['chambre_numero'],
                    $nuitee['prenom'],
                    $nuitee['nom'],
                    $duree,
                    $duree > 1 ? 's' : ''
                ), 
                'success'
            );
        } else {
            redirect('hebergement/', 'Hébergement non trouvé ou déjà libéré.', 'danger');
        }
    } catch (PDOException $e) {
        error_log("Erreur libération chambre : " . $e->getMessage());
        redirect('hebergement/', 'Erreur lors de la libération de la chambre.', 'danger');
    }
} else {
    redirect('hebergement/');
}