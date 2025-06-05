<?php
// hebergement/toggle_chambre.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chambreId = intval($_POST['chambre_id'] ?? 0);
    $disponible = $_POST['disponible'] === 'true';
    
    if ($chambreId) {
        $db = getDB();
        
        try {
            $sql = "UPDATE chambres SET disponible = :disponible WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':disponible' => $disponible ? 1 : 0,
                ':id' => $chambreId
            ]);
            
            $action = $disponible ? 'Activation' : 'Désactivation';
            logAction($action . ' chambre', 'chambres', $chambreId);
            
            redirect('hebergement/chambres.php', 
                'Chambre ' . ($disponible ? 'activée' : 'désactivée') . ' avec succès.', 
                'success');
        } catch (PDOException $e) {
            redirect('hebergement/chambres.php', 'Erreur lors de la modification.', 'danger');
        }
    }
}

redirect('hebergement/chambres.php');