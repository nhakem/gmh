<?php
// repas/enregistrer.php - Enregistrement de repas
require_once '../config.php';
require_once '../includes/classes/Auth.php';

// Vérification authentification
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Enregistrer un repas - ' . SITE_NAME;
$currentPage = 'repas';

try {
    $db = getDB();
    
    // Récupérer les types de repas
    $typesRepas = $db->query("SELECT * FROM types_repas ORDER BY id")->fetchAll();
    
    // Si une personne est pré-sélectionnée (depuis la liste des personnes)
    $personneId = intval($_GET['personne_id'] ?? 0);
    $personneSelectionnee = null;
    
    if ($personneId) {
        $stmt = $db->prepare("SELECT id, nom, prenom, role FROM personnes WHERE id = :id AND actif = TRUE");
        $stmt->execute([':id' => $personneId]);
        $personneSelectionnee = $stmt->fetch();
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'personne_id' => intval($_POST['personne_id'] ?? 0),
            'type_repas_id' => intval($_POST['type_repas_id'] ?? 0),
            'date_repas' => sanitize($_POST['date_repas'] ?? date('Y-m-d')),
            'mode_paiement' => sanitize($_POST['mode_paiement'] ?? 'gratuit'),
            'montant' => floatval($_POST['montant'] ?? 0)
        ];
        
        // Pour enregistrement multiple
        $repasMultiples = isset($_POST['repas_multiples']) ? $_POST['repas_multiples'] : [];
        
        // Validation
        $errors = [];
        if (!$data['personne_id']) {
            $errors[] = "Veuillez sélectionner une personne.";
        }
        
        // Vérifier que la date n'est pas dans le futur
        if ($data['date_repas'] > date('Y-m-d')) {
            $errors[] = "La date du repas ne peut pas être dans le futur.";
        }
        
        // Vérifier qu'au moins un type de repas est sélectionné
        if (!$data['type_repas_id'] && empty($repasMultiples)) {
            $errors[] = "Veuillez sélectionner au moins un type de repas.";
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                $repasCrees = 0;
                
                // Si enregistrement multiple
                if (!empty($repasMultiples)) {
                    foreach ($repasMultiples as $typeRepasId) {
                        // Vérifier si ce repas n'existe pas déjà
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM repas WHERE personne_id = :personne_id AND type_repas_id = :type_repas_id AND date_repas = :date_repas");
                        $checkStmt->execute([
                            ':personne_id' => $data['personne_id'],
                            ':type_repas_id' => $typeRepasId,
                            ':date_repas' => $data['date_repas']
                        ]);
                        
                        if ($checkStmt->fetchColumn() == 0) {
                            $sql = "INSERT INTO repas (personne_id, type_repas_id, date_repas, mode_paiement, montant, saisi_par) 
                                    VALUES (:personne_id, :type_repas_id, :date_repas, :mode_paiement, :montant, :saisi_par)";
                            
                            $stmt = $db->prepare($sql);
                            $stmt->execute([
                                ':personne_id' => $data['personne_id'],
                                ':type_repas_id' => $typeRepasId,
                                ':date_repas' => $data['date_repas'],
                                ':mode_paiement' => $data['mode_paiement'],
                                ':montant' => $data['mode_paiement'] !== 'gratuit' ? $data['montant'] : null,
                                ':saisi_par' => $_SESSION['user_id']
                            ]);
                            $repasCrees++;
                        }
                    }
                    $message = "$repasCrees repas enregistrés avec succès.";
                } 
                // Sinon enregistrement simple
                else {
                    // Vérifier si ce repas n'existe pas déjà
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM repas WHERE personne_id = :personne_id AND type_repas_id = :type_repas_id AND date_repas = :date_repas");
                    $checkStmt->execute([
                        ':personne_id' => $data['personne_id'],
                        ':type_repas_id' => $data['type_repas_id'],
                        ':date_repas' => $data['date_repas']
                    ]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        throw new Exception("Ce repas a déjà été enregistré pour cette personne à cette date.");
                    }
                    
                    $sql = "INSERT INTO repas (personne_id, type_repas_id, date_repas, mode_paiement, montant, saisi_par) 
                            VALUES (:personne_id, :type_repas_id, :date_repas, :mode_paiement, :montant, :saisi_par)";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':personne_id' => $data['personne_id'],
                        ':type_repas_id' => $data['type_repas_id'],
                        ':date_repas' => $data['date_repas'],
                        ':mode_paiement' => $data['mode_paiement'],
                        ':montant' => $data['mode_paiement'] !== 'gratuit' ? $data['montant'] : null,
                        ':saisi_par' => $_SESSION['user_id']
                    ]);
                    $repasCrees = 1;
                    $message = "Repas enregistré avec succès.";
                }
                
                if ($repasCrees > 0) {
                    $db->commit();
                    
                    // Journaliser
                    logAction('Enregistrement repas', 'repas', $db->lastInsertId(), "Personne ID: " . $data['personne_id'] . ", $repasCrees repas");
                    
                    // Redirection avec option de continuer
                    if (isset($_POST['continuer'])) {
                        redirect('repas/enregistrer.php?personne_id=' . $data['personne_id'], $message, 'success');
                    } else {
                        redirect('repas/', $message, 'success');
                    }
                } else {
                    throw new Exception("Aucun nouveau repas à enregistrer (tous existent déjà).");
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
    error_log($error);
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="fade-in">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-utensils me-2 text-primary"></i>
                            Enregistrer un repas
                        </h3>
                        <a href="<?php echo BASE_URL; ?>repas/" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
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
                                    Personne <span class="text-danger">*</span>
                                </label>
                                <?php if ($personneSelectionnee): ?>
                                    <input type="hidden" name="personne_id" value="<?php echo $personneSelectionnee['id']; ?>">
                                    <div class="alert alert-info">
                                        <i class="fas fa-user me-2"></i>
                                        <strong><?php echo htmlspecialchars($personneSelectionnee['prenom'] . ' ' . $personneSelectionnee['nom']); ?></strong>
                                        <?php echo $personneSelectionnee['role'] === 'benevole' ? '(Bénévole)' : '(Client)'; ?>
                                        <a href="<?php echo BASE_URL; ?>repas/enregistrer.php" class="btn btn-sm btn-outline-primary float-end">
                                            Changer
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <select class="form-select" id="personne_id" name="personne_id" required>
                                        <option value="">Sélectionner une personne...</option>
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
                                        Veuillez sélectionner une personne.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Date du repas -->
                            <div class="col-md-6">
                                <label for="date_repas" class="form-label">
                                    Date du repas <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       id="date_repas" 
                                       name="date_repas" 
                                       value="<?php echo isset($data['date_repas']) ? $data['date_repas'] : date('Y-m-d'); ?>"
                                       max="<?php echo date('Y-m-d'); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    La date est obligatoire et ne peut pas être dans le futur.
                                </div>
                            </div>
                            
                            <!-- Mode de paiement -->
                            <div class="col-md-6">
                                <label for="mode_paiement" class="form-label">Mode de paiement</label>
                                <select class="form-select" id="mode_paiement" name="mode_paiement" onchange="toggleMontant()">
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
                            
                            <!-- Montant -->
                            <div class="col-md-6" id="montant_div" style="display: none;">
                                <label for="montant" class="form-label">Montant ($)</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="montant" 
                                       name="montant" 
                                       step="0.01"
                                       min="0"
                                       value="<?php echo isset($data['montant']) ? $data['montant'] : '5.00'; ?>">
                            </div>
                            
                            <!-- Type de repas -->
                            <div class="col-12">
                                <label class="form-label">Type de repas <span class="text-danger">*</span></label>
                                
                                <!-- Option 1: Enregistrement simple -->
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode_enregistrement" id="mode_simple" value="simple" checked onchange="toggleModeEnregistrement()">
                                    <label class="form-check-label" for="mode_simple">
                                        <strong>Enregistrement simple</strong> (un seul type de repas)
                                    </label>
                                </div>
                                
                                <div id="simple_div" class="mt-2">
                                    <select class="form-select" name="type_repas_id">
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($typesRepas as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" <?php echo (isset($data['type_repas_id']) && $data['type_repas_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Option 2: Enregistrement multiple -->
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="radio" name="mode_enregistrement" id="mode_multiple" value="multiple" onchange="toggleModeEnregistrement()">
                                    <label class="form-check-label" for="mode_multiple">
                                        <strong>Enregistrement multiple</strong> (plusieurs repas à la fois)
                                    </label>
                                </div>
                                
                                <div id="multiple_div" class="mt-2" style="display: none;">
                                    <div class="row">
                                        <?php foreach ($typesRepas as $type): ?>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="repas_multiples[]" value="<?php echo $type['id']; ?>" id="repas_<?php echo $type['id']; ?>">
                                                    <label class="form-check-label" for="repas_<?php echo $type['id']; ?>">
                                                        <?php echo htmlspecialchars($type['nom']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons -->
                            <div class="col-12 mt-4">
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="continuer" id="continuer" value="1">
                                        <label class="form-check-label" for="continuer">
                                            Continuer avec la même personne
                                        </label>
                                    </div>
                                    <div>
                                        <a href="<?php echo BASE_URL; ?>repas/" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>
                                            Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Enregistrer
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
</div>

<script>
function toggleMontant() {
    const modePaiement = document.getElementById('mode_paiement').value;
    const montantDiv = document.getElementById('montant_div');
    
    if (modePaiement === 'gratuit') {
        montantDiv.style.display = 'none';
    } else {
        montantDiv.style.display = 'block';
    }
}

function toggleModeEnregistrement() {
    const modeSimple = document.getElementById('mode_simple').checked;
    const simpleDiv = document.getElementById('simple_div');
    const multipleDiv = document.getElementById('multiple_div');
    
    if (modeSimple) {
        simpleDiv.style.display = 'block';
        multipleDiv.style.display = 'none';
        // Décocher tous les checkboxes
        document.querySelectorAll('input[name="repas_multiples[]"]').forEach(cb => cb.checked = false);
    } else {
        simpleDiv.style.display = 'none';
        multipleDiv.style.display = 'block';
        // Réinitialiser la sélection simple
        document.querySelector('select[name="type_repas_id"]').value = '';
    }
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', function() {
    toggleMontant();
    toggleModeEnregistrement();
});
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>