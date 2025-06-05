<?php
// medicaments/index.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

$pageTitle = 'Gestion des médicaments - ' . SITE_NAME;
$currentPage = 'medicaments';

$db = getDB();

// Filtres
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$medicamentId = $_GET['medicament_id'] ?? '';
$gratuitOnly = isset($_GET['gratuit_only']) ? true : false;

// Statistiques générales
$statsSql = "SELECT 
    COUNT(DISTINCT p.personne_id) as personnes_sous_traitement,
    COUNT(DISTINCT p.medicament_id) as medicaments_differents,
    COUNT(*) as prescriptions_actives,
    SUM(CASE WHEN p.gratuit = TRUE THEN 1 ELSE 0 END) as prescriptions_gratuites,
    SUM(CASE WHEN p.gratuit = FALSE THEN 1 ELSE 0 END) as prescriptions_payantes,
    SUM(CASE WHEN p.gratuit = FALSE THEN IFNULL(p.cout, 0) ELSE 0 END) as cout_total
    FROM prescriptions p
    WHERE p.date_debut <= CURDATE() 
    AND (p.date_fin IS NULL OR p.date_fin >= CURDATE())";

$stats = $db->query($statsSql)->fetch();

// Liste des prescriptions avec filtres
$sql = "SELECT p.*, 
        pers.nom as personne_nom, 
        pers.prenom as personne_prenom,
        m.nom as medicament_nom,
        m.forme as medicament_forme,
        u.nom_complet as prescrit_par
        FROM prescriptions p
        JOIN personnes pers ON p.personne_id = pers.id
        JOIN medicaments m ON p.medicament_id = m.id
        JOIN utilisateurs u ON p.saisi_par = u.id
        WHERE 1=1";

$params = [];

if ($dateDebut && $dateFin) {
    $sql .= " AND ((p.date_debut BETWEEN :date_debut AND :date_fin) 
              OR (p.date_fin BETWEEN :date_debut2 AND :date_fin2)
              OR (p.date_debut <= :date_debut3 AND (p.date_fin IS NULL OR p.date_fin >= :date_fin3)))";
    $params[':date_debut'] = $dateDebut;
    $params[':date_fin'] = $dateFin;
    $params[':date_debut2'] = $dateDebut;
    $params[':date_fin2'] = $dateFin;
    $params[':date_debut3'] = $dateDebut;
    $params[':date_fin3'] = $dateFin;
}

if ($medicamentId) {
    $sql .= " AND p.medicament_id = :medicament_id";
    $params[':medicament_id'] = $medicamentId;
}

if ($gratuitOnly) {
    $sql .= " AND p.gratuit = TRUE";
}

