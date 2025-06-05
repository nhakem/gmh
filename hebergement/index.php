<?php
// hebergement/index.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Gestion de l\'hébergement - ' . SITE_NAME;
$currentPage = 'hebergement';

$db = getDB();

// Récupérer les statistiques générales
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM chambres) as total_chambres,
    (SELECT SUM(nombre_lits) FROM chambres) as total_lits,
    (SELECT COUNT(DISTINCT chambre_id) FROM nuitees WHERE actif = TRUE AND date_debut <= CURDATE() AND (date_fin IS NULL OR date_fin >= CURDATE())) as chambres_occupees,
    (SELECT COUNT(DISTINCT personne_id) FROM nuitees WHERE actif = TRUE AND date_debut <= CURDATE() AND (date_fin IS NULL OR date_fin >= CURDATE())) as personnes_hebergees,
    (SELECT SUM(tarif_journalier * DATEDIFF(CURDATE(), date_debut) + 1) FROM nuitees WHERE actif = TRUE AND mode_paiement != 'gratuit' AND date_debut <= CURDATE()) as revenus_actifs";

$stats = $db->query($statsSql)->fetch();

// Calculer le taux d'occupation
$tauxOccupation = $stats['total_chambres'] > 0 ? round(($stats['chambres_occupees'] / $stats['total_chambres']) * 100, 1) : 0;

// Récupérer la liste des chambres avec leur statut actuel
$chambresSql = "SELECT 
    c.*,
    th.nom as type_hebergement_nom,
    n.id as nuitee_id,
    n.personne_id,
    n.date_debut,
    n.date_fin,
    p.nom as personne_nom,
    p.prenom as personne_prenom
FROM chambres c
JOIN types_hebergement th ON c.type_hebergement_id = th.id
LEFT JOIN nuitees n ON c.id = n.chambre_id AND n.actif = TRUE AND n.date_debut <= CURDATE() AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())
LEFT JOIN personnes p ON n.personne_id = p.id
ORDER BY th.id, c.numero";

$chambres = $db->query($chambresSql)->fetchAll();

