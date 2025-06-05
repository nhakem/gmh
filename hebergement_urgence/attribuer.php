<?php
// hebergement_urgence/attribuer.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Attribuer une chambre d\'urgence (HD) - ' . SITE_NAME;
$currentPage = 'hebergement_urgence';

$db = getDB();

// Récupérer l'ID du type d'hébergement HD
$typeHDSql = "SELECT id FROM types_hebergement WHERE nom = 'HD'";
$typeHD = $db->query($typeHDSql)->fetch();
$typeHDId = $typeHD['id'] ?? null;

// Si une personne est pré-sélectionnée
$personneId = intval($_GET['personne_id'] ?? 0);
$personneSelectionnee = null;

if ($personneId) {
    // Vérifier que la personne n'est pas déjà hébergée
    $checkSql = "SELECT COUNT(*) FROM nuitees WHERE personne_id = :personne_id AND actif = TRUE";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':personne_id' => $personneId]);
    
    if ($checkStmt->fetchColumn() > 0) {
        redirect('hebergement_urgence/', 'Cette personne est déjà hébergée.', 'warning');
    }
    
    $stmt = $db->prepare("SELECT id, nom, prenom FROM personnes WHERE id = :id AND actif = TRUE");
    $stmt->execute([':id' => $personneId]);
    $personneSelectionnee = $stmt->fetch();
}

// Si une chambre est pré-sélectionnée
$chambreId = intval($_GET['chambre_id'] ?? 0);
$chambreSelectionnee = null;

if ($chambreId) {
    // Vérifier que la chambre est disponible et est de type HD
    $checkSql = "SELECT COUNT(*) FROM nuitees WHERE chambre_id = :chambre_id AND actif = TRUE";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':chambre_id' => $chambreId]);
    
    if ($checkStmt->fetchColumn() > 0) {
        redirect('hebergement_urgence/', 'Cette chambre HD est déjà occupée.', 'warning');
    }
    
    $stmt = $db->prepare("SELECT c.*, th.nom as type_nom FROM chambres c JOIN types_hebergement th ON c.type_hebergement_id = th.id WHERE c.id = :id AND c.disponible = TRUE AND c.type_hebergement_id = :type_id");
    $stmt->execute([':id' => $chambreId, ':type_id' => $typeHDId]);
    $chambreSelectionnee = $stmt->fetch();
}

// Récupérer les chambres HD disponibles
$chambresDisponiblesSql = "SELECT c.*, th.nom as type_hebergement_nom
    FROM chambres c
    JOIN types_hebergement th ON c.type_hebergement_id = th.id
    WHERE c.disponible = TRUE 
    AND c.type_hebergement_id = :type_id
    AND c.id NOT IN (SELECT chambre_id FROM nuitees WHERE actif = TRUE)
    ORDER BY c.numero";