$sql .= " ORDER BY p.date_debut DESC, p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// Liste des médicaments pour le filtre
$medicaments = $db->query("SELECT * FROM medicaments ORDER BY nom")->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-pills me-2 text-primary"></i>
            Gestion des médicaments
        </h1>
        <div>
            <a href="<?php echo BASE_URL; ?>medicaments/prescrire.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                Nouvelle prescription
            </a>
            <?php if (hasPermission('administrateur')): ?>
            <a href="<?php echo BASE_URL; ?>medicaments/gerer.php" class="btn btn-outline-primary">
                <i class="fas fa-cog me-2"></i>
                Gérer les médicaments
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-icon bg-primary bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['personnes_sous_traitement']; ?></h4>
                <p class="text-muted mb-0 small">Personnes traitées</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-icon bg-info bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-prescription"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['prescriptions_actives']; ?></h4>
                <p class="text-muted mb-0 small">Prescriptions actives</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-icon bg-success bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-capsules"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['medicaments_differents']; ?></h4>
                <p class="text-muted mb-0 small">Médicaments différents</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-icon bg-warning bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-gift"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['prescriptions_gratuites']; ?></h4>
                <p class="text-muted mb-0 small">Gratuites</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-icon bg-secondary bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h4 class="mb-1"><?php echo $stats['prescriptions_payantes']; ?></h4>
                <p class="text-muted mb-0 small">Payantes</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-icon bg-danger bg-gradient text-white mx-auto mb-2">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h4 class="mb-1"><?php echo number_format($stats['cout_total'], 2); ?> $</h4>
                <p class="text-muted mb-0 small">Coût total</p>
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
            <div class="col-md-3">
                <label class="form-label">Médicament</label>
                <select class="form-select" name="medicament_id">
                    <option value="">Tous les médicaments</option>
                    <?php foreach ($medicaments as $med): ?>
                        <option value="<?php echo $med['id']; ?>" <?php echo $medicamentId == $med['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($med['nom']); ?>
                            <?php if ($med['forme']): ?>
                                (<?php echo htmlspecialchars($med['forme']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="gratuit_only" id="gratuit_only" <?php echo $gratuitOnly ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="gratuit_only">
                        Gratuits
                    </label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary me-2">
                    <i class="fas fa-filter me-2"></i>
                    Filtrer
                </button>
                <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Liste des prescriptions -->
    <div class="table-container">
        <h5 class="mb-3">
            <i class="fas fa-list me-2 text-primary"></i>
            Prescriptions en cours
        </h5>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Médicament</th>
                        <th>Dosage</th>
                        <th>Fréquence</th>
                        <th>Période</th>
                        <th>Coût</th>
                        <th>Prescrit par</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $p): 
                        $dateDebut = new DateTime($p['date_debut']);
                        $dateFin = $p['date_fin'] ? new DateTime($p['date_fin']) : null;
                        $aujourdhui = new DateTime();
                        
                        // Vérifier si la prescription est active
                        $active = $dateDebut <= $aujourdhui && (!$dateFin || $dateFin >= $aujourdhui);
                        
                        // Calculer les jours restants
                        $joursRestants = $dateFin ? $dateFin->diff($aujourdhui)->days : null;
                    ?>
                    <tr class="<?php echo !$active ? 'table-secondary' : ''; ?>">
                        <td><?php echo $p['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['personne_prenom'] . ' ' . $p['personne_nom']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-primary">
                                <?php echo htmlspecialchars($p['medicament_nom']); ?>
                            </span>
                            <?php if ($p['medicament_forme']): ?>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($p['medicament_forme']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['dosage']); ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($p['frequence']); ?></small>
                        </td>
                        <td>
                            <small>
                                Du <?php echo formatDate($p['date_debut'], 'd/m/Y'); ?><br>
                                <?php if ($p['date_fin']): ?>
                                    Au <?php echo formatDate($p['date_fin'], 'd/m/Y'); ?>
                                    <?php if ($active && $joursRestants !== null): ?>
                                        <span class="badge bg-<?php echo $joursRestants <= 3 ? 'warning' : 'info'; ?>">
                                            <?php echo $joursRestants; ?> jour<?php echo $joursRestants > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Indéterminée</span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($p['gratuit']): ?>
                                <span class="badge bg-success">Gratuit</span>
                            <?php else: ?>
                                <strong><?php echo number_format($p['cout'], 2); ?> $</strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?php echo htmlspecialchars($p['prescrit_par']); ?><br>
                                <span class="text-muted"><?php echo formatDate($p['date_saisie'], 'd/m H:i'); ?></span>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if ($active): ?>
                                    <a href="<?php echo BASE_URL; ?>medicaments/modifier.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-outline-primary"
                                       data-bs-toggle="tooltip"
                                       title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>medicaments/renouveler.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-outline-info"
                                       data-bs-toggle="tooltip"
                                       title="Renouveler">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>medicaments/arreter.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-outline-warning"
                                       data-bs-toggle="tooltip"
                                       title="Arrêter"
                                       onclick="return confirm('Arrêter cette prescription ?');">
                                        <i class="fas fa-stop"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('administrateur')): ?>
                                    <button onclick="confirmDelete('<?php echo BASE_URL; ?>medicaments/supprimer.php?id=<?php echo $p['id']; ?>', 'Supprimer cette prescription ?')" 
                                            class="btn btn-outline-danger"
                                            data-bs-toggle="tooltip"
                                            title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
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
        <a href="<?php echo BASE_URL; ?>medicaments/historique.php" class="btn btn-info">
            <i class="fas fa-history me-2"></i>
            Historique complet
        </a>
        <a href="<?php echo BASE_URL; ?>medicaments/export.php?date_debut=<?php echo $dateDebut; ?>&date_fin=<?php echo $dateFin; ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>
            Exporter
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>