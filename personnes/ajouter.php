<?php
// personnes/ajouter.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Ajouter une personne - ' . SITE_NAME;
$currentPage = 'personnes';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Récupération et validation des données
    $data = [
        'nom' => sanitize($_POST['nom'] ?? ''),
        'prenom' => sanitize($_POST['prenom'] ?? ''),
        'sexe' => sanitize($_POST['sexe'] ?? ''),
        'date_naissance' => $_POST['date_naissance'] ?? null,
        'age' => intval($_POST['age'] ?? 0),
        'ville' => sanitize($_POST['ville'] ?? ''),
        'origine' => sanitize($_POST['origine'] ?? ''),
        'role' => sanitize($_POST['role'] ?? 'client'),
        'telephone' => sanitize($_POST['telephone'] ?? ''),
        'email' => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)
    ];
    
    // Validation
    $errors = [];
    if (empty($data['nom'])) $errors[] = "Le nom est obligatoire.";
    if (empty($data['prenom'])) $errors[] = "Le prénom est obligatoire.";
    if (empty($data['sexe'])) $errors[] = "Le sexe est obligatoire.";
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if (empty($errors)) {
        try {
            // Si pas de date de naissance mais un âge, calculer une date approximative
            if (empty($data['date_naissance']) && $data['age'] > 0) {
                $data['date_naissance'] = date('Y-m-d', strtotime("-{$data['age']} years"));
            }
            
            $sql = "INSERT INTO personnes (nom, prenom, sexe, date_naissance, age, ville, origine, role, telephone, email) 
                    VALUES (:nom, :prenom, :sexe, :date_naissance, :age, :ville, :origine, :role, :telephone, :email)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nom' => $data['nom'],
                ':prenom' => $data['prenom'],
                ':sexe' => $data['sexe'],
                ':date_naissance' => $data['date_naissance'],
                ':age' => $data['age'],
                ':ville' => $data['ville'],
                ':origine' => $data['origine'],
                ':role' => $data['role'],
                ':telephone' => $data['telephone'],
                ':email' => $data['email']
            ]);
            
            $personneId = $db->lastInsertId();
            
            // Journaliser l'action
            logAction('Ajout personne', 'personnes', $personneId, "Nouvelle personne : {$data['prenom']} {$data['nom']}");
            
            // Redirection avec message de succès
            redirect('personnes/', 'Personne ajoutée avec succès.', 'success');
            
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
                        <i class="fas fa-user-plus me-2 text-primary"></i>
                        Ajouter une personne
                    </h3>
                    <a href="<?php echo BASE_URL; ?>personnes/" class="btn btn-outline-secondary">
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
                        <!-- Informations personnelles -->
                        <div class="col-12">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Informations personnelles
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   value="<?php echo isset($data['nom']) ? htmlspecialchars($data['nom']) : ''; ?>"
                                   required>
                            <div class="invalid-feedback">
                                Le nom est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="prenom" 
                                   name="prenom" 
                                   value="<?php echo isset($data['prenom']) ? htmlspecialchars($data['prenom']) : ''; ?>"
                                   required>
                            <div class="invalid-feedback">
                                Le prénom est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">Sélectionner...</option>
                                <option value="M" <?php echo (isset($data['sexe']) && $data['sexe'] === 'M') ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo (isset($data['sexe']) && $data['sexe'] === 'F') ? 'selected' : ''; ?>>Féminin</option>
                                <option value="Autre" <?php echo (isset($data['sexe']) && $data['sexe'] === 'Autre') ? 'selected' : ''; ?>>Autre</option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner le sexe.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_naissance" 
                                   name="date_naissance"
                                   value="<?php echo isset($data['date_naissance']) ? $data['date_naissance'] : ''; ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="age" class="form-label">Âge</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="age" 
                                   name="age"
                                   value="<?php echo isset($data['age']) ? $data['age'] : ''; ?>"
                                   min="0" 
                                   max="120">
                            <small class="text-muted">Calculé automatiquement si date de naissance renseignée</small>
                        </div>
                        
                        <!-- Origine et rôle -->
                        <div class="col-12 mt-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                Origine et rôle
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="ville" class="form-label">Ville</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="ville" 
                                   name="ville"
                                   value="<?php echo isset($data['ville']) ? htmlspecialchars($data['ville']) : ''; ?>"
                                   placeholder="Ex: Montréal">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="origine" class="form-label">Pays d'origine</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="origine" 
                                   name="origine"
                                   value="<?php echo isset($data['origine']) ? htmlspecialchars($data['origine']) : ''; ?>"
                                   placeholder="Ex: Canada">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="role" class="form-label">Rôle</label>
                            <select class="form-select" id="role" name="role">
                                <option value="client" <?php echo (!isset($data['role']) || $data['role'] === 'client') ? 'selected' : ''; ?>>Client</option>
                                <option value="benevole" <?php echo (isset($data['role']) && $data['role'] === 'benevole') ? 'selected' : ''; ?>>Bénévole</option>
                            </select>
                        </div>
                        
                        <!-- Contact -->
                        <div class="col-12 mt-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-phone me-2"></i>
                                Coordonnées
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="telephone" 
                                   name="telephone"
                                   value="<?php echo isset($data['telephone']) ? htmlspecialchars($data['telephone']) : ''; ?>"
                                   placeholder="Ex: 514-123-4567">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email"
                                   value="<?php echo isset($data['email']) ? htmlspecialchars($data['email']) : ''; ?>"
                                   placeholder="exemple@email.com">
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12 mt-4">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>personnes/" class="btn btn-secondary">
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
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>