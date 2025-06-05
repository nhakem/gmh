<?php
// logs/export_logs.php - Export des journaux en CSV/Excel
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('logs/', 'Accès direct non autorisé', 'error');
}

try {
    $db = getDB();
    
    $dateDebut = $_POST['export_date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateFin = $_POST['export_date_fin'] ?? date('Y-m-d');
    $format = $_POST['format'] ?? 'csv';
    
    // Validation des dates
    $dateDebutObj = new DateTime($dateDebut);
    $dateFinObj = new DateTime($dateFin);
    
    if ($dateFinObj < $dateDebutObj) {
        throw new Exception("La date de fin doit être postérieure à la date de début");
    }
    
    // Récupération des logs
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.date_action,
            u.nom_complet,
            u.nom_utilisateur,
            l.action,
            l.table_concernee,
            l.id_enregistrement,
            l.details,
            l.ip_address
        FROM logs_saisie l
        JOIN utilisateurs u ON l.utilisateur_id = u.id
        WHERE DATE(l.date_action) BETWEEN ? AND ?
        ORDER BY l.date_action DESC
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    $logs = $stmt->fetchAll();
    
    $filename = 'GMH_Logs_' . $dateDebut . '_' . $dateFin;
    
    if ($format === 'csv') {
        exportCSV($logs, $filename);
    } else {
        exportExcel($logs, $filename);
    }
    
    // Log de l'action
    logAction('export_logs', 'logs_saisie', null, "Export logs du $dateDebut au $dateFin ($format)");
    
} catch (Exception $e) {
    redirect('logs/', 'Erreur lors de l\'export: ' . $e->getMessage(), 'error');
}

function exportCSV($logs, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    fputcsv($output, [
        'ID',
        'Date/Heure',
        'Utilisateur',
        'Login',
        'Action',
        'Table',
        'ID Enregistrement',
        'Détails',
        'Adresse IP'
    ]);
    
    // Données
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['date_action'],
            $log['nom_complet'],
            $log['nom_utilisateur'],
            $log['action'],
            $log['table_concernee'],
            $log['id_enregistrement'],
            $log['details'],
            $log['ip_address']
        ]);
    }
    
    fclose($output);
}

function exportExcel($logs, $filename) {
    // Pour une vraie implémentation Excel, utiliser PhpSpreadsheet
    // Pour l'instant, on fait un CSV avec extension .xlsx
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    
    exportCSV($logs, $filename);
}
?>