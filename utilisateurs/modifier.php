<?php
// utilisateurs/modifier.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Modifier un utilisateur - ' . SITE_NAME;
$currentPage = 'utilisateurs';

// Récupérer l'ID de l'utilisateur
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    redirect('utilisateurs/', 'Utilisateur non trouvé.', 'danger');
}

// Récupérer les données de l'utilisateur
$db = getDB();
$sql = "SELECT * FROM utilisateurs WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('utilisateurs/', 'Utilisateur non trouvé.', 'danger');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom_complet' => sanitize($_POST['nom_complet'] ?? ''),
        'role' => sanitize($_POST['role'] ?? 'agent_saisie'),
        'actif' => isset($_POST['actif']) ? 1 : 0
    ];
    
    // Validation
    $errors = [];
    if (empty($data['nom_complet'])) {
        $errors[] = "Le nom complet est obligatoire.";
    }
    
    // Empêcher la modification du rôle admin principal
    if ($user['nom_utilisateur'] === 'admin' && $data['role'] !== 'administrateur') {
        $errors[] = "Impossible de changer le rôle de l'administrateur principal.";
    }
    
    // Empêcher la désactivation de son propre compte
    if ($userId == $_SESSION['user_id'] && !$data['actif']) {
        $errors[] = "Vous ne pouvez pas désactiver votre propre compte.";
    }
    
    if (empty($errors)) {
        try {
            $updateSql = "UPDATE utilisateurs SET nom_complet = :nom_complet, role = :role, actif = :actif WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $result = $updateStmt->execute([
                ':nom_complet' => $data['nom_complet'],
                ':role' => $data['role'],
                ':actif' => $data['actif'],
                ':id' => $userId
            ]);
            
            if ($result) {
                logAction('Modification utilisateur', 'utilisateurs', $userId);
                redirect('utilisateurs/', 'Utilisateur modifié avec succès.', 'success');
            } else {
                $errors[] = "Erreur lors de la modification.";
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
        <div class="col-lg-8 mx-auto">
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">
                        <i class="fas fa-user-edit me-2 text-primary"></i>
                        Modifier l'utilisateur
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
                        <!-- Informations non modifiables -->
                        <div class="col-12">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Informations du compte
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($user['nom_utilisateur']); ?>"
                                   disabled>
                            <small class="text-muted">Le nom d'utilisateur ne peut pas être modifié</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date de création</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?php echo formatDate($user['date_creation'], 'd/m/Y H:i'); ?>"
                                   disabled>
                        </div>
                        
                        <!-- Informations modifiables -->
                        <div class="col-12 mt-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-edit me-2"></i>
                                Informations modifiables
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
                                   value="<?php echo htmlspecialchars($user['nom_complet']); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Le nom complet est obligatoire.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="role" class="form-label">Rôle</label>
                            <select class="form-select" 
                                    id="role" 
                                    name="role" 
                                    <?php echo $user['nom_utilisateur'] === 'admin' ? 'disabled' : ''; ?>>
                                <option value="agent_saisie" <?php echo $user['role'] === 'agent_saisie' ? 'selected' : ''; ?>>
                                    Agent de saisie
                                </option>
                                <option value="administrateur" <?php echo $user['role'] === 'administrateur' ? 'selected' : ''; ?>>
                                    Administrateur
                                </option>
                            </select>
                            <?php if ($user['nom_utilisateur'] === 'admin'): ?>
                                <input type="hidden" name="role" value="administrateur">
                                <small class="text-muted">Le rôle de l'administrateur principal ne peut pas être modifié</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Statut du compte</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="actif" 
                                       name="actif"
                                       <?php echo $user['actif'] ? 'checked' : ''; ?>
                                       <?php echo $userId == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="actif">
                                    Compte actif
                                </label>
                                <?php if ($userId == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="actif" value="1">
                                    <small class="text-muted d-block">Vous ne pouvez pas désactiver votre propre compte</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Dernière connexion -->
                        <?php if ($user['derniere_connexion']): ?>
                        <div class="col-12">
                            <label class="form-label">Dernière connexion</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?php echo formatDate($user['derniere_connexion'], 'd/m/Y H:i'); ?>"
                                   disabled>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions supplémentaires -->
                        <div class="col-12 mt-4">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Pour changer le mot de passe de cet utilisateur, utilisez la fonction 
                                <a href="<?php echo BASE_URL; ?>utilisateurs/reset_password.php?id=<?php echo $userId; ?>" class="alert-link">
                                    Réinitialiser le mot de passe
                                </a>
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

<?php require_once '../includes/footer.php'; ?>