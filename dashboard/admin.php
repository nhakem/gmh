<?php
// dashboard/admin.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Tableau de bord - ' . SITE_NAME;
$currentPage = 'dashboard';

// Récupérer les statistiques du jour
$db = getDB();

// Statistiques générales
$stats = [];

// Personnes hébergées aujourd'hui
$sql = "SELECT COUNT(DISTINCT personne_id) as total 
        FROM nuitees 
        WHERE actif = TRUE 
        AND date_debut <= CURDATE() 
        AND (date_fin IS NULL OR date_fin >= CURDATE())";
$stmt = $db->query($sql);
$stats['personnes_hebergees'] = $stmt->fetchColumn();

// Nouvelles personnes aujourd'hui
$sql = "SELECT COUNT(*) as total 
        FROM personnes 
        WHERE DATE(date_inscription) = CURDATE()";
$stmt = $db->query($sql);
$stats['nouvelles_personnes'] = $stmt->fetchColumn();

// Repas servis aujourd'hui
$sql = "SELECT COUNT(*) as total 
        FROM repas 
        WHERE date_repas = CURDATE()";
$stmt = $db->query($sql);
$stats['repas_jour'] = $stmt->fetchColumn();

// Chambres occupées
$sql = "SELECT COUNT(DISTINCT chambre_id) as total 
        FROM nuitees 
        WHERE actif = TRUE 
        AND date_debut <= CURDATE() 
        AND (date_fin IS NULL OR date_fin >= CURDATE())";
$stmt = $db->query($sql);
$stats['chambres_occupees'] = $stmt->fetchColumn();

// Statistiques par type d'hébergement
$sql = "SELECT th.nom, COUNT(DISTINCT n.personne_id) as nombre_personnes
        FROM nuitees n
        JOIN chambres c ON n.chambre_id = c.id
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        WHERE n.actif = TRUE 
        AND n.date_debut <= CURDATE() 
        AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())
        GROUP BY th.id, th.nom";
$stmt = $db->query($sql);
$stats_hebergement = $stmt->fetchAll();

// Dernières activités
$sql = "SELECT l.*, u.nom_complet 
        FROM logs_saisie l
        JOIN utilisateurs u ON l.utilisateur_id = u.id
        ORDER BY l.date_action DESC
        LIMIT 10";
$stmt = $db->query($sql);
$dernières_activites = $stmt->fetchAll();

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

    <!-- Cartes de statistiques -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-gradient text-white">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="mb-1"><?php echo $stats['personnes_hebergees']; ?></h3>
                <p class="text-muted mb-0">Personnes hébergées</p>
                <small class="text-success">
                    <i class="fas fa-arrow-up me-1"></i>
                    <?php echo $stats['nouvelles_personnes']; ?> nouvelles aujourd'hui
                </small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-gradient text-white">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 class="mb-1"><?php echo $stats['repas_jour']; ?></h3>
                <p class="text-muted mb-0">Repas servis aujourd'hui</p>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-gradient text-white">
                    <i class="fas fa-bed"></i>
                </div>
                <h3 class="mb-1"><?php echo $stats['chambres_occupees']; ?></h3>
                <p class="text-muted mb-0">Chambres occupées</p>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-gradient text-white">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="mb-1">85%</h3>
                <p class="text-muted mb-0">Taux d'occupation</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Répartition par type d'hébergement -->
        <div class="col-lg-6">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-home me-2 text-primary"></i>
                    Répartition par hébergement
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type d'hébergement</th>
                                <th class="text-end">Personnes</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = array_sum(array_column($stats_hebergement, 'nombre_personnes'));
                            foreach ($stats_hebergement as $stat): 
                                $percentage = $total > 0 ? round(($stat['nombre_personnes'] / $total) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['nom']); ?></td>
                                <td class="text-end"><?php echo $stat['nombre_personnes']; ?></td>
                                <td class="text-end">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-primary" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dernières activités -->
        <div class="col-lg-6">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-history me-2 text-primary"></i>
                    Dernières activités
                </h5>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-sm">
                        <tbody>
                            <?php foreach ($dernières_activites as $activite): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-circle text-success me-2" style="font-size: 0.5rem;"></i>
                                        <div>
                                            <strong><?php echo htmlspecialchars($activite['nom_complet']); ?></strong>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($activite['action']); ?>
                                                <?php if ($activite['table_concernee']): ?>
                                                    - <?php echo htmlspecialchars($activite['table_concernee']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end text-muted">
                                    <small><?php echo formatDate($activite['date_action'], 'd/m H:i'); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="row g-4 mt-4">
        <div class="col-12">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-bolt me-2 text-primary"></i>
                    Actions rapides
                </h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="<?php echo BASE_URL; ?>personnes/ajouter.php" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus me-2"></i>
                            Nouvelle personne
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php" class="btn btn-info w-100">
                            <i class="fas fa-bed me-2"></i>
                            Attribuer chambre
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo BASE_URL; ?>repas/enregistrer.php" class="btn btn-success w-100">
                            <i class="fas fa-utensils me-2"></i>
                            Enregistrer repas
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo BASE_URL; ?>statistiques/" class="btn btn-warning w-100">
                            <i class="fas fa-chart-bar me-2"></i>
                            Voir statistiques
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>