// Récupérer les nuitées actives
$nuiteesActives = $db->query("
    SELECT n.*, p.nom, p.prenom, c.numero as chambre_numero, th.nom as type_hebergement
    FROM nuitees n
    JOIN personnes p ON n.personne_id = p.id
    JOIN chambres c ON n.chambre_id = c.id
    JOIN types_hebergement th ON c.type_hebergement_id = th.id
    WHERE n.actif = TRUE AND n.date_debut <= CURDATE() AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())
    ORDER BY n.date_debut DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-bed me-2 text-primary"></i>
            Gestion de l'hébergement
        </h1>
        <div>
            <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                Attribuer une chambre
            </a>
            <?php if (hasPermission('administrateur')): ?>
            <a href="<?php echo BASE_URL; ?>hebergement/chambres.php" class="btn btn-outline-primary">
                <i class="fas fa-cog me-2"></i>
                Gérer les chambres
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-primary bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-door-open"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['total_chambres']; ?></h4>
                <p class="text-muted mb-0">Chambres totales</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-info bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-bed"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['total_lits']; ?></h4>
                <p class="text-muted mb-0">Lits totaux</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-success bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['personnes_hebergees']; ?></h4>
                <p class="text-muted mb-0">Personnes hébergées</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-warning bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h4 class="mb-1"><?php echo $tauxOccupation; ?>%</h4>
                <p class="text-muted mb-0">Taux d'occupation</p>
            </div>
        </div>
    </div>
    
    <?php if (hasPermission('administrateur')): ?>
    <!-- Statistiques financières pour admin -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="stat-card">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-dollar-sign me-2"></i>
                    Statistiques financières du mois
                </h5>
                <?php
                // Statistiques financières du mois
                $financesSql = "SELECT 
                    COUNT(CASE WHEN mode_paiement = 'gratuit' THEN 1 END) as hebergements_gratuits,
                    COUNT(CASE WHEN mode_paiement != 'gratuit' THEN 1 END) as hebergements_payants,
                    SUM(CASE WHEN mode_paiement != 'gratuit' THEN tarif_journalier * DATEDIFF(LEAST(IFNULL(date_fin, CURDATE()), CURDATE()), GREATEST(date_debut, DATE_FORMAT(CURDATE(), '%Y-%m-01'))) + 1 ELSE 0 END) as revenus_mois
                    FROM nuitees 
                    WHERE (date_debut <= CURDATE() AND (date_fin IS NULL OR date_fin >= DATE_FORMAT(CURDATE(), '%Y-%m-01')))";
                
                $finances = $db->query($financesSql)->fetch();
                ?>
                <div class="row text-center">
                    <div class="col-md-4">
                        <h4 class="text-success mb-1"><?php echo $finances['hebergements_gratuits'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Hébergements gratuits</p>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-warning mb-1"><?php echo $finances['hebergements_payants'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Hébergements payants</p>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-danger mb-1"><?php echo number_format($finances['revenus_mois'] ?? 0, 2); ?> $</h4>
                        <p class="text-muted mb-0">Revenus du mois</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Vue des chambres par type -->
    <div class="table-container mb-4">
        <h5 class="mb-3">
            <i class="fas fa-door-closed me-2 text-primary"></i>
            État des chambres
        </h5>
        <?php
        $currentType = '';
        foreach ($chambres as $chambre):
            if ($currentType !== $chambre['type_hebergement_nom']):
                if ($currentType !== '') echo '</div></div>';
                $currentType = $chambre['type_hebergement_nom'];
        ?>
            <h6 class="mt-4 mb-3 text-primary"><?php echo htmlspecialchars($currentType); ?></h6>
            <div class="row g-2">
        <?php endif; ?>
            
            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                <div class="card h-100 <?php echo $chambre['personne_id'] ? 'border-danger' : 'border-success'; ?>">
                    <div class="card-body text-center p-2">
                        <h6 class="card-title mb-1">
                            <i class="fas fa-door-<?php echo $chambre['personne_id'] ? 'closed' : 'open'; ?>"></i>
                            <?php echo htmlspecialchars($chambre['numero']); ?>
                        </h6>
                        <small class="text-muted d-block">
                            <?php echo $chambre['nombre_lits']; ?> lit<?php echo $chambre['nombre_lits'] > 1 ? 's' : ''; ?>
                        </small>
                        <?php if ($chambre['personne_id']): ?>
                            <small class="text-danger d-block mt-1">
                                <strong><?php echo htmlspecialchars($chambre['personne_prenom'] . ' ' . substr($chambre['personne_nom'], 0, 1) . '.'); ?></strong>
                            </small>
                            <small class="text-muted">
                                Depuis le <?php echo formatDate($chambre['date_debut'], 'd/m'); ?>
                            </small>
                            <div class="mt-1">
                                <a href="<?php echo BASE_URL; ?>hebergement/liberer.php?id=<?php echo $chambre['nuitee_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Voulez-vous vraiment libérer cette chambre ?');">
                                    <i class="fas fa-sign-out-alt"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-success mt-2">Disponible</span>
                            <div class="mt-1">
                                <a href="<?php echo BASE_URL; ?>hebergement/attribuer.php?chambre_id=<?php echo $chambre['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-user-plus"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($currentType !== '') echo '</div>'; ?>
    </div>

    <!-- Liste des nuitées actives -->
    <div class="table-container">
        <h5 class="mb-3">
            <i class="fas fa-list me-2 text-primary"></i>
            Hébergements actifs
        </h5>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Personne</th>
                        <th>Chambre</th>
                        <th>Type</th>
                        <th>Date début</th>
                        <th>Durée</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nuiteesActives as $nuitee): 
                        $duree = (new DateTime())->diff(new DateTime($nuitee['date_debut']))->days + 1;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($nuitee['prenom'] . ' ' . $nuitee['nom']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo htmlspecialchars($nuitee['chambre_numero']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($nuitee['type_hebergement']); ?></td>
                        <td><?php echo formatDate($nuitee['date_debut'], 'd/m/Y'); ?></td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo $duree; ?> jour<?php echo $duree > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?php echo BASE_URL; ?>hebergement/modifier.php?id=<?php echo $nuitee['id']; ?>" 
                                   class="btn btn-outline-primary"
                                   data-bs-toggle="tooltip"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>hebergement/liberer.php?id=<?php echo $nuitee['id']; ?>" 
                                   class="btn btn-outline-danger"
                                   data-bs-toggle="tooltip"
                                   title="Libérer la chambre"
                                   onclick="return confirm('Voulez-vous vraiment libérer cette chambre ?');">
                                    <i class="fas fa-sign-out-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Actions supplémentaires -->
    <?php if (hasPermission('administrateur')): ?>
    <div class="mt-4">
        <a href="<?php echo BASE_URL; ?>hebergement/historique.php" class="btn btn-info">
            <i class="fas fa-history me-2"></i>
            Voir l'historique complet
        </a>
        <a href="<?php echo BASE_URL; ?>hebergement/export.php" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>
            Exporter les statistiques
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>