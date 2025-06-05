<?php
// statistiques/export.php - Export des données en Excel
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

// Si pas de demande d'export, afficher la page de sélection
if (!isset($_POST['export_type']) && !isset($_GET['export'])) {
    $currentPage = 'statistiques';
    $pageTitle = 'Export des données - ' . SITE_NAME;
    include ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-download me-2"></i>
        Export des données
    </h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>
        Retour au tableau de bord
    </a>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-excel me-2"></i>
                    Sélectionner les données à exporter
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type d'export</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" 
                                           id="export_complet" value="complet" checked>
                                    <label class="form-check-label" for="export_complet">
                                        <strong>Export complet</strong><br>
                                        <small class="text-muted">Toutes les données de la période</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" 
                                           id="export_statistiques" value="statistiques">
                                    <label class="form-check-label" for="export_statistiques">
                                        <strong>Statistiques uniquement</strong><br>
                                        <small class="text-muted">Données agrégées et indicateurs</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="options_complet">
                        <label class="form-label">Données à inclure</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_personnes" 
                                           id="include_personnes" value="1" checked>
                                    <label class="form-check-label" for="include_personnes">
                                        Données des personnes
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_nuitees" 
                                           id="include_nuitees" value="1" checked>
                                    <label class="form-check-label" for="include_nuitees">
                                        Nuitées et hébergements
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_repas" 
                                           id="include_repas" value="1" checked>
                                    <label class="form-check-label" for="include_repas">
                                        Repas
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_medicaments" 
                                           id="include_medicaments" value="1">
                                    <label class="form-check-label" for="include_medicaments">
                                        Médicaments et prescriptions
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="format" class="form-label">Format d'export</label>
                        <select class="form-select" id="format" name="format">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        L'export peut prendre quelques secondes selon la quantité de données.
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-download me-2"></i>
                            Générer et télécharger l'export
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher/masquer les options selon le type d'export
document.querySelectorAll('input[name="export_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const optionsComplet = document.getElementById('options_complet');
        if (this.value === 'complet') {
            optionsComplet.style.display = 'block';
        } else {
            optionsComplet.style.display = 'none';
        }
    });
});
</script>

<?php
    include ROOT_PATH . '/includes/footer.php';
    exit;
}

// Traitement de l'export
try {
    $dateDebut = $_POST['date_debut'] ?? $_GET['date_debut'] ?? date('Y-m-01');
    $dateFin = $_POST['date_fin'] ?? $_GET['date_fin'] ?? date('Y-m-t');
    $exportType = $_POST['export_type'] ?? $_GET['type'] ?? 'complet';
    $format = $_POST['format'] ?? $_GET['format'] ?? 'xlsx';
    
    $db = getDB();
    
    // Validation des dates
    $dateDebutObj = new DateTime($dateDebut);
    $dateFinObj = new DateTime($dateFin);
    
    if ($dateFinObj < $dateDebutObj) {
        throw new Exception("La date de fin doit être postérieure à la date de début");
    }
    
    $filename = 'GMH_Export_' . $dateDebutObj->format('Y-m-d') . '_' . $dateFinObj->format('Y-m-d');
    
    if ($format === 'xlsx') {
        // Export Excel avec plusieurs feuilles
        require_once ROOT_PATH . '/vendor/autoload.php'; // Assuming PhpSpreadsheet is installed
        
        // Si PhpSpreadsheet n'est pas disponible, utiliser une approche CSV multiple
        $filename .= '.xlsx';
        exportToExcel($db, $dateDebut, $dateFin, $exportType, $filename);
        
    } else {
        // Export CSV simple
        $filename .= '.csv';
        exportToCSV($db, $dateDebut, $dateFin, $exportType, $filename);
    }
    
    // Log de l'action
    logAction('export_donnees', 'export', null, "Export $exportType du $dateDebut au $dateFin");
    
} catch (Exception $e) {
    echo "Erreur lors de l'export : " . $e->getMessage();
    exit;
}

function exportToCSV($db, $dateDebut, $dateFin, $exportType, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($exportType === 'statistiques') {
        exportStatistiquesCSV($output, $db, $dateDebut, $dateFin);
    } else {
        exportCompletCSV($output, $db, $dateDebut, $dateFin);
    }
    
    fclose($output);
}

