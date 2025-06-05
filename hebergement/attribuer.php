<?php
// hebergement/attribuer.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Attribuer une chambre - ' . SITE_NAME;
$currentPage = 'hebergement';

$db = getDB();

// Si une personne est pré-sélectionnée
$personneId = intval($_GET['personne_id'] ?? 0);
$personneSelectionnee = null;

if ($personneId) {
    // Vérifier que la personne n'est pas déjà hébergée
    $checkSql = "SELECT COUNT(*) FROM nuitees WHERE personne_id = :personne_id AND actif = TRUE";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':personne_id' => $personneId]);
    
    if ($checkStmt->fetchColumn() > 0) {
        redirect('hebergement/', 'Cette personne est déjà hébergée.', 'warning');
    }
    
    $stmt = $db->prepare("SELECT id, nom, prenom FROM personnes WHERE id = :id AND actif = TRUE");
    $stmt->execute([':id' => $personneId]);
    $personneSelectionnee = $stmt->fetch();
}

// Si une chambre est pré-sélectionnée
$chambreId = intval($_GET['chambre_id'] ?? 0);
$chambreSelectionnee = null;

if ($chambreId) {
    // Vérifier que la chambre est disponible
    $checkSql = "SELECT COUNT(*) FROM nuitees WHERE chambre_id = :chambre_id AND actif = TRUE";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':chambre_id' => $chambreId]);
    
    if ($checkStmt->fetchColumn() > 0) {
        redirect('hebergement/', 'Cette chambre est déjà occupée.', 'warning');
    }
    
    $stmt = $db->prepare("SELECT c.*, th.nom as type_nom FROM chambres c JOIN types_hebergement th ON c.type_hebergement_id = th.id WHERE c.id = :id AND c.disponible = TRUE");
    $stmt->execute([':id' => $chambreId]);
    $chambreSelectionnee = $stmt->fetch();
}

// Récupérer les chambres disponibles
$chambresDisponiblesSql = "SELECT c.*, th.nom as type_hebergement_nom
    FROM chambres c
    JOIN types_hebergement th ON c.type_hebergement_id = th.id
    WHERE c.disponible = TRUE 
    AND c.id NOT IN (SELECT chambre_id FROM nuitees WHERE actif = TRUE)
    ORDER BY th.id, c.numero";
