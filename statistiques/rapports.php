<?php
// statistiques/rapports.php - Rapports détaillés
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
$pageTitle = 'Rapports détaillés - ' . SITE_NAME;

// Paramètres de filtrage
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois courant
$dateFin = $_GET['date_fin'] ?? date('Y-m-t'); // Dernier jour du mois courant
$typeRapport = $_GET['type'] ?? 'general';

try {
    $db = getDB();
    
    // Validation des dates
    $dateDebutObj = new DateTime($dateDebut);
    $dateFinObj = new DateTime($dateFin);
    
    if ($dateFinObj < $dateDebutObj) {
        throw new Exception("La date de fin doit être postérieure à la date de début");
    }
    
    // Appel de la procédure stockée pour les statistiques de la période
    $stmt = $db->prepare("CALL sp_statistiques_periode(?, ?)");
    $stmt->execute([$dateDebut, $dateFin]);
    
    // Récupération des résultats
    $statsGenerales = $stmt->fetch();
    $stmt->nextRowset();
    $statsSexe = $stmt->fetchAll();
    $stmt->nextRowset();
    $statsHebergement = $stmt->fetchAll();
    $stmt->nextRowset();
    $statsRepas = $stmt->fetchAll();
    
    // Statistiques détaillées personnalisées
    // Évolution journalière de la période
    $evolutionJournaliere = $db->prepare("
        SELECT 
            DATE(n.date_debut) as date_entree,
            COUNT(DISTINCT n.personne_id) as nouvelles_entrees,
            (SELECT COUNT(DISTINCT n2.personne_id) 
             FROM nuitees n2 
             WHERE n2.actif = 1 
               AND DATE(n2.date_debut) <= DATE(n.date_debut)
               AND (n2.date_fin IS NULL OR DATE(n2.date_fin) >= DATE(n.date_debut))
            ) as personnes_presentes
        FROM nuitees n
        WHERE n.actif = 1 
          AND DATE(n.date_debut) BETWEEN ? AND ?
        GROUP BY DATE(n.date_debut)
        ORDER BY date_entree
    ");
    $evolutionJournaliere->execute([$dateDebut, $dateFin]);
    $evolutionJour = $evolutionJournaliere->fetchAll();
    
    // Durée moyenne de séjour
    $dureeSejourStmt = $db->prepare("
        SELECT 
            AVG(DATEDIFF(IFNULL(date_fin, CURDATE()), date_debut) + 1) as duree_moyenne,
            MIN(DATEDIFF(IFNULL(date_fin, CURDATE()), date_debut) + 1) as duree_min,
            MAX(DATEDIFF(IFNULL(date_fin, CURDATE()), date_debut) + 1) as duree_max,
            COUNT(*) as nombre_sejours
        FROM nuitees 
        WHERE actif = 1 
          AND date_debut BETWEEN ? AND ?
    ");
    $dureeSejourStmt->execute([$dateDebut, $dateFin]);
    $dureeSejour = $dureeSejourStmt->fetch();
    
    // Taux d'occupation par chambre
    $occupationChambres = $db->prepare("
        SELECT 
            c.numero,
            th.nom as type_hebergement,
            COUNT(n.id) as nombre_sejours,
            SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, ?), ?), 
                GREATEST(n.date_debut, ?)) + 1) as jours_occupation,
            ROUND(
                SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, ?), ?), 
                    GREATEST(n.date_debut, ?)) + 1) / 
                (DATEDIFF(?, ?) + 1) * 100, 2
            ) as taux_occupation
        FROM chambres c
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        LEFT JOIN nuitees n ON c.id = n.chambre_id 
            AND n.actif = 1
            AND n.date_debut <= ?
            AND (n.date_fin IS NULL OR n.date_fin >= ?)
        GROUP BY c.id, c.numero, th.nom
        ORDER BY taux_occupation DESC
    ");
    $occupationChambres->execute([
        $dateFin, $dateFin, $dateDebut, 
        $dateFin, $dateFin, $dateDebut,
        $dateFin, $dateDebut,
        $dateFin, $dateDebut
    ]);
    $occupation = $occupationChambres->fetchAll();
    
    // Analyse des repas par type et mode de paiement
    $analyseModesPaiement = $db->prepare("
        SELECT 
            mode_paiement,
            COUNT(*) as nombre_transactions,
            SUM(CASE WHEN montant IS NOT NULL THEN montant ELSE 0 END) as total_montant
        FROM (
            SELECT mode_paiement, tarif_journalier as montant FROM nuitees 
            WHERE actif = 1 AND date_debut BETWEEN ? AND ?
            UNION ALL
            SELECT mode_paiement, montant FROM repas 
            WHERE date_repas BETWEEN ? AND ?
        ) as transactions
        GROUP BY mode_paiement
    ");
    $analyseModesPaiement->execute([$dateDebut, $dateFin, $dateDebut, $dateFin]);
    $modesPaiement = $analyseModesPaiement->fetchAll();

} catch (Exception $e) {
    $error = "Erreur lors de la génération du rapport : " . $e->getMessage();
    error_log($error);
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-file-alt me-2"></i>
        Rapports détaillés
    </h1>
    <div class="btn-group">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour au tableau de bord
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print me-1"></i>
            Imprimer
        </button>
    </div>
</div>

<!-- Formulaire de filtrage -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres du rapport
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="date_debut" class="form-label">Date de début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo htmlspecialchars($dateDebut); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="date_fin" class="form-label">Date de fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?php echo htmlspecialchars($dateFin); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="type" class="form-label">Type de rapport</label>
                <select class="form-select" id="type" name="type">
                    <option value="general" <?php echo $typeRapport === 'general' ? 'selected' : ''; ?>>
                        Rapport général
                    </option>
                    <option value="hebergement" <?php echo $typeRapport === 'hebergement' ? 'selected' : ''; ?>>
                        Focus hébergement
                    </option>
                    <option value="repas" <?php echo $typeRapport === 'repas' ? 'selected' : ''; ?>>
                        Focus repas
                    </option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Générer le rapport
                </button>
                <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-1"></i>
                    Exporter Excel
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

<!-- En-tête du rapport -->
<div class="card mb-4">
    <div class="card-body text-center">
        <h2 class="mb-3">Rapport d'activité - <?php echo SITE_NAME; ?></h2>
        <p class="lead">
            Période du <?php echo formatDate($dateDebut); ?> au <?php echo formatDate($dateFin); ?>
        </p>
        <small class="text-muted">
            Rapport généré le <?php echo formatDate(date('Y-m-d'), 'd/m/Y à H:i'); ?>
            par <?php echo htmlspecialchars($_SESSION['user_fullname']); ?>
        </small>
    </div>
</div>

<!-- Synthèse générale -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-primary text-white mx-auto">
                <i class="fas fa-users"></i>
            </div>
            <h3><?php echo number_format($statsGenerales['nombre_personnes_total'] ?? 0); ?></h3>
            <p class="text-muted">Personnes hébergées</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-info text-white mx-auto">
                <i class="fas fa-calendar-day"></i>
            </div>
            <h3><?php echo number_format($statsGenerales['total_nuitees'] ?? 0); ?></h3>
            <p class="text-muted">Nuitées totales</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-warning text-white mx-auto">
                <i class="fas fa-bed"></i>
            </div>
            <h3><?php echo number_format($statsGenerales['chambres_utilisees'] ?? 0); ?></h3>
            <p class="text-muted">Chambres utilisées</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-success text-white mx-auto">
                <i class="fas fa-clock"></i>
            </div>
            <h3><?php echo number_format($dureeSejour['duree_moyenne'] ?? 0, 1); ?></h3>
            <p class="text-muted">Durée moyenne (jours)</p>
        </div>
    </div>
</div>

<!-- Répartition par sexe -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Répartition par sexe</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sexe</th>
                                <th>Personnes</th>
                                <th>Nuitées</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalPersonnes = array_sum(array_column($statsSexe, 'nombre_personnes'));
                            foreach ($statsSexe as $stat): 
                                $pourcentage = $totalPersonnes > 0 ? ($stat['nombre_personnes'] / $totalPersonnes) * 100 : 0;
                                $sexeLibelle = $stat['sexe'] === 'M' ? 'Hommes' : ($stat['sexe'] === 'F' ? 'Femmes' : 'Autre');
                            ?>
                            <tr>
                                <td><?php echo $sexeLibelle; ?></td>
                                <td><?php echo number_format($stat['nombre_personnes']); ?></td>
                                <td><?php echo number_format($stat['total_nuitees']); ?></td>
                                <td><?php echo number_format($pourcentage, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Durées de séjour</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-success"><?php echo number_format($dureeSejour['duree_min'] ?? 0); ?></h4>
                        <small class="text-muted">Minimum</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-primary"><?php echo number_format($dureeSejour['duree_moyenne'] ?? 0, 1); ?></h4>
                        <small class="text-muted">Moyenne</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-warning"><?php echo number_format($dureeSejour['duree_max'] ?? 0); ?></h4>
                        <small class="text-muted">Maximum</small>
                    </div>
                </div>
                <hr>
                <p class="text-center mb-0">
                    <strong><?php echo number_format($dureeSejour['nombre_sejours'] ?? 0); ?></strong> 
                    séjours analysés
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Types d'hébergement -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Utilisation par type d'hébergement</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Type d'hébergement</th>
                        <th>Personnes</th>
                        <th>Nuitées</th>
                        <th>Nuitées par personne</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statsHebergement as $hebergement): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($hebergement['type_hebergement']); ?></td>
                        <td><?php echo number_format($hebergement['nombre_personnes']); ?></td>
                        <td><?php echo number_format($hebergement['total_nuitees']); ?></td>
                        <td>
                            <?php 
                            $ratio = $hebergement['nombre_personnes'] > 0 ? 
                                     $hebergement['total_nuitees'] / $hebergement['nombre_personnes'] : 0;
                            echo number_format($ratio, 1);
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Taux d'occupation des chambres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Taux d'occupation des chambres</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Chambre</th>
                        <th>Type</th>
                        <th>Séjours</th>
                        <th>Jours occupés</th>
                        <th>Taux d'occupation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($occupation as $chambre): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($chambre['numero']); ?></td>
                        <td><?php echo htmlspecialchars($chambre['type_hebergement']); ?></td>
                        <td><?php echo number_format($chambre['nombre_sejours']); ?></td>
                        <td><?php echo number_format($chambre['jours_occupation']); ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                    <div class="progress-bar" style="width: <?php echo min($chambre['taux_occupation'], 100); ?>%">
                                        <?php echo number_format($chambre['taux_occupation'], 1); ?>%
                                    </div>
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

<!-- Analyse financière -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Analyse des modes de paiement</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($modesPaiement as $mode): ?>
            <div class="col-md-4 mb-3">
                <div class="stat-card text-center">
                    <h4 class="mb-2">
                        <?php 
                        switch($mode['mode_paiement']) {
                            case 'gratuit': echo 'Gratuit'; break;
                            case 'comptant': echo 'Comptant'; break;
                            case 'credit': echo 'Crédit'; break;
                            default: echo ucfirst($mode['mode_paiement']);
                        }
                        ?>
                    </h4>
                    <p class="mb-1">
                        <strong><?php echo number_format($mode['nombre_transactions']); ?></strong> 
                        transactions
                    </p>
                    <p class="text-success mb-0">
                        <strong><?php echo number_format($mode['total_montant'], 2); ?> $</strong>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($evolutionJour)): ?>
