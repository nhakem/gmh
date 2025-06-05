<?php
// repas/index.php - Gestion des repas
require_once '../config.php';
require_once '../includes/classes/Auth.php';

// Vérification authentification
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Gestion des repas - ' . SITE_NAME;
$currentPage = 'repas';

// Récupérer les filtres
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
$dateFin = $_GET['date_fin'] ?? date('Y-m-d'); // Aujourd'hui
$typeRepasId = $_GET['type_repas'] ?? '';
$modePaiement = $_GET['mode_paiement'] ?? '';
$personneId = $_GET['personne_id'] ?? '';

try {
    $db = getDB();
    
    // Récupérer les types de repas pour le filtre
    $typesRepas = $db->query("SELECT * FROM types_repas ORDER BY id")->fetchAll();
    
    // Construire la requête avec filtres
    $sql = "SELECT r.*, p.nom, p.prenom, p.role as personne_role, tr.nom as type_repas_nom, u.nom_complet as saisi_par_nom
            FROM repas r
            JOIN personnes p ON r.personne_id = p.id
            JOIN types_repas tr ON r.type_repas_id = tr.id
            JOIN utilisateurs u ON r.saisi_par = u.id
            WHERE r.date_repas BETWEEN :date_debut AND :date_fin";
    
    $params = [
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ];
    
    if ($typeRepasId) {
        $sql .= " AND r.type_repas_id = :type_repas_id";
        $params[':type_repas_id'] = $typeRepasId;
    }
    
    if ($modePaiement) {
        $sql .= " AND r.mode_paiement = :mode_paiement";
        $params[':mode_paiement'] = $modePaiement;
    }
    
    if ($personneId) {
        $sql .= " AND r.personne_id = :personne_id";
        $params[':personne_id'] = $personneId;
    }
    
    $sql .= " ORDER BY r.date_repas DESC, r.date_saisie DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $repas = $stmt->fetchAll();
    
    // Statistiques pour la période
    $statsSql = "SELECT 
        COUNT(*) as total_repas,
        COUNT(DISTINCT personne_id) as personnes_uniques,
        SUM(CASE WHEN mode_paiement = 'gratuit' THEN 1 ELSE 0 END) as repas_gratuits,
        SUM(CASE WHEN mode_paiement != 'gratuit' THEN 1 ELSE 0 END) as repas_payants,
        SUM(CASE WHEN mode_paiement != 'gratuit' THEN IFNULL(montant, 0) ELSE 0 END) as total_revenus
        FROM repas
        WHERE date_repas BETWEEN :date_debut AND :date_fin";
    
    if ($typeRepasId) {
        $statsSql .= " AND type_repas_id = :type_repas_id";
    }
    if ($modePaiement) {
        $statsSql .= " AND mode_paiement = :mode_paiement";
    }
    if ($personneId) {
        $statsSql .= " AND personne_id = :personne_id";
    }
    
    $statsStmt = $db->prepare($statsSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();
    
    // Récupérer les personnes pour le filtre
    $personnes = $db->query("SELECT id, nom, prenom, role FROM personnes WHERE actif = TRUE ORDER BY nom, prenom")->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
    error_log($error);
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-utensils me-2 text-primary"></i>
            Gestion des repas
        </h1>
        <a href="<?php echo BASE_URL; ?>repas/enregistrer.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            Enregistrer un repas
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-card text-center">
                <div class="stat-icon bg-primary text-white mx-auto mb-2">
                    <i class="fas fa-utensils"></i>
                </div>
                <h4 class="text-primary mb-1"><?php echo number_format($stats['total_repas']); ?></h4>
                <p class="text-muted mb-0 small">Total repas</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-card text-center">
                <div class="stat-icon bg-info text-white mx-auto mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="text-info mb-1"><?php echo number_format($stats['personnes_uniques']); ?></h4>
                <p class="text-muted mb-0 small">Personnes</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-card text-center">
                <div class="stat-icon bg-success text-white mx-auto mb-2">
                    <i class="fas fa-gift"></i>
                </div>
                <h4 class="text-success mb-1"><?php echo number_format($stats['repas_gratuits']); ?></h4>
                <p class="text-muted mb-0 small">Gratuits</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-card text-center">
                <div class="stat-icon bg-warning text-white mx-auto mb-2">
                    <i class="fas fa-money-bill"></i>
                </div>
                <h4 class="text-warning mb-1"><?php echo number_format($stats['repas_payants']); ?></h4>
                <p class="text-muted mb-0 small">Payants</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-card text-center">
                <div class="stat-icon bg-danger text-white mx-auto mb-2">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h4 class="text-danger mb-1"><?php echo number_format($stats['total_revenus'], 2); ?> $</h4>
                <p class="text-muted mb-0 small">Revenus</p>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-filter me-2"></i>
                Filtres de recherche
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date début</label>
                    <input type="date" 
                           class="form-control" 
                           name="date_debut" 
                           value="<?php echo htmlspecialchars($dateDebut); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date fin</label>
                    <input type="date" 
                           class="form-control" 
                           name="date_fin" 
                           value="<?php echo htmlspecialchars($dateFin); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type de repas</label>
                    <select class="form-select" name="type_repas">
                        <option value="">Tous</option>
                        <?php foreach ($typesRepas as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $typeRepasId == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mode paiement</label>
                    <select class="form-select" name="mode_paiement">
                        <option value="">Tous</option>
                        <option value="gratuit" <?php echo $modePaiement === 'gratuit' ? 'selected' : ''; ?>>Gratuit</option>
                        <option value="comptant" <?php echo $modePaiement === 'comptant' ? 'selected' : ''; ?>>Comptant</option>
                        <option value="credit" <?php echo $modePaiement === 'credit' ? 'selected' : ''; ?>>Crédit</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>
                            Filtrer
                        </button>
                        <a href="<?php echo BASE_URL; ?>repas/" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des repas -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                Liste des repas (<?php echo count($repas); ?> résultats)
            </h5>
            <?php if ($auth->hasRole('administrateur')): ?>
                <a href="<?php echo BASE_URL; ?>repas/export.php?<?php echo http_build_query($_GET); ?>" 
                   class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel me-1"></i>
                    Exporter
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Personne</th>
                            <th>Type repas</th>
                            <th>Mode paiement</th>
                            <th>Montant</th>
                            <th>Saisi par</th>
                            <?php if ($auth->hasRole('administrateur')): ?>
                                <th width="100">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($repas)): ?>
                            <?php foreach ($repas as $r): ?>
                            <tr>
                                <td>
                                    <strong><?php echo formatDate($r['date_repas'], 'd/m/Y'); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo formatDate($r['date_saisie'], 'H:i'); ?></small>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($r['prenom'] . ' ' . $r['nom']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $r['personne_role'] === 'benevole' ? 'Bénévole' : 'Client'; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($r['type_repas_nom']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match($r['mode_paiement']) {
                                        'gratuit' => 'bg-success',
                                        'comptant' => 'bg-warning text-dark',
                                        'credit' => 'bg-info',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($r['mode_paiement']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($r['mode_paiement'] !== 'gratuit' && $r['montant'] > 0): ?>
                                        <strong class="text-success"><?php echo number_format($r['montant'], 2); ?> $</strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($r['saisi_par_nom']); ?>
                                        <br>
                                        <span class="text-muted"><?php echo formatDate($r['date_saisie'], 'd/m H:i'); ?></span>
                                    </small>
                                </td>
                                <?php if ($auth->hasRole('administrateur')): ?>
                                <td>
                                    <button onclick="confirmDelete('<?php echo BASE_URL; ?>repas/supprimer.php?id=<?php echo $r['id']; ?>', 'Voulez-vous vraiment supprimer cet enregistrement de repas ?')" 
                                            class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="tooltip"
                                            title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $auth->hasRole('administrateur') ? '7' : '6'; ?>" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    Aucun repas trouvé pour cette période
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>