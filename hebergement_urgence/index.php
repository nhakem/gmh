<?php
// hebergement_urgence/index.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Gestion de l\'hébergement d\'urgence (HD) - ' . SITE_NAME;
$currentPage = 'hebergement_urgence';

$db = getDB();

// Récupérer l'ID du type d'hébergement HD
$typeHDSql = "SELECT id FROM types_hebergement WHERE nom = 'HD'";
$typeHD = $db->query($typeHDSql)->fetch();
$typeHDId = $typeHD['id'] ?? null;

// Récupérer les statistiques générales pour HD uniquement
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM chambres WHERE type_hebergement_id = :type_id) as total_chambres,
    (SELECT SUM(nombre_lits) FROM chambres WHERE type_hebergement_id = :type_id) as total_lits,
    (SELECT COUNT(DISTINCT c.id) FROM chambres c 
     INNER JOIN nuitees n ON c.id = n.chambre_id 
     WHERE c.type_hebergement_id = :type_id AND n.actif = TRUE 
     AND n.date_debut <= CURDATE() AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())) as chambres_occupees,
    (SELECT COUNT(DISTINCT n.personne_id) FROM nuitees n 
     INNER JOIN chambres c ON c.id = n.chambre_id 
     WHERE c.type_hebergement_id = :type_id AND n.actif = TRUE 
     AND n.date_debut <= CURDATE() AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())) as personnes_hebergees,
    (SELECT SUM(n.tarif_journalier * DATEDIFF(CURDATE(), n.date_debut) + 1) FROM nuitees n 
     INNER JOIN chambres c ON c.id = n.chambre_id 
     WHERE c.type_hebergement_id = :type_id AND n.actif = TRUE 
     AND n.mode_paiement != 'gratuit' AND n.date_debut <= CURDATE()) as revenus_actifs";

$stmt = $db->prepare($statsSql);
$stmt->execute([':type_id' => $typeHDId]);
$stats = $stmt->fetch();

// Calculer le taux d'occupation
$tauxOccupation = $stats['total_chambres'] > 0 ? round(($stats['chambres_occupees'] / $stats['total_chambres']) * 100, 1) : 0;

// Récupérer la liste des chambres HD avec leur statut actuel
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
WHERE c.type_hebergement_id = :type_id
ORDER BY c.numero";

$stmt = $db->prepare($chambresSql);
$stmt->execute([':type_id' => $typeHDId]);
$chambres = $stmt->fetchAll();

// Récupérer les nuitées actives pour HD
$nuiteesActivesSQL = "
    SELECT n.*, p.nom, p.prenom, c.numero as chambre_numero, th.nom as type_hebergement
    FROM nuitees n
    JOIN personnes p ON n.personne_id = p.id
    JOIN chambres c ON n.chambre_id = c.id
    JOIN types_hebergement th ON c.type_hebergement_id = th.id
    WHERE c.type_hebergement_id = :type_id 
    AND n.actif = TRUE AND n.date_debut <= CURDATE() 
    AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())
    ORDER BY n.date_debut DESC
