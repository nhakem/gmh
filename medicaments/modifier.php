<?php
// medicaments/modifier.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Modifier une prescription - ' . SITE_NAME;
$currentPage = 'medicaments';

$prescriptionId = intval($_GET['id'] ?? 0);
if (!$prescriptionId) {
    redirect('medicaments/', 'Prescription non trouvée.', 'danger');
}

$db = getDB();

// Récupérer la prescription
$sql = "SELECT p.*, pers.nom, pers.prenom, m.nom as medicament_nom
        FROM prescriptions p
        JOIN personnes pers ON p.personne_id = pers.id
        JOIN medicaments m ON p.medicament_id = m.id
        WHERE p.id = :id";

$stmt = $db->prepare($sql);
$stmt->execute([':id' => $prescriptionId]);
$prescription = $stmt->fetch();

if (!$prescription) {
    redirect('medicaments/', 'Prescription non trouvée.', 'danger');
}

// Vérifier que la prescription est encore active
$dateDebut = new DateTime($prescription['date_debut']);
$dateFin = $prescription['date_fin'] ? new DateTime($prescription['date_fin']) : null;
$aujourdhui = new DateTime();

if ($dateDebut > $aujourdhui || ($dateFin && $dateFin < $aujourdhui)) {
    redirect('medicaments/', 'Cette prescription n\'est plus active.', 'warning');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'dosage' => sanitize($_POST['dosage'] ?? ''),
        'frequence' => sanitize($_POST['frequence'] ?? ''),
        'date_fin' => !empty($_POST['date_fin']) ? $_POST['date_fin'] : null,
        'gratuit' => isset($_POST['gratuit']) ? 1 : 0,
        'cout' => floatval($_POST['cout'] ?? 0),
        'instructions' => sanitize($_POST['instructions'] ?? '')
    ];
    
    // Validation
    $errors = [];
    if (empty($data['dosage'])) {
        $errors[] = "Le dosage est obligatoire.";
    }
    if (empty($data['frequence'])) {
        $errors[] = "La fréquence est obligatoire.";
    }
    
    // Vérifier les dates
    if ($data['date_fin'] && $data['date_fin'] < $prescription['date_debut']) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
    }
    
    if (empty($errors)) {
        try {
            $updateSql = "UPDATE prescriptions 
                         SET dosage = :dosage, 
                             frequence = :frequence, 
                             date_fin = :date_fin, 
                             gratuit = :gratuit, 
                             cout = :cout, 
                             instructions = :instructions 
                         WHERE id = :id";
            
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                ':dosage' => $data['dosage'],
                ':frequence' => $data['frequence'],
                ':date_fin' => $data['date_fin'],
                ':gratuit' => $data['gratuit'],
                ':cout' => !$data['gratuit'] ? $data['cout'] : null,
                ':instructions' => $data['instructions'],
                ':id' => $prescriptionId
            ]);
            
            logAction('Modification prescription', 'prescriptions', $prescriptionId);
            redirect('medicaments/', 'Prescription modifiée avec succès.', 'success');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">
                        <i class="fas fa-edit me-2 text-primary"></i>
                        Modifier la prescription
                    </h3>
                    <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
                
                <!-- Informations de la prescription -->
                <div class="alert alert-info">
                    <h6 class="alert-heading">Informations de la prescription :</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Patient :</strong> <?php echo htmlspecialchars($prescription['prenom'] . ' ' . $prescription['nom']); ?><br>
                            <strong>Médicament :</strong> <?php echo htmlspecialchars($prescription['medicament_nom']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Date de début :</strong> <?php echo formatDate($prescription['date_debut'], 'd/m/Y'); ?><br>
                            <strong>Prescrit le :</strong> <?php echo formatDate($prescription['date_saisie'], 'd/m/Y H:i'); ?>
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
                        <!-- Dosage et fréquence -->
                        <div class="col-md-6">
                            <label for="dosage" class="form-label">
                                Dosage <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="dosage" 
                                   name="dosage" 
                                   value="<?php echo htmlspecialchars($prescription['dosage']); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Le dosage est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="frequence" class="form-label">
                                Fréquence <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="frequence" 
                                   name="frequence" 
                                   value="<?php echo htmlspecialchars($prescription['frequence']); ?>"
                                   required>
                            <div class="invalid-feedback">
                                La fréquence est obligatoire.
                            </div>
                        </div>
                        
                        <!-- Date de fin -->
                        <div class="col-12">
                            <label for="date_fin" class="form-label">
                                Date de fin <small class="text-muted">(optionnel)</small>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_fin" 
                                   name="date_fin" 
                                   value="<?php echo $prescription['date_fin'] ?? ''; ?>"
                                   min="<?php echo $prescription['date_debut']; ?>">
                            <small class="text-muted">Laissez vide pour un traitement continu</small>
                        </div>
                        
                        <!-- Coût -->
                        <div class="col-12">
                            <div class="form-check mb-3">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="gratuit" 
                                       name="gratuit"
                                       <?php echo $prescription['gratuit'] ? 'checked' : ''; ?>
                                       onchange="toggleCout()">
                                <label class="form-check-label" for="gratuit">
                                    Médicament gratuit
                                </label>
                            </div>
                            
                            <div id="cout_div" style="<?php echo $prescription['gratuit'] ? 'display: none;' : ''; ?>">
                                <label for="cout" class="form-label">Coût total ($)</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="cout" 
                                       name="cout" 
                                       step="0.01"
                                       min="0"
                                       value="<?php echo $prescription['cout'] ?? '0.00'; ?>">
                            </div>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="col-12">
                            <label for="instructions" class="form-label">
                                Instructions spéciales <small class="text-muted">(optionnel)</small>
                            </label>
                            <textarea class="form-control" 
                                      id="instructions" 
                                      name="instructions" 
                                      rows="3"><?php echo htmlspecialchars($prescription['instructions'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-secondary">
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

<script>
function toggleCout() {
    const gratuit = document.getElementById('gratuit').checked;
    const coutDiv = document.getElementById('cout_div');
    
    if (gratuit) {
        coutDiv.style.display = 'none';
        document.getElementById('cout').value = '0.00';
    } else {
        coutDiv.style.display = 'block';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>