<?php
// utilisateurs/ajouter.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Ajouter un utilisateur - ' . SITE_NAME;
$currentPage = 'utilisateurs';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom_utilisateur' => sanitize($_POST['nom_utilisateur'] ?? ''),
        'nom_complet' => sanitize($_POST['nom_complet'] ?? ''),
        'mot_de_passe' => $_POST['mot_de_passe'] ?? '',
        'confirmer_mot_de_passe' => $_POST['confirmer_mot_de_passe'] ?? '',
        'role' => sanitize($_POST['role'] ?? 'agent_saisie')
    ];
    
    // Validation
    $errors = [];
    if (empty($data['nom_utilisateur'])) {
        $errors[] = "Le nom d'utilisateur est obligatoire.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['nom_utilisateur'])) {
        $errors[] = "Le nom d'utilisateur doit contenir entre 3 et 20 caractères alphanumériques.";
    }
    
    if (empty($data['nom_complet'])) {
        $errors[] = "Le nom complet est obligatoire.";
    }
    
    if (empty($data['mot_de_passe'])) {
        $errors[] = "Le mot de passe est obligatoire.";
    } elseif (strlen($data['mot_de_passe']) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    
    if ($data['mot_de_passe'] !== $data['confirmer_mot_de_passe']) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    if (empty($errors)) {
        $result = $auth->createUser($data);
        
        if ($result['success']) {
            redirect('utilisateurs/', $result['message'], 'success');
        } else {
            $errors[] = $result['message'];
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
                        Ajouter un utilisateur
                    </h3>
                    <a href="<?php echo BASE_URL; ?>utilisateurs/" class="btn btn-outline-secondary">
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
                        <!-- Informations de connexion -->
                        <div class="col-12">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-lock me-2"></i>
                                Informations de connexion
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nom_utilisateur" class="form-label">
                                Nom d'utilisateur <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom_utilisateur" 
                                   name="nom_utilisateur" 
                                   value="<?php echo isset($data['nom_utilisateur']) ? htmlspecialchars($data['nom_utilisateur']) : ''; ?>"
                                   pattern="[a-zA-Z0-9_]{3,20}"
                                   required>
                            <small class="text-muted">3-20 caractères, lettres, chiffres et underscore uniquement</small>
                            <div class="invalid-feedback">
                                Nom d'utilisateur invalide.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="agent_saisie" <?php echo (!isset($data['role']) || $data['role'] === 'agent_saisie') ? 'selected' : ''; ?>>
                                    Agent de saisie
                                </option>
                                <option value="administrateur" <?php echo (isset($data['role']) && $data['role'] === 'administrateur') ? 'selected' : ''; ?>>
                                    Administrateur
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un rôle.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="mot_de_passe" class="form-label">
                                Mot de passe <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="mot_de_passe" 
                                       name="mot_de_passe"
                                       minlength="6"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('mot_de_passe')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 6 caractères</small>
                            <div class="invalid-feedback">
                                Le mot de passe doit contenir au moins 6 caractères.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="confirmer_mot_de_passe" class="form-label">
                                Confirmer le mot de passe <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="confirmer_mot_de_passe" 
                                       name="confirmer_mot_de_passe"
                                       minlength="6"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmer_mot_de_passe')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Les mots de passe doivent correspondre.
                            </div>
                        </div>
                        
                        <!-- Informations personnelles -->
                        <div class="col-12 mt-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-user me-2"></i>
                                Informations personnelles
                            </h5>
                        </div>
                        
                        <div class="col-12">
                            <label for="nom_complet" class="form-label">
                                Nom complet <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom_complet" 
                                   name="nom_complet" 
                                   value="<?php echo isset($data['nom_complet']) ? htmlspecialchars($data['nom_complet']) : ''; ?>"
                                   required>
                            <div class="invalid-feedback">
                                Le nom complet est obligatoire.
                            </div>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12 mt-4">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>utilisateurs/" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Créer l'utilisateur
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
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('mot_de_passe').value;
    const confirmPassword = document.getElementById('confirmer_mot_de_passe').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        document.getElementById('confirmer_mot_de_passe').setCustomValidity('Les mots de passe ne correspondent pas.');
    } else {
        document.getElementById('confirmer_mot_de_passe').setCustomValidity('');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>