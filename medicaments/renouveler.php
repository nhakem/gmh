<?php
// medicaments/renouveler.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Renouveler une prescription - ' . SITE_NAME;
$currentPage = 'medicaments';

$prescriptionId = intval($_GET['id'] ?? 0);
if (!$prescriptionId) {
    redirect('medicaments/', 'Prescription non trouvée.', 'danger');
}

$db = getDB();

// Récupérer la prescription à renouveler
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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'date_debut' => $_POST['date_debut'] ?? date('Y-m-d'),
        'date_fin' => !empty($_POST['date_fin']) ? $_POST['date_fin'] : null,
        'duree_jours' => intval($_POST['duree_jours'] ?? 0)
    ];
    
    // Si durée spécifiée, calculer la date de fin
    if ($data['duree_jours'] > 0 && empty($data['date_fin'])) {
        $dateDebut = new DateTime($data['date_debut']);
        $dateDebut->add(new DateInterval('P' . ($data['duree_jours'] - 1) . 'D'));
        $data['date_fin'] = $dateDebut->format('Y-m-d');
    }
    
    // Validation
    $errors = [];
    if ($data['date_fin'] && $data['date_fin'] < $data['date_debut']) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
    }
    
    if (empty($errors)) {
        try {
            // Créer une nouvelle prescription avec les mêmes paramètres
            $sql = "INSERT INTO prescriptions (personne_id, medicament_id, dosage, frequence, date_debut, date_fin, gratuit, cout, instructions, saisi_par) 
                    SELECT personne_id, medicament_id, dosage, frequence, :date_debut, :date_fin, gratuit, cout, instructions, :saisi_par
                    FROM prescriptions WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':date_debut' => $data['date_debut'],
                ':date_fin' => $data['date_fin'],
                ':saisi_par' => $_SESSION['user_id'],
                ':id' => $prescriptionId
            ]);
            
            $nouvellePrescriptionId = $db->lastInsertId();
            
            // Journaliser
            logAction('Renouvellement prescription', 'prescriptions', $nouvellePrescriptionId, 
                "Renouvellement de #$prescriptionId");
            
            redirect('medicaments/', 'Prescription renouvelée avec succès.', 'success');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors du renouvellement : " . $e->getMessage();
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
                        <i class="fas fa-redo me-2 text-primary"></i>
                        Renouveler la prescription
                    </h3>
                    <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
                
                <!-- Informations de la prescription -->
                <div class="alert alert-info">
                    <h6 class="alert-heading">Prescription à renouveler :</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Patient :</strong> <?php echo htmlspecialchars($prescription['prenom'] . ' ' . $prescription['nom']); ?><br>
                            <strong>Médicament :</strong> <?php echo htmlspecialchars($prescription['medicament_nom']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Dosage :</strong> <?php echo htmlspecialchars($prescription['dosage']); ?><br>
                            <strong>Fréquence :</strong> <?php echo htmlspecialchars($prescription['frequence']); ?>
                        </div>
                    </div>
                    <?php if ($prescription['instructions']): ?>
                        <div class="mt-2">
                            <strong>Instructions :</strong> <?php echo htmlspecialchars($prescription['instructions']); ?>
                        </div>
                    <?php endif; ?>
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
                            <label for="date_debut" class="form-label">
                                Date de début du renouvellement <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_debut" 
                                   name="date_debut" 
                                   value="<?php echo date('Y-m-d'); ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   required>
                            <div class="invalid-feedback">
                                La date de début est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Durée du renouvellement</label>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="duree_type" id="duree_predefinies" checked>
                                <label class="form-check-label" for="duree_predefinies">
                                    Durée prédéfinie
                                </label>
                            </div>
                            
                            <div class="btn-group d-block mt-2" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="setDuree(7)">7 jours</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setDuree(14)">14 jours</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setDuree(30)">30 jours</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setDuree(90)">3 mois</button>
                            </div>
                            
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="radio" name="duree_type" id="duree_personnalisee">
                                <label class="form-check-label" for="duree_personnalisee">
                                    Durée personnalisée
                                </label>
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <label for="duree_jours" class="form-label">Nombre de jours</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="duree_jours" 
                                           name="duree_jours" 
                                           min="1"
                                           value="30"
                                           onchange="calculateDateFin()">
                                </div>
                                <div class="col-md-6">
                                    <label for="date_fin" class="form-label">Ou date de fin</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="date_fin" 
                                           name="date_fin"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           onchange="document.getElementById('duree_personnalisee').checked = true;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                Le renouvellement créera une nouvelle prescription avec les mêmes paramètres 
                                (dosage, fréquence, coût, instructions).
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-redo me-2"></i>
                                    Renouveler la prescription
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
function setDuree(jours) {
    document.getElementById('duree_predefinies').checked = true;
    document.getElementById('duree_jours').value = jours;
    calculateDateFin();
}

function calculateDateFin() {
    const dateDebut = document.getElementById('date_debut').value;
    const dureeJours = parseInt(document.getElementById('duree_jours').value);
    
    if (dateDebut && dureeJours > 0) {
        const date = new Date(dateDebut);
        date.setDate(date.getDate() + dureeJours - 1);
        document.getElementById('date_fin').value = date.toISOString().split('T')[0];
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    calculateDateFin();
});
</script>

<?php require_once '../includes/footer.php'; ?>