$stmt = $db->prepare($chambresDisponiblesSql);
$stmt->execute([':type_id' => $typeHDId]);
$chambresDisponibles = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'personne_id' => intval($_POST['personne_id'] ?? 0),
        'chambre_id' => intval($_POST['chambre_id'] ?? 0),
        'date_debut' => $_POST['date_debut'] ?? date('Y-m-d'),
        'date_fin' => !empty($_POST['date_fin']) ? $_POST['date_fin'] : null,
        'mode_paiement' => sanitize($_POST['mode_paiement'] ?? 'gratuit'),
        'tarif_journalier' => floatval($_POST['tarif_journalier'] ?? 0),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];
    
    // Validation
    $errors = [];
    if (!$data['personne_id']) {
        $errors[] = "Veuillez sélectionner une personne.";
    }
    if (!$data['chambre_id']) {
        $errors[] = "Veuillez sélectionner une chambre HD.";
    }
    
    // Vérifier que les dates sont cohérentes
    if ($data['date_fin'] && $data['date_fin'] < $data['date_debut']) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
    }
    
    // Vérifier que la personne n'est pas déjà hébergée
    if ($data['personne_id']) {
        $checkSql = "SELECT COUNT(*) FROM nuitees WHERE personne_id = :personne_id AND actif = TRUE";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':personne_id' => $data['personne_id']]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Cette personne est déjà hébergée dans une autre chambre.";
        }
    }
    
    // Vérifier que la chambre est disponible et est de type HD
    if ($data['chambre_id']) {
        $checkSql = "SELECT COUNT(*) FROM chambres c 
                     WHERE c.id = :chambre_id 
                     AND c.type_hebergement_id = :type_id 
                     AND c.id NOT IN (SELECT chambre_id FROM nuitees WHERE actif = TRUE)";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':chambre_id' => $data['chambre_id'], ':type_id' => $typeHDId]);
        if ($checkStmt->fetchColumn() == 0) {
            $errors[] = "Cette chambre HD n'est pas disponible.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Calculer le montant total si date de fin connue et mode payant
            $montantTotal = null;
            if ($data['date_fin'] && $data['mode_paiement'] !== 'gratuit' && $data['tarif_journalier'] > 0) {
                $dateDebut = new DateTime($data['date_debut']);
                $dateFin = new DateTime($data['date_fin']);
                $duree = $dateFin->diff($dateDebut)->days + 1;
                $montantTotal = $duree * $data['tarif_journalier'];
            }
            
            $sql = "INSERT INTO nuitees (personne_id, chambre_id, date_debut, date_fin, mode_paiement, tarif_journalier, montant_total, actif, saisi_par) 
                    VALUES (:personne_id, :chambre_id, :date_debut, :date_fin, :mode_paiement, :tarif_journalier, :montant_total, TRUE, :saisi_par)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':personne_id' => $data['personne_id'],
                ':chambre_id' => $data['chambre_id'],
                ':date_debut' => $data['date_debut'],
                ':date_fin' => $data['date_fin'],
                ':mode_paiement' => $data['mode_paiement'],
                ':tarif_journalier' => $data['mode_paiement'] !== 'gratuit' ? $data['tarif_journalier'] : null,
                ':montant_total' => $montantTotal,
                ':saisi_par' => $_SESSION['user_id']
            ]);
            
            $nuiteeId = $db->lastInsertId();
            
            // Journaliser
            logAction('Attribution chambre HD', 'nuitees', $nuiteeId, 
                "Personne ID: {$data['personne_id']}, Chambre HD ID: {$data['chambre_id']}, Mode: {$data['mode_paiement']}");
            
            redirect('hebergement_urgence/', 'Chambre HD attribuée avec succès.', 'success');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'attribution : " . $e->getMessage();
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
                        <i class="fas fa-ambulance me-2 text-danger"></i>
                        Attribuer une chambre d'urgence (HD)
                    </h3>
                    <a href="<?php echo BASE_URL; ?>hebergement_urgence/" class="btn btn-outline-secondary">
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
                                Personne à héberger en urgence <span class="text-danger">*</span>
                            </label>
                            <?php if ($personneSelectionnee): ?>
                                <input type="hidden" name="personne_id" value="<?php echo $personneSelectionnee['id']; ?>">
                                <div class="alert alert-info">
                                    <i class="fas fa-user me-2"></i>
                                    <strong><?php echo htmlspecialchars($personneSelectionnee['prenom'] . ' ' . $personneSelectionnee['nom']); ?></strong>
                                    <a href="<?php echo BASE_URL; ?>hebergement_urgence/attribuer.php" class="btn btn-sm btn-outline-primary float-end">
                                        Changer
                                    </a>
                                </div>
                            <?php else: ?>
                                <select class="form-select" id="personne_id" name="personne_id" required>
                                    <option value="">Sélectionner une personne...</option>
                                    <?php
                                    // Récupérer seulement les personnes non hébergées
                                    $personnesSql = "SELECT p.id, p.nom, p.prenom, p.role 
                                                    FROM personnes p 
                                                    WHERE p.actif = TRUE 
                                                    AND p.id NOT IN (SELECT personne_id FROM nuitees WHERE actif = TRUE)
                                                    ORDER BY p.nom, p.prenom";
                                    $personnes = $db->query($personnesSql)->fetchAll();
                                    foreach ($personnes as $personne):
                                    ?>
                                        <option value="<?php echo $personne['id']; ?>">
                                            <?php echo htmlspecialchars($personne['nom'] . ' ' . $personne['prenom']); ?>
                                            <?php if ($personne['role'] === 'benevole'): ?>
                                                <span class="text-info">(Bénévole)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Seules les personnes non hébergées sont affichées.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sélection de la chambre HD -->
                        <div class="col-12">
                            <label for="chambre_id" class="form-label">
                                Chambre d'urgence (HD) <span class="text-danger">*</span>
                            </label>
                            <?php if ($chambreSelectionnee): ?>
                                <input type="hidden" name="chambre_id" value="<?php echo $chambreSelectionnee['id']; ?>">
                                <div class="alert alert-info">
                                    <i class="fas fa-door-open me-2"></i>
                                    <strong>Chambre <?php echo htmlspecialchars($chambreSelectionnee['numero']); ?></strong>
                                    (<?php echo $chambreSelectionnee['nombre_lits']; ?> lit<?php echo $chambreSelectionnee['nombre_lits'] > 1 ? 's' : ''; ?>)
                                    <a href="<?php echo BASE_URL; ?>hebergement_urgence/attribuer.php" class="btn btn-sm btn-outline-primary float-end">
                                        Changer
                                    </a>
                                </div>
                            <?php else: ?>
                                <select class="form-select" id="chambre_id" name="chambre_id" required>
                                    <option value="">Sélectionner une chambre HD...</option>
                                    <?php if (count($chambresDisponibles) > 0): ?>
                                        <?php foreach ($chambresDisponibles as $chambre): ?>
                                            <option value="<?php echo $chambre['id']; ?>" 
                                                    data-tarif="<?php echo $chambre['tarif_standard']; ?>">
                                                Chambre <?php echo htmlspecialchars($chambre['numero']); ?> - 
                                                <?php echo $chambre['nombre_lits']; ?> lit<?php echo $chambre['nombre_lits'] > 1 ? 's' : ''; ?>
                                                <?php if ($chambre['tarif_standard'] > 0): ?>
                                                    (<?php echo number_format($chambre['tarif_standard'], 2); ?>$/nuit)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Aucune chambre HD disponible</option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Hébergement d'urgence uniquement
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Dates -->
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">
                                Date de début <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">
                                Date de fin prévue
                            </label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin">
                            <div class="form-text">
                                Laisser vide si la durée est indéterminée (urgence).
                            </div>
                        </div>
                        
                        <!-- Mode de paiement -->
                        <div class="col-md-6">
                            <label for="mode_paiement" class="form-label">
                                Mode de paiement <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="gratuit" selected>Gratuit (urgence)</option>
                                <option value="comptant">Comptant</option>
                                <option value="credit">Crédit</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6" id="tarif_container" style="display: none;">
                            <label for="tarif_journalier" class="form-label">
                                Tarif journalier ($)
                            </label>
                            <input type="number" class="form-control" id="tarif_journalier" name="tarif_journalier" 
                                   min="0" step="0.01" value="0">
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes / Raison de l'urgence</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Indiquer la raison de l'hébergement d'urgence..."></textarea>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save me-2"></i>
                                Attribuer la chambre HD
                            </button>
                            <a href="<?php echo BASE_URL; ?>hebergement_urgence/" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>
                                Annuler
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du tarif selon le mode de paiement
    const modePaiement = document.getElementById('mode_paiement');
    const tarifContainer = document.getElementById('tarif_container');
    const tarifInput = document.getElementById('tarif_journalier');
    const chambreSelect = document.getElementById('chambre_id');
    
    function updateTarifVisibility() {
        if (modePaiement.value !== 'gratuit') {
            tarifContainer.style.display = 'block';
            // Mettre à jour le tarif selon la chambre sélectionnée
            if (chambreSelect.selectedOptions[0] && chambreSelect.selectedOptions[0].dataset.tarif) {
                tarifInput.value = chambreSelect.selectedOptions[0].dataset.tarif;
            }
        } else {
            tarifContainer.style.display = 'none';
            tarifInput.value = 0;
        }
    }
    
    modePaiement.addEventListener('change', updateTarifVisibility);
    chambreSelect.addEventListener('change', updateTarifVisibility);
    
    // Validation des dates
    const dateDebut = document.getElementById('date_debut');
    const dateFin = document.getElementById('date_fin');
    
    dateFin.addEventListener('change', function() {
        if (this.value && this.value < dateDebut.value) {
            this.setCustomValidity('La date de fin doit être après la date de début');
        } else {
            this.setCustomValidity('');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>