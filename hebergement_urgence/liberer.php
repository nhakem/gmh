<?php
// hebergement_urgence/liberer.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$nuiteeId = intval($_GET['id'] ?? 0);

if (!$nuiteeId) {
    redirect('hebergement_urgence/', 'ID de nuitée invalide.', 'error');
}

$db = getDB();

try {
    // Vérifier que la nuitée existe et est de type HD
    $checkSql = "SELECT n.*, c.numero, c.type_hebergement_id, p.nom, p.prenom, th.nom as type_nom 
                 FROM nuitees n 
                 JOIN chambres c ON n.chambre_id = c.id 
                 JOIN personnes p ON n.personne_id = p.id
                 JOIN types_hebergement th ON c.type_hebergement_id = th.id
                 WHERE n.id = :id AND n.actif = TRUE AND th.nom = 'HD'";
    
    $stmt = $db->prepare($checkSql);
    $stmt->execute([':id' => $nuiteeId]);
    $nuitee = $stmt->fetch();
    
    if (!$nuitee) {
        redirect('hebergement_urgence/', 'Nuitée HD introuvable ou déjà libérée.', 'error');
    }
    
    // Mettre à jour la nuitée
    $updateSql = "UPDATE nuitees SET actif = FALSE, date_fin = CURDATE() WHERE id = :id";
    $stmt = $db->prepare($updateSql);
    $stmt->execute([':id' => $nuiteeId]);
    
    // Journaliser
    logAction('Libération chambre HD', 'nuitees', $nuiteeId, 
        "Chambre: {$nuitee['numero']}, Personne: {$nuitee['prenom']} {$nuitee['nom']}");
    
    redirect('hebergement_urgence/', 'La chambre HD a été libérée avec succès.', 'success');
    
} catch (PDOException $e) {
    error_log("Erreur libération chambre HD : " . $e->getMessage());
    redirect('hebergement_urgence/', 'Erreur lors de la libération de la chambre HD.', 'error');
}
?>