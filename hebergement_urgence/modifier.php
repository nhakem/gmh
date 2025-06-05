<?php
// hebergement_urgence/modifier.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Modifier l\'hébergement d\'urgence - ' . SITE_NAME;
$currentPage = 'hebergement_urgence';

$db = getDB();

// Récupérer l'ID du type d'hébergement HD
$typeHDSql = "SELECT id FROM types_hebergement WHERE nom = 'HD'";
$typeHD = $db->query($typeHDSql)->fetch();
$typeHDId = $typeHD['id'] ?? null;

$nuiteeId = intval($_GET['id'] ?? 0);

if (!$nuiteeId) {
    redirect('hebergement_urgence/', 'ID de nuitée invalide.', 'error');
}

// Récupérer les informations de la nuitée
$sql = "SELECT n.*, c.numero as chambre_numero, c.type_hebergement_id, p.nom, p.prenom, th.nom as type_nom
        FROM nuitees n
        JOIN chambres c ON n.chambre_id = c.id
        JOIN personnes p ON n.personne_id = p.id
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        WHERE n.id = :id AND n.actif = TRUE AND th.nom = 'HD'";

$stmt = $db->prepare($sql);
$stmt->execute([':id' => $nuiteeId]);
$nuitee = $stmt->fetch();

if (!$nuitee) {
    redirect('hebergement_urgence/', 'Nuitée HD introuvable ou inactive.', 'error');
}

// Récupérer les chambres HD disponibles (incluant la chambre actuelle)
$chambresSql = "SELECT c.*, th.nom as type_hebergement_nom
    FROM chambres c
    JOIN types_hebergement th ON c.type_hebergement_id = th.id
    WHERE c.disponible = TRUE 
    AND c.type_hebergement_id = :type_id
    AND (c.id = :current_chambre OR c.id NOT IN (SELECT chambre_id FROM nuitees WHERE actif = TRUE))
    ORDER BY c.numero";
