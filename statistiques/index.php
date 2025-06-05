<?php
// statistiques/index.php - Tableau de bord des statistiques
require_once '../config.php';
require_once ROOT_PATH . '/includes/classes/Auth.php';

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

if (!hasPermission('administrateur')) {
    redirect('dashboard/', MSG_PERMISSION_DENIED, 'error');
}

$currentPage = 'statistiques';
$pageTitle = 'Statistiques - ' . SITE_NAME;

try {
    $db = getDB();
    
    // Statistiques générales
    $statsGenerales = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM personnes WHERE actif = 1) as total_personnes,
            (SELECT COUNT(*) FROM personnes WHERE actif = 1 AND role = 'client') as total_clients,
            (SELECT COUNT(*) FROM personnes WHERE actif = 1 AND role = 'benevole') as total_benevoles,
            (SELECT COUNT(*) FROM nuitees WHERE actif = 1 AND (date_fin IS NULL OR date_fin >= CURDATE())) as hebergements_actifs,
            (SELECT COUNT(*) FROM chambres WHERE disponible = 1) as chambres_disponibles,
            (SELECT COUNT(*) FROM repas WHERE date_repas = CURDATE()) as repas_aujourdhui
    ")->fetch();
    
    // Statistiques du mois courant
    $moisCourant = $db->query("
        SELECT 
            COUNT(DISTINCT n.personne_id) as personnes_hebergees,
            SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), LAST_DAY(CURDATE())), 
                GREATEST(n.date_debut, DATE_FORMAT(CURDATE(), '%Y-%m-01'))) + 1) as total_nuitees,
            COUNT(DISTINCT r.personne_id) as personnes_repas,
            COUNT(r.id) as total_repas
        FROM nuitees n
        LEFT JOIN repas r ON r.personne_id = n.personne_id 
            AND r.date_repas BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
        WHERE n.actif = 1 
            AND n.date_debut <= LAST_DAY(CURDATE())
            AND (n.date_fin IS NULL OR n.date_fin >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))
    ")->fetch();
    
    // Données pour graphiques - Évolution mensuelle (12 derniers mois)
    $evolutionMensuelle = $db->query("
        SELECT 
            DATE_FORMAT(date_debut, '%Y-%m') as mois,
            DATE_FORMAT(date_debut, '%M %Y') as mois_libelle,
            COUNT(DISTINCT personne_id) as personnes,
            SUM(DATEDIFF(IFNULL(date_fin, LAST_DAY(date_debut)), date_debut) + 1) as nuitees
        FROM nuitees 
        WHERE actif = 1 
            AND date_debut >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(date_debut, '%Y-%m')
        ORDER BY mois
    ")->fetchAll();
    
    // Répartition par sexe
    $repartitionSexe = $db->query("
        SELECT 
            p.sexe,
            COUNT(DISTINCT n.personne_id) as nombre
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        WHERE n.actif = 1 
            AND n.date_debut >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        GROUP BY p.sexe
    ")->fetchAll();
    
    // Répartition par tranche d'âge
    $repartitionAge = $db->query("
        SELECT 
            CASE 
                WHEN p.age < 18 THEN 'Moins de 18 ans'
                WHEN p.age BETWEEN 18 AND 25 THEN '18-25 ans'
                WHEN p.age BETWEEN 26 AND 40 THEN '26-40 ans'
                WHEN p.age BETWEEN 41 AND 60 THEN '41-60 ans'
                WHEN p.age > 60 THEN 'Plus de 60 ans'
                ELSE 'Non renseigné'
            END as tranche_age,
            COUNT(DISTINCT n.personne_id) as nombre
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        WHERE n.actif = 1 
            AND n.date_debut >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        GROUP BY tranche_age
        ORDER BY MIN(p.age)
    ")->fetchAll();
    
    // Utilisation des types d'hébergement
    $typeHebergement = $db->query("
        SELECT 
            th.nom,
            COUNT(DISTINCT n.personne_id) as personnes,
            SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), CURDATE()), 
                GREATEST(n.date_debut, DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) + 1) as nuitees
        FROM nuitees n
        JOIN chambres c ON n.chambre_id = c.id
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        WHERE n.actif = 1 
            AND n.date_debut <= CURDATE()
            AND (n.date_fin IS NULL OR n.date_fin >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        GROUP BY th.id, th.nom
    ")->fetchAll();
    
    // Top 5 des origines les plus fréquentes
    $topOrigines = $db->query("
        SELECT 
            p.origine,
            COUNT(DISTINCT n.personne_id) as nombre
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        WHERE n.actif = 1 
            AND p.origine IS NOT NULL 
            AND p.origine != ''
            AND n.date_debut >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY p.origine
        ORDER BY nombre DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
    error_log($error);
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-bar me-2"></i>
        Tableau de bord des statistiques
    </h1>
    <div class="btn-group">
        <a href="rapports.php" class="btn btn-outline-primary">
            <i class="fas fa-file-alt me-1"></i>
            Rapports détaillés
        </a>
        <a href="export.php" class="btn btn-outline-success">
            <i class="fas fa-download me-1"></i>
            Exporter
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

<!-- Cartes de statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary text-white">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="mb-1"><?php echo number_format($statsGenerales['total_personnes']); ?></h3>
            <p class="text-muted mb-2">Personnes enregistrées</p>
            <small class="text-success">
                <i class="fas fa-user me-1"></i>
                <?php echo $statsGenerales['total_clients']; ?> clients, 
                <?php echo $statsGenerales['total_benevoles']; ?> bénévoles
            </small>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-info text-white">
                <i class="fas fa-bed"></i>
            </div>
            <h3 class="mb-1"><?php echo number_format($statsGenerales['hebergements_actifs']); ?></h3>
            <p class="text-muted mb-2">Hébergements actifs</p>
            <small class="text-info">
                <i class="fas fa-door-open me-1"></i>
                <?php echo $statsGenerales['chambres_disponibles']; ?> chambres disponibles
            </small>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning text-white">
                <i class="fas fa-calendar-day"></i>
            </div>
            <h3 class="mb-1"><?php echo number_format($moisCourant['total_nuitees'] ?? 0); ?></h3>
            <p class="text-muted mb-2">Nuitées ce mois</p>
            <small class="text-warning">
                <i class="fas fa-users me-1"></i>
                <?php echo $moisCourant['personnes_hebergees'] ?? 0; ?> personnes hébergées
            </small>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-success text-white">
                <i class="fas fa-utensils"></i>
            </div>
            <h3 class="mb-1"><?php echo number_format($statsGenerales['repas_aujourdhui']); ?></h3>
            <p class="text-muted mb-2">Repas aujourd'hui</p>
            <small class="text-success">
                <i class="fas fa-calendar-month me-1"></i>
                <?php echo number_format($moisCourant['total_repas'] ?? 0); ?> ce mois
            </small>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row">
    <!-- Évolution mensuelle -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des hébergements (12 derniers mois)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Répartition par sexe -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par sexe
                </h5>
            </div>
            <div class="card-body">
                <canvas id="sexeChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Tranches d'âge -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Répartition par âge
                </h5>
            </div>
            <div class="card-body">
                <canvas id="ageChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Types d'hébergement -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-home me-2"></i>
                    Types d'hébergement
                </h5>
            </div>
            <div class="card-body">
                <canvas id="hebergementChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top origines -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Principales origines géographiques (3 derniers mois)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Origine</th>
                                <th>Nombre de personnes</th>
                                <th>Pourcentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($topOrigines)): ?>
                                <?php 
                                $totalOrigines = array_sum(array_column($topOrigines, 'nombre'));
                                foreach ($topOrigines as $origine): 
                                    $pourcentage = $totalOrigines > 0 ? ($origine['nombre'] / $totalOrigines) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($origine['origine']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $origine['nombre']; ?></span>
                                    </td>
                                    <td>
                                        <div class="progress" style="width: 200px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $pourcentage; ?>%">
                                                <?php echo number_format($pourcentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Aucune donnée disponible</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
$pageScripts = "
<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
// Configuration générale des graphiques
Chart.defaults.font.family = 'Segoe UI, system-ui, sans-serif';
Chart.defaults.color = '#6c757d';

// Graphique évolution mensuelle
const evolutionData = " . json_encode($evolutionMensuelle) . ";
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: evolutionData.map(item => item.mois_libelle),
        datasets: [{
            label: 'Personnes hébergées',
            data: evolutionData.map(item => item.personnes),
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Nuitées',
            data: evolutionData.map(item => item.nuitees),
            borderColor: '#764ba2',
            backgroundColor: 'rgba(118, 75, 162, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: { display: true, text: 'Personnes' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'Nuitées' },
                grid: { drawOnChartArea: false }
            }
        },
        plugins: {
            legend: { display: true }
        }
    }
});

// Graphique répartition par sexe
const sexeData = " . json_encode($repartitionSexe) . ";
const sexeCtx = document.getElementById('sexeChart').getContext('2d');
new Chart(sexeCtx, {
    type: 'doughnut',
    data: {
        labels: sexeData.map(item => item.sexe === 'M' ? 'Hommes' : (item.sexe === 'F' ? 'Femmes' : 'Autre')),
        datasets: [{
            data: sexeData.map(item => item.nombre),
            backgroundColor: ['#667eea', '#764ba2', '#f093fb']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Graphique tranches d'âge
const ageData = " . json_encode($repartitionAge) . ";
const ageCtx = document.getElementById('ageChart').getContext('2d');
new Chart(ageCtx, {
    type: 'bar',
    data: {
        labels: ageData.map(item => item.tranche_age),
        datasets: [{
            label: 'Nombre de personnes',
            data: ageData.map(item => item.nombre),
            backgroundColor: '#667eea'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Graphique types d'hébergement
const hebergementData = " . json_encode($typeHebergement) . ";
const hebergementCtx = document.getElementById('hebergementChart').getContext('2d');
new Chart(hebergementCtx, {
    type: 'bar',
    data: {
        labels: hebergementData.map(item => item.nom),
        datasets: [{
            label: 'Personnes',
            data: hebergementData.map(item => item.personnes),
            backgroundColor: '#667eea'
        }, {
            label: 'Nuitées',
            data: hebergementData.map(item => item.nuitees),
            backgroundColor: '#764ba2'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true }
        }
    }
});
</script>
";

include ROOT_PATH . '/includes/footer.php';
?>