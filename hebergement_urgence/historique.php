<?php
// hebergement_urgence/historique.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'error');
}

$pageTitle = 'Historique des hébergements d\'urgence (HD) - ' . SITE_NAME;
$currentPage = 'hebergement_urgence';

$db = getDB();

// Récupérer l'ID du type d'hébergement HD
$typeHDSql = "SELECT id FROM types_hebergement WHERE nom = 'HD'";
$typeHD = $db->query($typeHDSql)->fetch();
$typeHDId = $typeHD['id'] ?? null;

// Filtres
$filtres = [
    'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
    'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
    'personne' => $_GET['personne'] ?? '',
    'chambre' => $_GET['chambre'] ?? '',
    'statut' => $_GET['statut'] ?? 'tous'
];

// Construire la requête avec filtres
$sql = "SELECT n.*, 
        p.nom as personne_nom, 
        p.prenom as personne_prenom,
        p.sexe,
        p.age,
        c.numero as chambre_numero,
        u.nom_complet as saisi_par_nom,
        DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 as duree_jours
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        JOIN chambres c ON n.chambre_id = c.id
        JOIN utilisateurs u ON n.saisi_par = u.id
        WHERE c.type_hebergement_id = :type_id";

$params = [':type_id' => $typeHDId];

// Appliquer les filtres
if ($filtres['date_debut']) {
    $sql .= " AND (n.date_fin IS NULL OR n.date_fin >= :date_debut)";
    $params[':date_debut'] = $filtres['date_debut'];
}

if ($filtres['date_fin']) {
    $sql .= " AND n.date_debut <= :date_fin";
    $params[':date_fin'] = $filtres['date_fin'];
}

if ($filtres['personne']) {
    $sql .= " AND (LOWER(p.nom) LIKE :personne OR LOWER(p.prenom) LIKE :personne)";
    $params[':personne'] = '%' . strtolower($filtres['personne']) . '%';
}

if ($filtres['chambre']) {
    $sql .= " AND LOWER(c.numero) LIKE :chambre";
    $params[':chambre'] = '%' . strtolower($filtres['chambre']) . '%';
}

if ($filtres['statut'] === 'actif') {
    $sql .= " AND n.actif = TRUE";
} elseif ($filtres['statut'] === 'termine') {
    $sql .= " AND n.actif = FALSE";
}

$sql .= " ORDER BY n.date_debut DESC, n.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$historique = $stmt->fetchAll();

// Statistiques de la période
$statsSql = "SELECT 
    COUNT(DISTINCT n.personne_id) as nb_personnes,
    COUNT(n.id) as nb_sejours,
    SUM(DATEDIFF(IFNULL(n.date_fin, LEAST(CURDATE(), :stat_date_fin)), 
        GREATEST(n.date_debut, :stat_date_debut)) + 1) as total_nuitees,
    SUM(CASE WHEN n.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) as nb_gratuits,
    SUM(CASE WHEN n.mode_paiement != 'gratuit' THEN 
        n.tarif_journalier * DATEDIFF(IFNULL(n.date_fin, LEAST(CURDATE(), :stat_date_fin2)), 
        GREATEST(n.date_debut, :stat_date_debut2)) + 1 ELSE 0 END) as revenus_totaux
    FROM nuitees n
    JOIN chambres c ON n.chambre_id = c.id
    WHERE c.type_hebergement_id = :type_id
    AND n.date_debut <= :stat_date_fin3
    AND (n.date_fin IS NULL OR n.date_fin >= :stat_date_debut3)";

$statsParams = [
    ':type_id' => $typeHDId,
    ':stat_date_debut' => $filtres['date_debut'],
    ':stat_date_debut2' => $filtres['date_debut'],
    ':stat_date_debut3' => $filtres['date_debut'],
    ':stat_date_fin' => $filtres['date_fin'],
    ':stat_date_fin2' => $filtres['date_fin'],
    ':stat_date_fin3' => $filtres['date_fin']
];

