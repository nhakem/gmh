<?php
// medicaments/modifier_medicament.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nom = sanitize($_POST['nom'] ?? '');
    $forme = sanitize($_POST['forme'] ?? '');
    
    if ($id && !empty($nom)) {
        $db = getDB();
        
        try {
            $sql = "UPDATE medicaments SET nom = :nom, forme = :forme WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nom' => $nom,
                ':forme' => $forme,
                ':id' => $id
            ]);
            
            logAction('Modification médicament', 'medicaments', $id, "Modifié: $nom");
            redirect('medicaments/gerer.php', 'Médicament modifié avec succès.', 'success');
            
        } catch (PDOException $e) {
            redirect('medicaments/gerer.php', 'Erreur lors de la modification.', 'danger');
        }
    } else {
        redirect('medicaments/gerer.php', 'Données invalides.', 'danger');
    }
} else {
    redirect('medicaments/gerer.php');
}