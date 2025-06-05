<?php
// maintenance/index.php - Outils de maintenance et administration
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

$currentPage = 'maintenance';
$pageTitle = 'Maintenance système - ' . SITE_NAME;

try {
    $db = getDB();
    
    // Informations système
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'mysql_version' => $db->query("SELECT VERSION() as version")->fetch()['version'],
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu',
        'disk_space_total' => disk_total_space('.'),
        'disk_space_free' => disk_free_space('.'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'timezone' => date_default_timezone_get()
    ];
    
    // Statistiques de la base de données
    $dbStats = [];
    $tables = ['personnes', 'nuitees', 'repas', 'medicaments', 'prescriptions', 'utilisateurs', 'logs_saisie'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
        $dbStats[$table] = $stmt->fetch()['count'];
    }
    
    // Taille de la base de données
    $dbSizeStmt = $db->prepare("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = ? 
        ORDER BY size_mb DESC
    ");
    $dbSizeStmt->execute([DB_NAME]);
    $dbSizes = $dbSizeStmt->fetchAll();
    
    // Vérification de l'intégrité
    $integrityChecks = [
        'personnes_orphelines' => $db->query("
            SELECT COUNT(*) as count 
            FROM personnes p 
            LEFT JOIN nuitees n ON p.id = n.personne_id 
            WHERE n.personne_id IS NULL AND p.role = 'client'
        ")->fetch()['count'],
        
        'nuitees_sans_fin' => $db->query("
            SELECT COUNT(*) as count 
            FROM nuitees 
            WHERE date_fin IS NULL AND date_debut < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        ")->fetch()['count'],
        
        'chambres_surnumeraires' => $db->query("
            SELECT COUNT(*) as count 
            FROM nuitees n1
            JOIN nuitees n2 ON n1.chambre_id = n2.chambre_id 
                AND n1.id != n2.id
                AND n1.date_debut <= IFNULL(n2.date_fin, CURDATE())
                AND IFNULL(n1.date_fin, CURDATE()) >= n2.date_debut
            WHERE n1.actif = 1 AND n2.actif = 1
        ")->fetch()['count'],
        
        'users_inactifs' => $db->query("
            SELECT COUNT(*) as count 
            FROM utilisateurs 
            WHERE derniere_connexion < DATE_SUB(CURDATE(), INTERVAL 90 DAY) 
                OR derniere_connexion IS NULL
        ")->fetch()['count']
    ];
    
    // Logs récents
    $recentLogs = $db->query("
        SELECT 
            l.action,
            l.date_action,
            u.nom_complet,
            l.table_concernee
        FROM logs_saisie l
        JOIN utilisateurs u ON l.utilisateur_id = u.id
        ORDER BY l.date_action DESC
        LIMIT 5
    ")->fetchAll();
    
    // Sauvegarde info
    $backupDir = ROOT_PATH . '/backups/';
    $lastBackup = null;
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '*.sql');
        if ($files) {
            $lastBackupFile = max($files);
            $lastBackup = [
                'file' => basename($lastBackupFile),
                'date' => date('d/m/Y H:i:s', filemtime($lastBackupFile)),
                'size' => formatBytes(filesize($lastBackupFile))
            ];
        }
    }

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des informations : " . $e->getMessage();
    error_log($error);
}

// Fonction pour formater les octets
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-tools me-2"></i>
        Maintenance système
    </h1>
    <div class="btn-group">
        <button type="button" class="btn btn-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt me-1"></i>
            Actualiser
        </button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

<!-- Alertes système -->
<div class="row mb-4">
    <?php if ($integrityChecks['nuitees_sans_fin'] > 0): ?>
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention :</strong> <?php echo $integrityChecks['nuitees_sans_fin']; ?> nuitée(s) sans date de fin depuis plus d'un an.
            <a href="#" class="btn btn-sm btn-outline-warning ms-2">Corriger</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($integrityChecks['chambres_surnumeraires'] > 0): ?>
    <div class="col-12">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Erreur :</strong> <?php echo $integrityChecks['chambres_surnumeraires']; ?> conflit(s) de réservation détecté(s).
            <a href="#" class="btn btn-sm btn-outline-danger ms-2">Examiner</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Informations système -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-server me-2"></i>
                    Informations système
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Version PHP</strong></td>
                        <td><?php echo $systemInfo['php_version']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Version MySQL</strong></td>
                        <td><?php echo $systemInfo['mysql_version']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Serveur web</strong></td>
                        <td><?php echo $systemInfo['server_software']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Fuseau horaire</strong></td>
                        <td><?php echo $systemInfo['timezone']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Limite mémoire</strong></td>
                        <td><?php echo $systemInfo['memory_limit']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Temps d'exécution max</strong></td>
                        <td><?php echo $systemInfo['max_execution_time']; ?>s</td>
                    </tr>
                    <tr>
                        <td><strong>Taille max upload</strong></td>
                        <td><?php echo $systemInfo['upload_max_filesize']; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-hdd me-2"></i>
                    Espace disque
                </h5>
            </div>
            <div class="card-body">
                <?php 
                $diskUsedPercent = (($systemInfo['disk_space_total'] - $systemInfo['disk_space_free']) / $systemInfo['disk_space_total']) * 100;
                $diskUsed = $systemInfo['disk_space_total'] - $systemInfo['disk_space_free'];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Utilisation du disque</span>
                        <span><?php echo number_format($diskUsedPercent, 1); ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar <?php echo $diskUsedPercent > 90 ? 'bg-danger' : ($diskUsedPercent > 75 ? 'bg-warning' : 'bg-success'); ?>" 
                             style="width: <?php echo $diskUsedPercent; ?>%"></div>
                    </div>
                </div>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Espace total</strong></td>
                        <td><?php echo formatBytes($systemInfo['disk_space_total']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Espace utilisé</strong></td>
                        <td><?php echo formatBytes($diskUsed); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Espace libre</strong></td>
                        <td><?php echo formatBytes($systemInfo['disk_space_free']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques base de données -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-database me-2"></i>
                    Statistiques de la base de données
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <?php foreach ($dbStats as $table => $count): ?>
                    <tr>
                        <td><strong><?php echo ucfirst($table); ?></strong></td>
                        <td class="text-end"><?php echo number_format($count); ?> enregistrements</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <div class="mt-3">
                    <h6>Taille des tables</h6>
                    <?php foreach ($dbSizes as $table): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><?php echo $table['table_name']; ?></small>
                        <span class="badge bg-light text-dark"><?php echo $table['size_mb']; ?> MB</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-shield-alt me-2"></i>
                    Vérifications d'intégrité
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-card">
                            <h4 class="<?php echo $integrityChecks['personnes_orphelines'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                <?php echo $integrityChecks['personnes_orphelines']; ?>
                            </h4>
                            <small>Personnes sans nuitée</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-card">
                            <h4 class="<?php echo $integrityChecks['nuitees_sans_fin'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                <?php echo $integrityChecks['nuitees_sans_fin']; ?>
                            </h4>
                            <small>Nuitées anciennes ouvertes</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-card">
                            <h4 class="<?php echo $integrityChecks['chambres_surnumeraires'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $integrityChecks['chambres_surnumeraires']; ?>
                            </h4>
                            <small>Conflits de réservation</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-card">
                            <h4 class="<?php echo $integrityChecks['users_inactifs'] > 0 ? 'text-info' : 'text-success'; ?>">
                                <?php echo $integrityChecks['users_inactifs']; ?>
                            </h4>
                            <small>Utilisateurs inactifs</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Outils de maintenance -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-wrench me-2"></i>
                    Outils de maintenance
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Sauvegarde -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-save fa-2x text-primary mb-3"></i>
                                <h6>Sauvegarde</h6>
                                <p class="text-muted small">
                                    <?php if ($lastBackup): ?>
                                        Dernière : <?php echo $lastBackup['date']; ?>
                                        <br><small>(<?php echo $lastBackup['size']; ?>)</small>
                                    <?php else: ?>
                                        Aucune sauvegarde trouvée
                                    <?php endif; ?>
                                </p>
                                <button type="button" class="btn btn-primary btn-sm" 
                                        data-bs-toggle="modal" data-bs-target="#backupModal">
                                    Créer une sauvegarde
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Optimisation -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-tachometer-alt fa-2x text-success mb-3"></i>
                                <h6>Optimisation</h6>
                                <p class="text-muted small">
                                    Optimiser les tables de la base de données
                                </p>
                                <button type="button" class="btn btn-success btn-sm"
                                        onclick="optimizeDatabase()">
                                    Optimiser maintenant
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nettoyage -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-broom fa-2x text-warning mb-3"></i>
                                <h6>Nettoyage</h6>
                                <p class="text-muted small">
                                    Supprimer les fichiers temporaires et anciens logs
                                </p>
                                <button type="button" class="btn btn-warning btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#cleanupModal">
                                    Nettoyer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vérification -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x text-info mb-3"></i>
                                <h6>Vérification</h6>
                                <p class="text-muted small">
                                    Analyser l'intégrité des données
                                </p>
                                <button type="button" class="btn btn-info btn-sm"
                                        onclick="checkIntegrity()">
                                    Vérifier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logs récents -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>
            Activité récente
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recentLogs)): ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date/Heure</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Table</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><?php echo formatDate($log['date_action'], 'd/m/Y H:i'); ?></td>
                        <td><?php echo htmlspecialchars($log['nom_complet']); ?></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['table_concernee']): ?>
                                <code><?php echo htmlspecialchars($log['table_concernee']); ?></code>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center">
            <a href="<?php echo BASE_URL; ?>logs/" class="btn btn-sm btn-outline-primary">
                Voir tous les journaux
            </a>
        </div>
        <?php else: ?>
        <div class="text-center text-muted">
            <i class="fas fa-inbox fa-2x mb-2"></i><br>
            Aucune activité récente
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<!-- Modal Sauvegarde -->
<div class="modal fade" id="backupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Créer une sauvegarde</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="backup.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type de sauvegarde</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" value="full" checked>
                            <label class="form-check-label">
                                <strong>Complète</strong> - Structure + données
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" value="structure">
                            <label class="form-check-label">
                                <strong>Structure uniquement</strong> - Sans les données
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" value="data">
                            <label class="form-check-label">
                                <strong>Données uniquement</strong> - Sans la structure
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="compress" checked>
                            <label class="form-check-label">
                                Compresser la sauvegarde (ZIP)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la sauvegarde</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nettoyage -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nettoyage du système</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="cleanup.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Éléments à nettoyer :</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="clean_logs" checked>
                            <label class="form-check-label">
                                Logs de plus de 90 jours
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="clean_temp">
                            <label class="form-check-label">
                                Fichiers temporaires
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="clean_uploads">
                            <label class="form-check-label">
                                Fichiers uploadés orphelins
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="clean_sessions">
                            <label class="form-check-label">
                                Sessions expirées
                            </label>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cette opération est irréversible. Assurez-vous d'avoir une sauvegarde récente.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Nettoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function optimizeDatabase() {
    if (confirm('Optimiser la base de données ? Cette opération peut prendre quelques minutes.')) {
        fetch('optimize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Succès', 'Base de données optimisée avec succès', 'success');
            } else {
                Swal.fire('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erreur', 'Erreur lors de l\'optimisation', 'error');
        });
    }
}

function checkIntegrity() {
    Swal.fire({
        title: 'Vérification en cours...',
        text: 'Analyse de l\'intégrité des données',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('check_integrity.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            let message = 'Vérification terminée:\n\n';
            for (let [key, value] of Object.entries(data.results)) {
                message += `${key}: ${value}\n`;
            }
            Swal.fire('Vérification terminée', message, 'info');
        } else {
            Swal.fire('Erreur', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Erreur', 'Erreur lors de la vérification', 'error');
    });
}
</script>

<?php
include ROOT_PATH . '/includes/footer.php';
?>