$chambresDisponibles = $db->query($chambresDisponiblesSql)->fetchAll();

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
        $errors[] = "Veuillez sélectionner une chambre.";
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
    
    // Vérifier que la chambre est disponible
    if ($data['chambre_id']) {
        $checkSql = "SELECT COUNT(*) FROM nuitees WHERE chambre_id = :chambre_id AND actif = TRUE";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':chambre_id' => $data['chambre_id']]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Cette chambre est déjà occupée.";
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
            logAction('Attribution chambre', 'nuitees', $nuiteeId, 
                "Personne ID: {$data['personne_id']}, Chambre ID: {$data['chambre_id']}, Mode: {$data['mode_paiement']}");
            
            redirect('hebergement/', 'Chambre attribuée avec succès.', 'success');
            
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
                        <i class="fas fa-bed me-2 text-primary"></i>
                        Attribuer une chambre
                    </h3>
                    <a href="<?php echo BASE_URL; ?>hebergement/" class="btn btn-outline-secondary">
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
                                Personne à héberger <span class="text-danger">*</span>
                            </label>
                            <?php if ($personneSelectionnee): ?>
                                <input type="hidden" name="personne_id" value="<?php echo $personneSelectionnee['id']; ?>">
                                <div class="alert alert-info">
                                    <i class="fas fa-user me-2"></i>
                                    <strong><?php echo htmlspecialchars($personneSelectionnee['prenom'] . ' ' . $personneSelectionnee['nom']); ?></strong>
                                    <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php" class="btn btn-sm btn-outline-primary float-end">
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
                                    
                                    foreach ($personnes as $p):
                                    ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo (isset($data['personne_id']) && $data['personne_id'] == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?> 
                                            <?php echo $p['role'] === 'benevole' ? '(Bénévole)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner une personne.
                                </div>
                                <?php if (empty($personnes)): ?>
                                    <div class="alert alert-warning mt-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Toutes les personnes actives sont déjà hébergées.
                                        <a href="<?php echo BASE_URL; ?>personnes/ajouter.php" class="alert-link">
                                            Ajouter une nouvelle personne
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sélection de la chambre -->
                        <div class="col-12">
                            <label for="chambre_id" class="form-label">
                                Chambre <span class="text-danger">*</span>
                            </label>
                            <?php if ($chambreSelectionnee): ?>
                                <input type="hidden" name="chambre_id" value="<?php echo $chambreSelectionnee['id']; ?>">
                                <div class="alert alert-info">
                                    <i class="fas fa-door-open me-2"></i>
                                    <strong>Chambre <?php echo htmlspecialchars($chambreSelectionnee['numero']); ?></strong>
                                    - <?php echo htmlspecialchars($chambreSelectionnee['type_nom']); ?>
                                    (<?php echo $chambreSelectionnee['nombre_lits']; ?> lit<?php echo $chambreSelectionnee['nombre_lits'] > 1 ? 's' : ''; ?>)
                                    <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php" class="btn btn-sm btn-outline-primary float-end">
                                        Changer
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php if (!empty($chambresDisponibles)): ?>
                                    <select class="form-select" id="chambre_id" name="chambre_id" required onchange="updateTarif()">
                                        <option value="">Sélectionner une chambre...</option>
                                        <?php
                                        $currentType = '';
                                        foreach ($chambresDisponibles as $chambre):
                                            if ($currentType !== $chambre['type_hebergement_nom']):
                                                if ($currentType !== '') echo '</optgroup>';
                                                $currentType = $chambre['type_hebergement_nom'];
                                                echo '<optgroup label="' . htmlspecialchars($currentType) . '">';
                                            endif;
                                        ?>
                                            <option value="<?php echo $chambre['id']; ?>" 
                                                    data-tarif="<?php echo $chambre['tarif_standard'] ?? 0; ?>"
                                                    <?php echo (isset($data['chambre_id']) && $data['chambre_id'] == $chambre['id']) ? 'selected' : ''; ?>>
                                                Chambre <?php echo htmlspecialchars($chambre['numero']); ?> 
                                                (<?php echo $chambre['nombre_lits']; ?> lit<?php echo $chambre['nombre_lits'] > 1 ? 's' : ''; ?>)
                                                <?php if ($chambre['tarif_standard'] > 0): ?>
                                                    - <?php echo number_format($chambre['tarif_standard'], 2); ?>$/jour
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($currentType !== '') echo '</optgroup>'; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une chambre.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Aucune chambre disponible.
                                        <a href="<?php echo BASE_URL; ?>hebergement/" class="alert-link">
                                            Voir les chambres occupées
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
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
                                   max="<?php echo date('Y-m-d'); ?>"
                                   required
                                   onchange="calculateTotal()">
                            <div class="invalid-feedback">
                                La date de début est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">
                                Date de fin prévue <small class="text-muted">(optionnel)</small>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_fin" 
                                   name="date_fin" 
                                   value="<?php echo isset($data['date_fin']) ? $data['date_fin'] : ''; ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   onchange="calculateTotal()">
                            <small class="text-muted">Laissez vide si la date de fin n'est pas connue</small>
                        </div>
                        
                        <!-- Mode de paiement et tarif -->
                        <div class="col-12 mt-3">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-dollar-sign me-2"></i>
                                Facturation
                            </h5>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="mode_paiement" class="form-label">Mode de paiement</label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" onchange="toggleTarif()">
                                <option value="gratuit" <?php echo (!isset($data['mode_paiement']) || $data['mode_paiement'] === 'gratuit') ? 'selected' : ''; ?>>
                                    Gratuit
                                </option>
                                <option value="comptant" <?php echo (isset($data['mode_paiement']) && $data['mode_paiement'] === 'comptant') ? 'selected' : ''; ?>>
                                    Comptant
                                </option>
                                <option value="credit" <?php echo (isset($data['mode_paiement']) && $data['mode_paiement'] === 'credit') ? 'selected' : ''; ?>>
                                    Crédit
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-4" id="tarif_div" style="display: none;">
                            <label for="tarif_journalier" class="form-label">Tarif journalier ($)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="tarif_journalier" 
                                   name="tarif_journalier" 
                                   step="0.01"
                                   min="0"
                                   value="<?php echo isset($data['tarif_journalier']) ? $data['tarif_journalier'] : '0.00'; ?>"
                                   onchange="calculateTotal()">
                        </div>
                        
                        <div class="col-md-4" id="total_div" style="display: none;">
                            <label class="form-label">Montant total estimé</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" 
                                       class="form-control" 
                                       id="montant_total" 
                                       readonly
                                       value="0.00">
                            </div>
                            <small class="text-muted">Calculé automatiquement si dates connues</small>
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes <small class="text-muted">(optionnel)</small></label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3"
                                      placeholder="Informations complémentaires..."><?php echo isset($data['notes']) ? htmlspecialchars($data['notes']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Résumé -->
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Rappel :</strong> Une personne ne peut occuper qu'une seule chambre à la fois.
                                Pour changer de chambre, il faut d'abord libérer la chambre actuelle.
                            </div>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>hebergement/" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Attribuer la chambre
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
// Validation des dates
document.getElementById('date_fin').addEventListener('change', function() {
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = this.value;
    
    if (dateDebut && dateFin && dateFin < dateDebut) {
        this.setCustomValidity('La date de fin doit être après la date de début.');
    } else {
        this.setCustomValidity('');
    }
    calculateTotal();
});

document.getElementById('date_debut').addEventListener('change', function() {
    const dateFin = document.getElementById('date_fin');
    dateFin.min = this.value;
    calculateTotal();
});

// Gestion du tarif
function toggleTarif() {
    const modePaiement = document.getElementById('mode_paiement').value;
    const tarifDiv = document.getElementById('tarif_div');
    const totalDiv = document.getElementById('total_div');
    
    if (modePaiement === 'gratuit') {
        tarifDiv.style.display = 'none';
        totalDiv.style.display = 'none';
    } else {
        tarifDiv.style.display = 'block';
        totalDiv.style.display = 'block';
        calculateTotal();
    }
}

// Mettre à jour le tarif selon la chambre sélectionnée
function updateTarif() {
    const chambreSelect = document.getElementById('chambre_id');
    const selectedOption = chambreSelect.options[chambreSelect.selectedIndex];
    const tarif = selectedOption.getAttribute('data-tarif') || 0;
    
    document.getElementById('tarif_journalier').value = parseFloat(tarif).toFixed(2);
    calculateTotal();
}

// Calculer le montant total
function calculateTotal() {
    const modePaiement = document.getElementById('mode_paiement').value;
    if (modePaiement === 'gratuit') return;
    
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = document.getElementById('date_fin').value;
    const tarif = parseFloat(document.getElementById('tarif_journalier').value) || 0;
    
    if (dateDebut && dateFin && tarif > 0) {
        const debut = new Date(dateDebut);
        const fin = new Date(dateFin);
        const diffTime = Math.abs(fin - debut);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 pour inclure le jour de départ
        
        const total = diffDays * tarif;
        document.getElementById('montant_total').value = total.toFixed(2);
    } else {
        document.getElementById('montant_total').value = '0.00';
    }
}

// Initialisation au chargement
document.addEventListener('DOMContentLoaded', function() {
    toggleTarif();
    updateTarif();
});
</script>

<?php require_once '../includes/footer.php'; ?>