$statsStmt = $db->prepare($statsSql);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-history me-2 text-danger"></i>
            Historique des hébergements d'urgence (HD)
        </h1>
        <a href="<?php echo BASE_URL; ?>hebergement_urgence/" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Retour
        </a>
    </div>

    <!-- Filtres -->
    <div class="table-container mb-4">
        <h5 class="mb-3">
            <i class="fas fa-filter me-2 text-danger"></i>
            Filtres
        </h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo $filtres['date_debut']; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?php echo $filtres['date_fin']; ?>">
            </div>
            <div class="col-md-2">
                <label for="personne" class="form-label">Personne</label>
                <input type="text" class="form-control" id="personne" name="personne" 
                       placeholder="Nom ou prénom" value="<?php echo htmlspecialchars($filtres['personne']); ?>">
            </div>
            <div class="col-md-2">
                <label for="chambre" class="form-label">Chambre HD</label>
                <input type="text" class="form-control" id="chambre" name="chambre" 
                       placeholder="Numéro" value="<?php echo htmlspecialchars($filtres['chambre']); ?>">
            </div>
            <div class="col-md-2">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="tous" <?php echo $filtres['statut'] === 'tous' ? 'selected' : ''; ?>>Tous</option>
                    <option value="actif" <?php echo $filtres['statut'] === 'actif' ? 'selected' : ''; ?>>Actifs</option>
                    <option value="termine" <?php echo $filtres['statut'] === 'termine' ? 'selected' : ''; ?>>Terminés</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-search me-2"></i>
                    Rechercher
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-redo me-2"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Statistiques de la période -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-danger bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['nb_personnes'] ?? 0; ?></h4>
                <p class="text-muted mb-0">Personnes HD</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-info bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-bed"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['nb_sejours'] ?? 0; ?></h4>
                <p class="text-muted mb-0">Séjours HD</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-success bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-moon"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['total_nuitees'] ?? 0; ?></h4>
                <p class="text-muted mb-0">Nuitées HD</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon bg-warning bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h4 class="mb-1"><?php echo number_format($stats['revenus_totaux'] ?? 0, 2); ?> $</h4>
                <p class="text-muted mb-0">Revenus HD</p>
            </div>
        </div>
    </div>

    <!-- Tableau de l'historique -->
    <div class="table-container">
        <h5 class="mb-3">
            <i class="fas fa-list me-2 text-danger"></i>
            Historique détaillé HD
        </h5>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Personne</th>
                        <th>Chambre HD</th>
                        <th>Date début</th>
                        <th>Date fin</th>
                        <th>Durée</th>
                        <th>Mode paiement</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Saisi par</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $sejour): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($sejour['personne_prenom'] . ' ' . $sejour['personne_nom']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <?php echo $sejour['sexe']; ?> - 
                                <?php echo $sejour['age'] ? $sejour['age'] . ' ans' : 'Âge inconnu'; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-danger">
                                <?php echo htmlspecialchars($sejour['chambre_numero']); ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($sejour['date_debut'], 'd/m/Y'); ?></td>
                        <td>
                            <?php if ($sejour['date_fin']): ?>
                                <?php echo formatDate($sejour['date_fin'], 'd/m/Y'); ?>
                            <?php else: ?>
                                <span class="text-muted">En cours</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo $sejour['duree_jours']; ?> jour<?php echo $sejour['duree_jours'] > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sejour['mode_paiement'] === 'gratuit'): ?>
                                <span class="badge bg-success">Gratuit</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?php echo ucfirst($sejour['mode_paiement']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sejour['mode_paiement'] !== 'gratuit' && $sejour['tarif_journalier']): ?>
                                <?php echo number_format($sejour['tarif_journalier'] * $sejour['duree_jours'], 2); ?> $
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sejour['actif']): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Terminé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($sejour['saisi_par_nom']); ?></small>
                            <br>
                            <small class="text-muted"><?php echo formatDate($sejour['date_saisie'], 'd/m/Y H:i'); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Actions -->
    <div class="mt-4">
        <a href="<?php echo BASE_URL; ?>hebergement_urgence/export.php?<?php echo http_build_query($filtres); ?>" 
           class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>
            Exporter l'historique HD
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>