$stmt = $db->prepare($chambresSql);
$stmt->execute([':type_id' => $typeHDId, ':current_chambre' => $nuitee['chambre_id']]);
$chambres = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'chambre_id' => intval($_POST['chambre_id'] ?? $nuitee['chambre_id']),
        'date_debut' => $_POST['date_debut'] ?? $nuitee['date_debut'],
        'date_fin' => !empty($_POST['date_fin']) ? $_POST['date_fin'] : null,
        'mode_paiement' => sanitize($_POST['mode_paiement'] ?? 'gratuit'),
        'tarif_journalier' => floatval($_POST['tarif_journalier'] ?? 0)
    ];
    
    // Validation
    $errors = [];
    
    // Vérifier que les dates sont cohérentes
    if ($data['date_fin'] && $data['date_fin'] < $data['date_debut']) {
        $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
    }
    
    // Si changement de chambre, vérifier qu'elle est disponible et de type HD
    if ($data['chambre_id'] != $nuitee['chambre_id']) {
        $checkSql = "SELECT COUNT(*) FROM chambres c 
                     WHERE c.id = :chambre_id 
                     AND c.type_hebergement_id = :type_id 
                     AND c.id NOT IN (SELECT chambre_id FROM nuitees WHERE actif = TRUE AND id != :nuitee_id)";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([
            ':chambre_id' => $data['chambre_id'], 
            ':type_id' => $typeHDId,
            ':nuitee_id' => $nuiteeId
        ]);
        if ($checkStmt->fetchColumn() == 0) {
            $errors[] = "Cette chambre HD n'est pas disponible.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Calculer le montant total si nécessaire
            $montantTotal = null;
            if ($data['date_fin'] && $data['mode_paiement'] !== 'gratuit' && $data['tarif_journalier'] > 0) {
                $dateDebut = new DateTime($data['date_debut']);
                $dateFin = new DateTime($data['date_fin']);
                $duree = $dateFin->diff($dateDebut)->days + 1;
                $montantTotal = $duree * $data['tarif_journalier'];
            }
            
            $updateSql = "UPDATE nuitees SET 
                          chambre_id = :chambre_id,
                          date_debut = :date_debut,
                          date_fin = :date_fin,
                          mode_paiement = :mode_paiement,
                          tarif_journalier = :tarif_journalier,
                          montant_total = :montant_total
                          WHERE id = :id";
            
            $stmt = $db->prepare($updateSql);
            $stmt->execute([
                ':chambre_id' => $data['chambre_id'],
                ':date_debut' => $data['date_debut'],
                ':date_fin' => $data['date_fin'],
                ':mode_paiement' => $data['mode_paiement'],
                ':tarif_journalier' => $data['mode_paiement'] !== 'gratuit' ? $data['tarif_journalier'] : null,
                ':montant_total' => $montantTotal,
                ':id' => $nuiteeId
            ]);
            
            // Journaliser
            logAction('Modification hébergement HD', 'nuitees', $nuiteeId, 
                "Modifications effectuées pour {$nuitee['prenom']} {$nuitee['nom']}");
            
            redirect('hebergement_urgence/', 'Hébergement HD modifié avec succès.', 'success');
            
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
                        <i class="fas fa-edit me-2 text-danger"></i>
                        Modifier l'hébergement d'urgence
                    </h3>
                    <a href="<?php echo BASE_URL; ?>hebergement_urgence/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
                
                <div class="alert alert-info mb-4">
                    <i class="fas fa-user me-2"></i>
                    <strong>Personne :</strong> <?php echo htmlspecialchars($nuitee['prenom'] . ' ' . $nuitee['nom']); ?>
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
                        <!-- Chambre HD -->
                        <div class="col-12">
                            <label for="chambre_id" class="form-label">
                                Chambre d'urgence (HD) <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="chambre_id" name="chambre_id" required>
                                <?php foreach ($chambres as $chambre): ?>
                                    <option value="<?php echo $chambre['id']; ?>" 
                                            <?php echo $chambre['id'] == $nuitee['chambre_id'] ? 'selected' : ''; ?>
                                            data-tarif="<?php echo $chambre['tarif_standard']; ?>">
                                        Chambre <?php echo htmlspecialchars($chambre['numero']); ?> - 
                                        <?php echo $chambre['nombre_lits']; ?> lit<?php echo $chambre['nombre_lits'] > 1 ? 's' : ''; ?>
                                        <?php if ($chambre['id'] == $nuitee['chambre_id']): ?>
                                            (Actuelle)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Dates -->
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">
                                Date de début <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo $nuitee['date_debut']; ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">
                                Date de fin prévue
                            </label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin"
                                   value="<?php echo $nuitee['date_fin']; ?>">
                            <div class="form-text">
                                Laisser vide si la durée est indéterminée.
                            </div>
                        </div>
                        
                        <!-- Mode de paiement -->
                        <div class="col-md-6">
                            <label for="mode_paiement" class="form-label">
                                Mode de paiement <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="gratuit" <?php echo $nuitee['mode_paiement'] == 'gratuit' ? 'selected' : ''; ?>>
                                    Gratuit (urgence)
                                </option>
                                <option value="comptant" <?php echo $nuitee['mode_paiement'] == 'comptant' ? 'selected' : ''; ?>>
                                    Comptant
                                </option>
                                <option value="credit" <?php echo $nuitee['mode_paiement'] == 'credit' ? 'selected' : ''; ?>>
                                    Crédit
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6" id="tarif_container" 
                             style="<?php echo $nuitee['mode_paiement'] == 'gratuit' ? 'display: none;' : ''; ?>">
                            <label for="tarif_journalier" class="form-label">
                                Tarif journalier ($)
                            </label>
                            <input type="number" class="form-control" id="tarif_journalier" name="tarif_journalier" 
                                   min="0" step="0.01" value="<?php echo $nuitee['tarif_journalier'] ?? 0; ?>">
                        </div>
                        
                        <!-- Informations supplémentaires -->
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Informations :</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Date de saisie : <?php echo formatDate($nuitee['date_saisie'], 'd/m/Y H:i'); ?></li>
                                    <?php if ($nuitee['montant_total']): ?>
                                        <li>Montant total actuel : <?php echo number_format($nuitee['montant_total'], 2); ?> $</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save me-2"></i>
                                Enregistrer les modifications
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
        } else {
            tarifContainer.style.display = 'none';
            tarifInput.value = 0;
        }
    }
    
    modePaiement.addEventListener('change', updateTarifVisibility);
    
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