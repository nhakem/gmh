<?php
// utilisateurs/reset_password.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Réinitialiser le mot de passe - ' . SITE_NAME;
$currentPage = 'utilisateurs';

// Récupérer l'ID de l'utilisateur
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    redirect('utilisateurs/', 'Utilisateur non trouvé.', 'danger');
}

// Récupérer les données de l'utilisateur
$db = getDB();
$sql = "SELECT id, nom_utilisateur, nom_complet FROM utilisateurs WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('utilisateurs/', 'Utilisateur non trouvé.', 'danger');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['nouveau_mot_de_passe'] ?? '';
    $confirmPassword = $_POST['confirmer_mot_de_passe'] ?? '';
    
    // Validation
    $errors = [];
    if (empty($newPassword)) {
        $errors[] = "Le nouveau mot de passe est obligatoire.";
    } elseif (strlen($newPassword) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            $updateSql = "UPDATE utilisateurs SET mot_de_passe = :password WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $result = $updateStmt->execute([
                ':password' => $hashedPassword,
                ':id' => $userId
            ]);
            
            if ($result) {
                logAction('Réinitialisation mot de passe', 'utilisateurs', $userId);
                redirect('utilisateurs/', 'Mot de passe réinitialisé avec succès.', 'success');
            } else {
                $errors[] = "Erreur lors de la réinitialisation.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur : " . $e->getMessage();
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
                        <i class="fas fa-key me-2 text-primary"></i>
                        Réinitialiser le mot de passe
                    </h3>
                    <a href="<?php echo BASE_URL; ?>utilisateurs/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Vous allez réinitialiser le mot de passe de l'utilisateur <strong><?php echo htmlspecialchars($user['nom_complet']); ?></strong> 
                    (<?php echo htmlspecialchars($user['nom_utilisateur']); ?>)
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
                            <label for="nouveau_mot_de_passe" class="form-label">
                                Nouveau mot de passe <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="nouveau_mot_de_passe" 
                                       name="nouveau_mot_de_passe"
                                       minlength="6"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('nouveau_mot_de_passe')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 6 caractères</small>
                            <div class="invalid-feedback">
                                Le mot de passe doit contenir au moins 6 caractères.
                            </div>
                        </div>
                        
                        <div class="col-12">
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
                        
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                L'utilisateur devra utiliser ce nouveau mot de passe pour se connecter.
                            </div>
                        </div>
                        
                        <!-- Boutons -->
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo BASE_URL; ?>utilisateurs/" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key me-2"></i>
                                    Réinitialiser le mot de passe
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
    const password = document.getElementById('nouveau_mot_de_passe').value;
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