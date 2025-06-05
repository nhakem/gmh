<?php
// hebergement/modifier.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Modifier un hébergement - ' . SITE_NAME;
$currentPage = 'hebergement';

$nuiteeId = intval($_GET['id'] ?? 0);
if (!$nuiteeId) {
    redirect('hebergement/', 'Hébergement non trouvé.', 'danger');
}

$db = getDB();

// Récupérer les données de l'hébergement
$sql = "SELECT n.*, p.nom, p.prenom, c.numero as chambre_numero, th.nom as type_hebergement
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        JOIN chambres c ON n.chambre_id = c.id
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        WHERE n.id = :id AND n.actif = TRUE";

$stmt = $db->prepare($sql);
$stmt->execute([':id' => $nuiteeId]);
$nuitee = $stmt->fetch();

if (!$nuitee) {
    redirect('hebergement/', 'Hébergement non trouvé ou déjà terminé.', 'danger');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'date_fin' => !empty($_POST['date_fin']) ? $_POST['date_fin'] : null,
        'notes' => sanitize($_POST['notes'] ?? '')
    ];
    
    // Validation
    $errors = [];
    if ($data['date_fin'] && $data['date_fin'] < $nuitee['date_debut']) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début (" . formatDate($nuitee['date_debut'], 'd/m/Y') . ").";
    }
    
    if (empty($errors)) {
        try {
            $updateSql = "UPDATE nuitees SET date_fin = :date_fin WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                ':date_fin' => $data['date_fin'],
                ':id' => $nuiteeId
            ]);
            
            logAction('Modification hébergement', 'nuitees', $nuiteeId, 
                "Modification date fin: " . ($data['date_fin'] ?? 'Non définie'));
            
            redirect('hebergement/', 'Hébergement modifié avec succès.', 'success');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">
                        <i class="fas fa-edit me-2 text-primary"></i>
                        Modifier l'hébergement
                    </h3>
                    <a href="<?php echo BASE_URL; ?>hebergement/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
                
                <!-- Informations actuelles -->
                <div class="alert alert-info">
                    <h6 class="alert-heading">Informations actuelles :</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Personne :</strong> <?php echo htmlspecialchars($nuitee['prenom'] . ' ' . $nuitee['nom']); ?><br>
                            <strong>Chambre :</strong> <?php echo htmlspecialchars($nuitee['chambre_numero']); ?> (<?php echo htmlspecialchars($nuitee['type_hebergement']); ?>)
                        </div>
                        <div class="col-md-6">
                            <strong>Date début :</strong> <?php echo formatDate($nuitee['date_debut'], 'd/m/Y'); ?><br>
                            <strong>Durée actuelle :</strong> <?php 
                                $duree = (new DateTime())->diff(new DateTime($nuitee['date_debut']))->days + 1;
                                echo $duree . ' jour' . ($duree > 1 ? 's' : '');
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Erreurs :</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="date_fin" class="form-label">
                                Date de fin prévue <small class="text-muted">(optionnel)</small>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_fin" 
                                   name="date_fin" 
                                   value="<?php echo $nuitee['date_fin'] ?? ''; ?>"
                                   min="<?php echo $nuitee['date_debut']; ?>">
                            <small class="text-muted">
                                Laissez vide si la date de fin n'est pas connue. 
                                Pour terminer l'hébergement, utilisez plutôt "Libérer la chambre".
                            </small>
                        </div>
                        
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes <small class="text-muted">(optionnel)</small></label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3"
                                      placeholder="Informations complémentaires..."><?php echo isset($data['notes']) ? htmlspecialchars($data['notes']) : ''; ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>hebergement/" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>