";
$stmt = $db->prepare($nuiteesActivesSQL);
$stmt->execute([':type_id' => $typeHDId]);
$nuiteesActives = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-ambulance me-2 text-danger"></i>
            Gestion de l'hébergement d'urgence (HD)
        </h1>
        <div>
            <a href="<?php echo BASE_URL; ?>hebergement_urgence/attribuer.php" class="btn btn-danger">
                <i class="fas fa-plus me-2"></i>
                Attribuer une chambre HD
            </a>
            <?php if (hasPermission('administrateur')): ?>
            <a href="<?php echo BASE_URL; ?>hebergement_urgence/chambres.php" class="btn btn-outline-danger">
                <i class="fas fa-cog me-2"></i>
                Gérer les chambres HD
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-danger bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-door-open"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['total_chambres'] ?? 0; ?></h4>
                <p class="text-muted mb-0">Chambres HD totales</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-info bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-bed"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['total_lits'] ?? 0; ?></h4>
                <p class="text-muted mb-0">Lits HD totaux</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-success bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['personnes_hebergees'] ?? 0; ?></h4>
                <p class="text-muted mb-0">Personnes en HD</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-warning bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h4 class="mb-1"><?php echo $tauxOccupation; ?>%</h4>
                <p class="text-muted mb-0">Taux d'occupation HD</p>
            </div>
        </div>
    </div>
    
    <?php if (hasPermission('administrateur')): ?>
    <!-- Statistiques financières pour admin -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="stat-card">
                <h5 class="text-danger mb-3">
                    <i class="fas fa-dollar-sign me-2"></i>
                    Statistiques financières HD du mois
                </h5>
                <?php
                // Statistiques financières du mois pour HD
                $financesSql = "SELECT 
                    COUNT(CASE WHEN n.mode_paiement = 'gratuit' THEN 1 END) as hebergements_gratuits,
                    COUNT(CASE WHEN n.mode_paiement != 'gratuit' THEN 1 END) as hebergements_payants,
                    SUM(CASE WHEN n.mode_paiement != 'gratuit' THEN n.tarif_journalier * DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), CURDATE()), GREATEST(n.date_debut, DATE_FORMAT(CURDATE(), '%Y-%m-01'))) + 1 ELSE 0 END) as revenus_mois
                    FROM nuitees n
                    INNER JOIN chambres c ON c.id = n.chambre_id 
                    WHERE c.type_hebergement_id = :type_id
                    AND (n.date_debut <= CURDATE() AND (n.date_fin IS NULL OR n.date_fin >= DATE_FORMAT(CURDATE(), '%Y-%m-01')))";
                
                $stmt = $db->prepare($financesSql);
                $stmt->execute([':type_id' => $typeHDId]);
                $finances = $stmt->fetch();
                ?>
                <div class="row text-center">
                    <div class="col-md-4">
                        <h4 class="text-success mb-1"><?php echo $finances['hebergements_gratuits'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Hébergements HD gratuits</p>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-warning mb-1"><?php echo $finances['hebergements_payants'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Hébergements HD payants</p>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-danger mb-1"><?php echo number_format($finances['revenus_mois'] ?? 0, 2); ?> $</h4>
                        <p class="text-muted mb-0">Revenus HD du mois</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Vue des chambres HD -->
    <div class="table-container mb-4">
        <h5 class="mb-3">
            <i class="fas fa-door-closed me-2 text-danger"></i>
            État des chambres d'urgence (HD)
        </h5>
        <div class="row g-2">
        <?php foreach ($chambres as $chambre): ?>
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
                                <a href="<?php echo BASE_URL; ?>hebergement_urgence/liberer.php?id=<?php echo $chambre['nuitee_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Voulez-vous vraiment libérer cette chambre HD ?');">
                                    <i class="fas fa-sign-out-alt"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-success mt-2">Disponible</span>
                            <div class="mt-1">
                                <a href="<?php echo BASE_URL; ?>hebergement_urgence/attribuer.php?chambre_id=<?php echo $chambre['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-user-plus"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Liste des nuitées HD actives -->
    <div class="table-container">
        <h5 class="mb-3">
            <i class="fas fa-list me-2 text-danger"></i>
            Hébergements d'urgence actifs
        </h5>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Personne</th>
                        <th>Chambre HD</th>
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
                            <span class="badge bg-danger">
                                <?php echo htmlspecialchars($nuitee['chambre_numero']); ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($nuitee['date_debut'], 'd/m/Y'); ?></td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo $duree; ?> jour<?php echo $duree > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?php echo BASE_URL; ?>hebergement_urgence/modifier.php?id=<?php echo $nuitee['id']; ?>" 
                                   class="btn btn-outline-primary"
                                   data-bs-toggle="tooltip"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>hebergement_urgence/liberer.php?id=<?php echo $nuitee['id']; ?>" 
                                   class="btn btn-outline-danger"
                                   data-bs-toggle="tooltip"
                                   title="Libérer la chambre HD"
                                   onclick="return confirm('Voulez-vous vraiment libérer cette chambre HD ?');">
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
        <a href="<?php echo BASE_URL; ?>hebergement_urgence/historique.php" class="btn btn-info">
            <i class="fas fa-history me-2"></i>
            Voir l'historique HD complet
        </a>
        <a href="<?php echo BASE_URL; ?>hebergement_urgence/export.php" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>
            Exporter les statistiques HD
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>