<!-- Graphique évolution journalière -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Évolution journalière des entrées</h5>
    </div>
    <div class="card-body">
        <canvas id="evolutionJournaliereChart" height="80"></canvas>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
if (!empty($evolutionJour)) {
    $pageScripts = "
    <script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
    <script>
    // Graphique évolution journalière
    const evolutionJournaliereData = " . json_encode($evolutionJour) . ";
    const evolutionJournaliereCtx = document.getElementById('evolutionJournaliereChart').getContext('2d');
    new Chart(evolutionJournaliereCtx, {
        type: 'line',
        data: {
            labels: evolutionJournaliereData.map(item => {
                const date = new Date(item.date_entree);
                return date.toLocaleDateString('fr-FR');
            }),
            datasets: [{
                label: 'Nouvelles entrées',
                data: evolutionJournaliereData.map(item => item.nouvelles_entrees),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Personnes présentes',
                data: evolutionJournaliereData.map(item => item.personnes_presentes),
                borderColor: '#764ba2',
                backgroundColor: 'rgba(118, 75, 162, 0.1)',
                tension: 0.4,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Nombre de personnes' }
                }
            },
            plugins: {
                legend: { display: true }
            }
        }
    });
    </script>
    ";
}

include ROOT_PATH . '/includes/footer.php';
?>