<?php
// medicaments/prescrire.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Prescrire un médicament - ' . SITE_NAME;
$currentPage = 'medicaments';

$db = getDB();

// Si une personne est pré-sélectionnée
$personneId = intval($_GET['personne_id'] ?? 0);
$personneSelectionnee = null;

if ($personneId) {
    $stmt = $db->prepare("SELECT id, nom, prenom FROM personnes WHERE id = :id AND actif = TRUE");
    $stmt->execute([':id' => $personneId]);
    $personneSelectionnee = $stmt->fetch();
}

// Récupérer la liste des médicaments
$medicaments = $db->query("SELECT * FROM medicaments ORDER BY nom")->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'personne_id' => intval($_POST['personne_id'] ?? 0),
        'medicament_id' => intval($_POST['medicament_id'] ?? 0),
        'dosage' => sanitize($_POST['dosage'] ?? ''),
        'frequence' => sanitize($_POST['frequence'] ?? ''),
        'date_debut' => $_POST['date_debut'] ?? date('Y-m-d'),
        'date_fin' => !empty($_POST['date_fin']) ? $_POST['date_fin'] : null,
        'gratuit' => isset($_POST['gratuit']) ? 1 : 0,
        'cout' => floatval($_POST['cout'] ?? 0),
        'instructions' => sanitize($_POST['instructions'] ?? '')
    ];
    
    // Validation
    $errors = [];
    if (!$data['personne_id']) {
        $errors[] = "Veuillez sélectionner une personne.";
    }
    if (!$data['medicament_id']) {
        $errors[] = "Veuillez sélectionner un médicament.";
    }
    if (empty($data['dosage'])) {
        $errors[] = "Le dosage est obligatoire.";
    }
    if (empty($data['frequence'])) {
        $errors[] = "La fréquence est obligatoire.";
    }
    
    // Vérifier les dates
    if ($data['date_fin'] && $data['date_fin'] < $data['date_debut']) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
    }
    
    // Vérifier si la personne a déjà ce médicament en cours
    if ($data['personne_id'] && $data['medicament_id']) {
        $checkSql = "SELECT COUNT(*) FROM prescriptions 
                     WHERE personne_id = :personne_id 
                     AND medicament_id = :medicament_id 
                     AND date_debut <= CURDATE() 
                     AND (date_fin IS NULL OR date_fin >= CURDATE())";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([
            ':personne_id' => $data['personne_id'],
            ':medicament_id' => $data['medicament_id']
        ]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Cette personne a déjà une prescription active pour ce médicament.";
        }
    }
    
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO prescriptions (personne_id, medicament_id, dosage, frequence, date_debut, date_fin, gratuit, cout, instructions, saisi_par) 
                    VALUES (:personne_id, :medicament_id, :dosage, :frequence, :date_debut, :date_fin, :gratuit, :cout, :instructions, :saisi_par)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':personne_id' => $data['personne_id'],
                ':medicament_id' => $data['medicament_id'],
                ':dosage' => $data['dosage'],
                ':frequence' => $data['frequence'],
                ':date_debut' => $data['date_debut'],
                ':date_fin' => $data['date_fin'],
                ':gratuit' => $data['gratuit'],
                ':cout' => !$data['gratuit'] ? $data['cout'] : null,
                ':instructions' => $data['instructions'],
                ':saisi_par' => $_SESSION['user_id']
            ]);
            
            $prescriptionId = $db->lastInsertId();
            
            // Journaliser
            logAction('Nouvelle prescription', 'prescriptions', $prescriptionId, 
                "Personne ID: {$data['personne_id']}, Médicament ID: {$data['medicament_id']}");
            
            // Redirection avec option de continuer
            if (isset($_POST['continuer'])) {
                redirect('medicaments/prescrire.php?personne_id=' . $data['personne_id'], 
                    'Prescription enregistrée avec succès.', 'success');
            } else {
                redirect('medicaments/', 'Prescription enregistrée avec succès.', 'success');
            }
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
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
                        <i class="fas fa-prescription me-2 text-primary"></i>
                        Prescrire un médicament
                    </h3>
                    <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
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
                        <!-- Sélection de la personne -->
                        <div class="col-12">
                            <label for="personne_id" class="form-label">
                                Patient <span class="text-danger">*</span>
                            </label>
                            <?php if ($personneSelectionnee): ?>
                                <input type="hidden" name="personne_id" value="<?php echo $personneSelectionnee['id']; ?>">
                                <div class="alert alert-info">
                                    <i class="fas fa-user me-2"></i>
                                    <strong><?php echo htmlspecialchars($personneSelectionnee['prenom'] . ' ' . $personneSelectionnee['nom']); ?></strong>
                                    <a href="<?php echo BASE_URL; ?>medicaments/prescrire.php" class="btn btn-sm btn-outline-primary float-end">
                                        Changer
                                    </a>
                                </div>
                            <?php else: ?>
                                <select class="form-select" id="personne_id" name="personne_id" required>
                                    <option value="">Sélectionner un patient...</option>
                                    <?php
                                    $personnes = $db->query("SELECT id, nom, prenom, role FROM personnes WHERE actif = TRUE ORDER BY nom, prenom")->fetchAll();
                                    foreach ($personnes as $p):
                                    ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo (isset($data['personne_id']) && $data['personne_id'] == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?> 
                                            <?php echo $p['role'] === 'benevole' ? '(Bénévole)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un patient.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sélection du médicament -->
                        <div class="col-12">
                            <label for="medicament_id" class="form-label">
                                Médicament <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="medicament_id" name="medicament_id" required>
                                <option value="">Sélectionner un médicament...</option>
                                <?php foreach ($medicaments as $med): ?>
                                    <option value="<?php echo $med['id']; ?>" <?php echo (isset($data['medicament_id']) && $data['medicament_id'] == $med['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($med['nom']); ?>
                                        <?php if ($med['forme']): ?>
                                            - <?php echo htmlspecialchars($med['forme']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un médicament.
                            </div>
                            <?php if (empty($medicaments)): ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Aucun médicament disponible. 
                                    <?php if (hasPermission('administrateur')): ?>
                                        <a href="<?php echo BASE_URL; ?>medicaments/gerer.php" class="alert-link">
                                            Ajouter des médicaments
                                        </a>
                                    <?php else: ?>
                                        Contactez un administrateur.
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Dosage et fréquence -->
                        <div class="col-md-6">
                            <label for="dosage" class="form-label">
                                Dosage <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="dosage" 
                                   name="dosage" 
                                   value="<?php echo isset($data['dosage']) ? htmlspecialchars($data['dosage']) : ''; ?>"
                                   placeholder="Ex: 500mg, 2 comprimés"
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
                                   value="<?php echo isset($data['frequence']) ? htmlspecialchars($data['frequence']) : ''; ?>"
                                   placeholder="Ex: 3 fois par jour, matin et soir"
                                   required>
                            <div class="invalid-feedback">
                                La fréquence est obligatoire.
                            </div>
                        </div>
                        
                        <!-- Dates -->
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">
                                Date de début <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_debut" 
                                   name="date_debut" 
                                   value="<?php echo isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d'); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                                   required>
                            <div class="invalid-feedback">
                                La date de début est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">
                                Date de fin <small class="text-muted">(optionnel)</small>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_fin" 
                                   name="date_fin" 
                                   value="<?php echo isset($data['date_fin']) ? $data['date_fin'] : ''; ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                            <small class="text-muted">Laissez vide pour un traitement continu</small>
                        </div>
                        
                        <!-- Coût -->
                        <div class="col-12">
                            <div class="form-check mb-3">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="gratuit" 
                                       name="gratuit"
                                       <?php echo (!isset($data['gratuit']) || $data['gratuit']) ? 'checked' : ''; ?>
                                       onchange="toggleCout()">
                                <label class="form-check-label" for="gratuit">
                                    Médicament gratuit
                                </label>
                            </div>
                            
                            <div id="cout_div" style="display: none;">
                                <label for="cout" class="form-label">Coût total ($)</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="cout" 
                                       name="cout" 
                                       step="0.01"
                                       min="0"
                                       value="<?php echo isset($data['cout']) ? $data['cout'] : '0.00'; ?>"
                                       placeholder="0.00">
                                <small class="text-muted">Coût total pour la durée du traitement</small>
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
                                      rows="3"
                                      placeholder="Ex: À prendre avec de la nourriture, éviter l'alcool..."><?php echo isset($data['instructions']) ? htmlspecialchars($data['instructions']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="continuer" id="continuer" value="1">
                                    <label class="form-check-label" for="continuer">
                                        Continuer avec le même patient
                                    </label>
                                </div>
                                <div>
                                    <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>
                                        Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        Enregistrer la prescription
                                    </button>
                                </div>
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

// Validation des dates
document.getElementById('date_fin').addEventListener('change', function() {
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = this.value;
    
    if (dateDebut && dateFin && dateFin < dateDebut) {
        this.setCustomValidity('La date de fin doit être après la date de début.');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('date_debut').addEventListener('change', function() {
    const dateFin = document.getElementById('date_fin');
    dateFin.min = this.value;
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    toggleCout();
});
</script>

<?php require_once '../includes/footer.php'; ?>