<?php
// hebergement/historique.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Historique des hébergements - ' . SITE_NAME;
$currentPage = 'hebergement';

$db = getDB();

// Filtres
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$typeHebergementId = $_GET['type_hebergement'] ?? '';
$statut = $_GET['statut'] ?? '';

// Récupérer les types d'hébergement pour le filtre
$typesHebergement = $db->query("SELECT * FROM types_hebergement ORDER BY nom")->fetchAll();

// Construire la requête
$sql = "SELECT n.*, 
        p.nom as personne_nom, 
        p.prenom as personne_prenom,
        p.role as personne_role,
        c.numero as chambre_numero,
        th.nom as type_hebergement,
        u.nom_complet as saisi_par_nom,
        DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 as duree_jours
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        JOIN chambres c ON n.chambre_id = c.id
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        JOIN utilisateurs u ON n.saisi_par = u.id
        WHERE 1=1";

$params = [];

// Filtre par dates
if ($dateDebut && $dateFin) {
    $sql .= " AND ((n.date_debut BETWEEN :date_debut AND :date_fin) 
              OR (n.date_fin BETWEEN :date_debut2 AND :date_fin2)
              OR (n.date_debut <= :date_debut3 AND (n.date_fin IS NULL OR n.date_fin >= :date_fin3)))";
    $params[':date_debut'] = $dateDebut;
    $params[':date_fin'] = $dateFin;
    $params[':date_debut2'] = $dateDebut;
    $params[':date_fin2'] = $dateFin;
    $params[':date_debut3'] = $dateDebut;
    $params[':date_fin3'] = $dateFin;
}

// Filtre par type d'hébergement
if ($typeHebergementId) {
    $sql .= " AND th.id = :type_hebergement_id";
    $params[':type_hebergement_id'] = $typeHebergementId;
}

// Filtre par statut
if ($statut === 'actif') {
    $sql .= " AND n.actif = TRUE";
} elseif ($statut === 'termine') {
    $sql .= " AND n.actif = FALSE";
}

$sql .= " ORDER BY n.date_debut DESC, n.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$historique = $stmt->fetchAll();

// Statistiques pour la période
$statsSql = "SELECT 
    COUNT(DISTINCT n.personne_id) as personnes_uniques,
    COUNT(*) as total_sejours,
    SUM(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as total_nuitees,
    AVG(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_moyenne
    FROM nuitees n
    JOIN chambres c ON n.chambre_id = c.id
    WHERE 1=1";

if ($dateDebut && $dateFin) {
    $statsSql .= " AND ((n.date_debut BETWEEN :date_debut AND :date_fin) 
                   OR (n.date_fin BETWEEN :date_debut AND :date_fin)
                   OR (n.date_debut <= :date_debut AND (n.date_fin IS NULL OR n.date_fin >= :date_fin)))";
}
if ($typeHebergementId) {
    $statsSql .= " AND c.type_hebergement_id = :type_hebergement_id";
}
if ($statut === 'actif') {
    $statsSql .= " AND n.actif = TRUE";
} elseif ($statut === 'termine') {
    $statsSql .= " AND n.actif = FALSE";
}

$statsStmt = $db->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-history me-2 text-primary"></i>
            Historique des hébergements
        </h1>
        <a href="<?php echo BASE_URL; ?>hebergement/" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Retour
        </a>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <h4 class="text-primary mb-1"><?php echo number_format($stats['personnes_uniques'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Personnes uniques</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <h4 class="text-info mb-1"><?php echo number_format($stats['total_sejours'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Séjours</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <h4 class="text-success mb-1"><?php echo number_format($stats['total_nuitees'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Nuitées totales</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <h4 class="text-warning mb-1"><?php echo number_format($stats['duree_moyenne'] ?? 0, 1); ?></h4>
                <p class="text-muted mb-0">Durée moyenne (jours)</p>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="table-container mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date début</label>
                <input type="date" 
                       class="form-control" 
                       name="date_debut" 
                       value="<?php echo $dateDebut; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date fin</label>
                <input type="date" 
                       class="form-control" 
                       name="date_fin" 
                       value="<?php echo $dateFin; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Type hébergement</label>
                <select class="form-select" name="type_hebergement">
                    <option value="">Tous</option>
                    <?php foreach ($typesHebergement as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $typeHebergementId == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous</option>
                    <option value="actif" <?php echo $statut === 'actif' ? 'selected' : ''; ?>>En cours</option>
                    <option value="termine" <?php echo $statut === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary me-2">
                    <i class="fas fa-filter me-2"></i>
                    Filtrer
                </button>
                <a href="<?php echo BASE_URL; ?>hebergement/historique.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Historique -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Personne</th>
                        <th>Chambre</th>
                        <th>Type</th>
                        <th>Date début</th>
                        <th>Date fin</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th>Saisi par</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><?php echo $h['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($h['personne_prenom'] . ' ' . $h['personne_nom']); ?></strong>
                            <small class="text-muted d-block">
                                <?php echo $h['personne_role'] === 'benevole' ? 'Bénévole' : 'Client'; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo htmlspecialchars($h['chambre_numero']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($h['type_hebergement']); ?></td>
                        <td><?php echo formatDate($h['date_debut'], 'd/m/Y'); ?></td>
                        <td>
                            <?php if ($h['date_fin']): ?>
                                <?php echo formatDate($h['date_fin'], 'd/m/Y'); ?>
                            <?php else: ?>
                                <span class="text-muted">En cours</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo $h['duree_jours']; ?> jour<?php echo $h['duree_jours'] > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($h['actif']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check"></i> En cours
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-history"></i> Terminé
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?php echo htmlspecialchars($h['saisi_par_nom']); ?><br>
                                <span class="text-muted"><?php echo formatDate($h['date_saisie'], 'd/m H:i'); ?></span>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Export -->
    <div class="mt-4 text-end">
        <a href="<?php echo BASE_URL; ?>hebergement/export.php?date_debut=<?php echo $dateDebut; ?>&date_fin=<?php echo $dateFin; ?>&type_hebergement=<?php echo $typeHebergementId; ?>&statut=<?php echo $statut; ?>" 
           class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>
            Exporter en Excel
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>