function exportStatistiquesCSV($output, $db, $dateDebut, $dateFin) {
    // En-tête du rapport
    fputcsv($output, ['RAPPORT STATISTIQUES GMH']);
    fputcsv($output, ['Période', "Du $dateDebut au $dateFin"]);
    fputcsv($output, ['Généré le', date('d/m/Y à H:i')]);
    fputcsv($output, ['']); // Ligne vide
    
    // Statistiques générales
    $stmt = $db->prepare("CALL sp_statistiques_periode(?, ?)");
    $stmt->execute([$dateDebut, $dateFin]);
    
    $statsGenerales = $stmt->fetch();
    fputcsv($output, ['STATISTIQUES GÉNÉRALES']);
    fputcsv($output, ['Nombre de personnes hébergées', $statsGenerales['nombre_personnes_total'] ?? 0]);
    fputcsv($output, ['Total nuitées', $statsGenerales['total_nuitees'] ?? 0]);
    fputcsv($output, ['Chambres utilisées', $statsGenerales['chambres_utilisees'] ?? 0]);
    fputcsv($output, ['']); // Ligne vide
    
    // Répartition par sexe
    $stmt->nextRowset();
    $statsSexe = $stmt->fetchAll();
    fputcsv($output, ['RÉPARTITION PAR SEXE']);
    fputcsv($output, ['Sexe', 'Nombre de personnes', 'Total nuitées']);
    foreach ($statsSexe as $stat) {
        $sexeLibelle = $stat['sexe'] === 'M' ? 'Hommes' : ($stat['sexe'] === 'F' ? 'Femmes' : 'Autre');
        fputcsv($output, [$sexeLibelle, $stat['nombre_personnes'], $stat['total_nuitees']]);
    }
    fputcsv($output, ['']); // Ligne vide
    
    // Types d'hébergement
    $stmt->nextRowset();
    $statsHebergement = $stmt->fetchAll();
    fputcsv($output, ['TYPES D\'HÉBERGEMENT']);
    fputcsv($output, ['Type', 'Nombre de personnes', 'Total nuitées']);
    foreach ($statsHebergement as $hebergement) {
        fputcsv($output, [
            $hebergement['type_hebergement'],
            $hebergement['nombre_personnes'],
            $hebergement['total_nuitees']
        ]);
    }
}

function exportCompletCSV($output, $db, $dateDebut, $dateFin) {
    // Export des personnes hébergées pendant la période
    fputcsv($output, ['PERSONNES HÉBERGÉES']);
    fputcsv($output, [
        'ID', 'Nom', 'Prénom', 'Sexe', 'Age', 'Date naissance', 
        'Ville', 'Origine', 'Téléphone', 'Email', 'Date inscription'
    ]);
    
    $stmt = $db->prepare("
        SELECT DISTINCT p.*
        FROM personnes p
        JOIN nuitees n ON p.id = n.personne_id
        WHERE n.actif = 1 
          AND n.date_debut BETWEEN ? AND ?
        ORDER BY p.nom, p.prenom
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    
    while ($personne = $stmt->fetch()) {
        fputcsv($output, [
            $personne['id'],
            $personne['nom'],
            $personne['prenom'],
            $personne['sexe'],
            $personne['age'],
            $personne['date_naissance'],
            $personne['ville'],
            $personne['origine'],
            $personne['telephone'],
            $personne['email'],
            $personne['date_inscription']
        ]);
    }
    
    fputcsv($output, ['']); // Ligne vide
    
    // Export des nuitées
    fputcsv($output, ['NUITÉES']);
    fputcsv($output, [
        'ID', 'Personne', 'Chambre', 'Type hébergement', 'Date début', 
        'Date fin', 'Nombre nuits', 'Mode paiement', 'Tarif journalier', 'Montant total'
    ]);
    
    $stmt = $db->prepare("
        SELECT 
            n.id,
            CONCAT(p.nom, ' ', p.prenom) as personne,
            c.numero as chambre,
            th.nom as type_hebergement,
            n.date_debut,
            n.date_fin,
            DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 as nombre_nuits,
            n.mode_paiement,
            n.tarif_journalier,
            n.montant_total
        FROM nuitees n
        JOIN personnes p ON n.personne_id = p.id
        JOIN chambres c ON n.chambre_id = c.id
        JOIN types_hebergement th ON c.type_hebergement_id = th.id
        WHERE n.actif = 1 
          AND n.date_debut BETWEEN ? AND ?
        ORDER BY n.date_debut DESC
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    
    while ($nuitee = $stmt->fetch()) {
        fputcsv($output, [
            $nuitee['id'],
            $nuitee['personne'],
            $nuitee['chambre'],
            $nuitee['type_hebergement'],
            $nuitee['date_debut'],
            $nuitee['date_fin'],
            $nuitee['nombre_nuits'],
            $nuitee['mode_paiement'],
            $nuitee['tarif_journalier'],
            $nuitee['montant_total']
        ]);
    }
    
    fputcsv($output, ['']); // Ligne vide
    
    // Export des repas
    fputcsv($output, ['REPAS']);
    fputcsv($output, [
        'ID', 'Personne', 'Type repas', 'Date', 'Mode paiement', 'Montant'
    ]);
    
    $stmt = $db->prepare("
        SELECT 
            r.id,
            CONCAT(p.nom, ' ', p.prenom) as personne,
            tr.nom as type_repas,
            r.date_repas,
            r.mode_paiement,
            r.montant
        FROM repas r
        JOIN personnes p ON r.personne_id = p.id
        JOIN types_repas tr ON r.type_repas_id = tr.id
        WHERE r.date_repas BETWEEN ? AND ?
        ORDER BY r.date_repas DESC
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    
    while ($repas = $stmt->fetch()) {
        fputcsv($output, [
            $repas['id'],
            $repas['personne'],
            $repas['type_repas'],
            $repas['date_repas'],
            $repas['mode_paiement'],
            $repas['montant']
        ]);
    }
}

function exportToExcel($db, $dateDebut, $dateFin, $exportType, $filename) {
    // Cette fonction nécessiterait PhpSpreadsheet
    // Pour l'instant, on redirige vers CSV
    $filename = str_replace('.xlsx', '.csv', $filename);
    exportToCSV($db, $dateDebut, $dateFin, $exportType, $filename);
}
?>