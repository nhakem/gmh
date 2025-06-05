<?php
// personnes/index.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Gestion des personnes - ' . SITE_NAME;
$currentPage = 'personnes';

// Récupérer la liste des personnes
$db = getDB();
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM nuitees n WHERE n.personne_id = p.id AND n.actif = TRUE) as nuitees_actives
        FROM personnes p 
        WHERE p.actif = TRUE 
        ORDER BY p.date_inscription DESC";
$stmt = $db->query($sql);
$personnes = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-users me-2 text-primary"></i>
            Gestion des personnes
        </h1>
        <a href="<?php echo BASE_URL; ?>personnes/ajouter.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            Nouvelle personne
        </a>
    </div>

    <!-- Filtres -->
    <div class="table-container mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       name="search" 
                       placeholder="Nom, prénom..."
                       value="<?php echo $_GET['search'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sexe</label>
                <select class="form-select" name="sexe">
                    <option value="">Tous</option>
                    <option value="M">Masculin</option>
                    <option value="F">Féminin</option>
                    <option value="Autre">Autre</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rôle</label>
                <select class="form-select" name="role">
                    <option value="">Tous</option>
                    <option value="client">Client</option>
                    <option value="benevole">Bénévole</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Origine</label>
                <input type="text" 
                       class="form-control" 
                       name="origine" 
                       placeholder="Pays..."
                       value="<?php echo $_GET['origine'] ?? ''; ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary me-2">
                    <i class="fas fa-filter me-2"></i>
                    Filtrer
                </button>
                <a href="<?php echo BASE_URL; ?>personnes/" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Liste des personnes -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom complet</th>
                        <th>Sexe</th>
                        <th>Âge</th>
                        <th>Ville</th>
                        <th>Origine</th>
                        <th>Rôle</th>
                        <th>Hébergé</th>
                        <th>Inscrit le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personnes as $personne): ?>
                    <tr>
                        <td><?php echo $personne['id']; ?></td>
                        <td>
                            <strong>
                                <?php echo htmlspecialchars($personne['prenom'] . ' ' . $personne['nom']); ?>
                            </strong>
                            <?php if ($personne['telephone']): ?>
                                <br><small class="text-muted">
                                    <i class="fas fa-phone fa-xs"></i> 
                                    <?php echo htmlspecialchars($personne['telephone']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $sexeIcons = ['M' => 'mars', 'F' => 'venus', 'Autre' => 'genderless'];
                            $sexeColors = ['M' => 'primary', 'F' => 'danger', 'Autre' => 'secondary'];
                            $icon = $sexeIcons[$personne['sexe']] ?? 'user';
                            $color = $sexeColors[$personne['sexe']] ?? 'secondary';
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?>"></i>
                        </td>
                        <td><?php echo $personne['age'] ?: '-'; ?></td>
                        <td><?php echo htmlspecialchars($personne['ville'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($personne['origine'] ?: '-'); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $personne['role'] === 'client' ? 'primary' : 'success'; ?>">
                                <?php echo ucfirst($personne['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($personne['nuitees_actives'] > 0): ?>
                                <span class="badge bg-info">
                                    <i class="fas fa-bed"></i> Oui
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Non</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($personne['date_inscription'], 'd/m/Y'); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?php echo BASE_URL; ?>personnes/modifier.php?id=<?php echo $personne['id']; ?>" 
                                   class="btn btn-outline-primary"
                                   data-bs-toggle="tooltip"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($personne['nuitees_actives'] == 0): ?>
                                    <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php?personne_id=<?php echo $personne['id']; ?>" 
                                       class="btn btn-outline-info"
                                       data-bs-toggle="tooltip"
                                       title="Attribuer chambre">
                                        <i class="fas fa-bed"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo BASE_URL; ?>repas/enregistrer.php?personne_id=<?php echo $personne['id']; ?>" 
                                   class="btn btn-outline-success"
                                   data-bs-toggle="tooltip"
                                   title="Enregistrer repas">
                                    <i class="fas fa-utensils"></i>
                                </a>
                                <?php if (hasPermission('administrateur')): ?>
                                    <button onclick="confirmDelete('<?php echo BASE_URL; ?>personnes/supprimer.php?id=<?php echo $personne['id']; ?>', 'Voulez-vous vraiment supprimer cette personne ?')" 
                                            class="btn btn-outline-danger"
                                            data-bs-toggle="tooltip"
                                            title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>