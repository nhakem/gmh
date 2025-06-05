<?php
// dashboard/agent.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Tableau de bord - ' . SITE_NAME;
$currentPage = 'dashboard';

// Récupérer quelques statistiques de base
$db = getDB();

// Nombre de personnes enregistrées aujourd'hui par l'agent
$sql = "SELECT COUNT(*) as total 
        FROM personnes 
        WHERE DATE(date_inscription) = CURDATE()";
$stmt = $db->query($sql);
$personnes_jour = $stmt->fetchColumn();

// Dernières personnes ajoutées
$sql = "SELECT id, nom, prenom, role, date_inscription 
        FROM personnes 
        ORDER BY date_inscription DESC 
        LIMIT 10";
$stmt = $db->query($sql);
$dernieres_personnes = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Tableau de bord</h1>
        <span class="text-muted">
            <i class="far fa-calendar-alt me-2"></i>
            <?php echo date('d/m/Y'); ?>
        </span>
    </div>

    <!-- Message de bienvenue -->
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        Bienvenue <strong><?php echo htmlspecialchars($_SESSION['user_fullname']); ?></strong>. 
        Vous êtes connecté en tant qu'<strong>Agent de saisie</strong>.
    </div>

    <!-- Actions rapides -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <a href="<?php echo BASE_URL; ?>personnes/ajouter.php" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-primary bg-gradient text-white mx-auto">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h5 class="mb-0">Nouvelle personne</h5>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-md-6">
            <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-info bg-gradient text-white mx-auto">
                        <i class="fas fa-bed"></i>
                    </div>
                    <h5 class="mb-0">Attribuer chambre</h5>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-md-6">
            <a href="<?php echo BASE_URL; ?>repas/enregistrer.php" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-success bg-gradient text-white mx-auto">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h5 class="mb-0">Enregistrer repas</h5>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-md-6">
            <a href="<?php echo BASE_URL; ?>medicaments/prescrire.php" class="text-decoration-none">
                <div class="stat-card text-center">
                    <div class="stat-icon bg-warning bg-gradient text-white mx-auto">
                        <i class="fas fa-pills"></i>
                    </div>
                    <h5 class="mb-0">Prescrire médicament</h5>
                </div>
            </a>
        </div>
    </div>

    <!-- Statistique du jour -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="stat-card">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-chart-line me-2"></i>
                    Activité du jour
                </h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h2 class="text-primary mb-0"><?php echo $personnes_jour; ?></h2>
                            <p class="text-muted mb-0">Personnes enregistrées aujourd'hui</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernières personnes ajoutées -->
    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-history me-2 text-primary"></i>
                    Dernières personnes enregistrées
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom complet</th>
                                <th>Rôle</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernieres_personnes as $personne): ?>
                            <tr>
                                <td><?php echo $personne['id']; ?></td>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($personne['prenom'] . ' ' . $personne['nom']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $personne['role'] === 'client' ? 'primary' : 'success'; ?>">
                                        <?php echo ucfirst($personne['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($personne['date_inscription'], 'd/m/Y H:i'); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>personnes/modifier.php?id=<?php echo $personne['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary"
                                       data-bs-toggle="tooltip"
                                       title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php?personne_id=<?php echo $personne['id']; ?>" 
                                       class="btn btn-sm btn-outline-info"
                                       data-bs-toggle="tooltip"
                                       title="Attribuer chambre">
                                        <i class="fas fa-bed"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>