<?php
// utilisateurs/index.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Gestion des utilisateurs - ' . SITE_NAME;
$currentPage = 'utilisateurs';

// Récupérer la liste des utilisateurs
$users = $auth->getUsers();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-user-cog me-2 text-primary"></i>
            Gestion des utilisateurs
        </h1>
        <a href="<?php echo BASE_URL; ?>utilisateurs/ajouter.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            Nouvel utilisateur
        </a>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Nom complet</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['nom_utilisateur']); ?></strong>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <span class="badge bg-info ms-1">Vous</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['nom_complet']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'administrateur' ? 'danger' : 'primary'; ?>">
                                <?php echo $user['role'] === 'administrateur' ? 'Administrateur' : 'Agent de saisie'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['actif']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i> Actif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-times-circle"></i> Inactif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($user['date_creation'], 'd/m/Y H:i'); ?></td>
                        <td>
                            <?php echo $user['derniere_connexion'] ? formatDate($user['derniere_connexion'], 'd/m/Y H:i') : '<span class="text-muted">Jamais</span>'; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?php echo BASE_URL; ?>utilisateurs/modifier.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-primary"
                                   data-bs-toggle="tooltip"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php if ($user['id'] != $_SESSION['user_id'] && $user['nom_utilisateur'] != 'admin'): ?>
                                    <?php if ($user['actif']): ?>
                                        <button onclick="toggleStatus(<?php echo $user['id']; ?>, false)" 
                                                class="btn btn-outline-warning"
                                                data-bs-toggle="tooltip"
                                                title="Désactiver">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="toggleStatus(<?php echo $user['id']; ?>, true)" 
                                                class="btn btn-outline-success"
                                                data-bs-toggle="tooltip"
                                                title="Activer">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="<?php echo BASE_URL; ?>utilisateurs/reset_password.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-info"
                                   data-bs-toggle="tooltip"
                                   title="Réinitialiser le mot de passe">
                                    <i class="fas fa-key"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleStatus(userId, activate) {
    const action = activate ? 'activer' : 'désactiver';
    const message = `Êtes-vous sûr de vouloir ${action} cet utilisateur ?`;
    
    if (confirm(message)) {
        // Créer un formulaire pour envoyer la requête POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo BASE_URL; ?>utilisateurs/toggle_status.php';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = activate ? '1' : '0';
        
        form.appendChild(userIdInput);
        form.appendChild(statusInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>