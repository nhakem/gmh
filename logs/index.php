<?php
// logs/index.php - Consultation des journaux d'activité
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

$currentPage = 'logs';
$pageTitle = 'Journaux d\'activité - ' . SITE_NAME;

// Paramètres de filtrage
$dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$utilisateur = $_GET['utilisateur'] ?? '';
$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limite = 50;
$offset = ($page - 1) * $limite;

try {
    $db = getDB();
    
    // Construction de la requête avec filtres
    $where = ['1=1'];
    $params = [];
    
    if ($dateDebut) {
        $where[] = "DATE(l.date_action) >= ?";
        $params[] = $dateDebut;
    }
    
    if ($dateFin) {
        $where[] = "DATE(l.date_action) <= ?";
        $params[] = $dateFin;
    }
    
    if ($utilisateur) {
        $where[] = "l.utilisateur_id = ?";
        $params[] = $utilisateur;
    }
    
    if ($action) {
        $where[] = "l.action LIKE ?";
        $params[] = "%$action%";
    }
    
    if ($table) {
        $where[] = "l.table_concernee = ?";
        $params[] = $table;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Compte total pour pagination
    $countStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM logs_saisie l 
        JOIN utilisateurs u ON l.utilisateur_id = u.id 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limite);
    
    // Récupération des logs
    $logsStmt = $db->prepare("
        SELECT 
            l.*,
            u.nom_complet,
            u.nom_utilisateur
        FROM logs_saisie l
        JOIN utilisateurs u ON l.utilisateur_id = u.id
        WHERE $whereClause
        ORDER BY l.date_action DESC
        LIMIT $limite OFFSET $offset
    ");
    $logsStmt->execute($params);
    $logs = $logsStmt->fetchAll();
    
    // Récupération des utilisateurs pour le filtre
    $utilisateurs = $db->query("
        SELECT id, nom_complet, nom_utilisateur 
        FROM utilisateurs 
        WHERE actif = 1 
        ORDER BY nom_complet
    ")->fetchAll();
    
    // Récupération des actions distinctes
    $actions = $db->query("
        SELECT DISTINCT action 
        FROM logs_saisie 
        WHERE action IS NOT NULL 
        ORDER BY action
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupération des tables distinctes
    $tables = $db->query("
        SELECT DISTINCT table_concernee 
        FROM logs_saisie 
        WHERE table_concernee IS NOT NULL 
        ORDER BY table_concernee
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    // Statistiques rapides
    $statsAujourdhui = $db->query("
        SELECT 
            COUNT(*) as total_actions,
            COUNT(DISTINCT utilisateur_id) as utilisateurs_actifs,
            COUNT(DISTINCT action) as types_actions
        FROM logs_saisie 
        WHERE DATE(date_action) = CURDATE()
    ")->fetch();

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des journaux : " . $e->getMessage();
    error_log($error);
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-history me-2"></i>
        Journaux d'activité
    </h1>
    <div class="btn-group">
        <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt me-1"></i>
            Actualiser
        </button>
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#exportModal">
            <i class="fas fa-download me-1"></i>
            Exporter
        </button>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeModal">
            <i class="fas fa-trash me-1"></i>
            Purger
        </button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon bg-primary text-white mx-auto mb-2">
                <i class="fas fa-list"></i>
            </div>
            <h4><?php echo number_format($statsAujourdhui['total_actions']); ?></h4>
            <p class="text-muted mb-0">Actions aujourd'hui</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon bg-success text-white mx-auto mb-2">
                <i class="fas fa-users"></i>
            </div>
            <h4><?php echo number_format($statsAujourdhui['utilisateurs_actifs']); ?></h4>
            <p class="text-muted mb-0">Utilisateurs actifs</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon bg-info text-white mx-auto mb-2">
                <i class="fas fa-cogs"></i>
            </div>
            <h4><?php echo number_format($totalLogs); ?></h4>
            <p class="text-muted mb-0">Total des logs</p>
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
                <label for="date_debut" class="form-label">Date de début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo htmlspecialchars($dateDebut); ?>">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date de fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?php echo htmlspecialchars($dateFin); ?>">
            </div>
            <div class="col-md-3">
                <label for="utilisateur" class="form-label">Utilisateur</label>
                <select class="form-select" id="utilisateur" name="utilisateur">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($utilisateurs as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $user['id'] == $utilisateur ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nom_complet']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">Toutes les actions</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?php echo htmlspecialchars($act); ?>" 
                                <?php echo $act == $action ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($act); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="table" class="form-label">Table</label>
                <select class="form-select" id="table" name="table">
                    <option value="">Toutes les tables</option>
                    <?php foreach ($tables as $tbl): ?>
                        <option value="<?php echo htmlspecialchars($tbl); ?>" 
                                <?php echo $tbl == $table ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table des logs -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            Journaux d'activité (<?php echo number_format($totalLogs); ?> entrées)
        </h5>
        <small class="text-muted">
            Page <?php echo $page; ?> sur <?php echo $totalPages; ?>
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date/Heure</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>ID</th>
                        <th>Détails</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small>
                                    <?php echo formatDate($log['date_action'], 'd/m/Y H:i:s'); ?>
                                </small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['nom_complet']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($log['nom_utilisateur']); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    echo match(true) {
                                        str_contains($log['action'], 'create') || str_contains($log['action'], 'ajouter') => 'bg-success',
                                        str_contains($log['action'], 'update') || str_contains($log['action'], 'modifier') => 'bg-warning',
                                        str_contains($log['action'], 'delete') || str_contains($log['action'], 'supprimer') => 'bg-danger',
                                        str_contains($log['action'], 'login') || str_contains($log['action'], 'connexion') => 'bg-info',
                                        default => 'bg-secondary'
                                    };
                                ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['table_concernee']): ?>
                                    <code><?php echo htmlspecialchars($log['table_concernee']); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['id_enregistrement']): ?>
                                    <span class="badge bg-light text-dark">
                                        #<?php echo $log['id_enregistrement']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['details']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            data-bs-toggle="tooltip" 
                                            title="<?php echo htmlspecialchars($log['details']); ?>">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                Aucun journal trouvé pour les critères sélectionnés
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <nav aria-label="Pagination des journaux">
            <ul class="pagination justify-content-center mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            Précédent
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Suivant
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- Modal Export -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exporter les journaux</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="export_logs.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Période d'export</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="date" class="form-control" name="export_date_debut" 
                                       value="<?php echo $dateDebut; ?>">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" name="export_date_fin" 
                                       value="<?php echo $dateFin; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Exporter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Purge -->
<div class="modal fade" id="purgeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Purger les journaux</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="purge_logs.php" method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention !</strong> Cette action est irréversible.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supprimer les logs antérieurs à :</label>
                        <input type="date" class="form-control" name="purge_date" 
                               value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirm_purge" required>
                        <label class="form-check-label">
                            Je confirme vouloir supprimer définitivement ces journaux
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Purger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include ROOT_PATH . '/includes/footer.php';
?>