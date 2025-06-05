<?php
// repas/export.php - Export des repas
require_once '../config.php';
require_once '../includes/classes/Auth.php';

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

if (!$auth->hasRole('administrateur')) {
    redirect('dashboard/', MSG_PERMISSION_DENIED, 'error');
}

// Récupérer les paramètres
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$typeRepasId = $_GET['type_repas'] ?? '';
$modePaiement = $_GET['mode_paiement'] ?? '';
$personneId = $_GET['personne_id'] ?? '';

try {
    $db = getDB();
    
    // Construire la requête avec filtres
    $sql = "SELECT 
        r.date_repas,
        p.nom,
        p.prenom,
        p.role as personne_role,
        tr.nom as type_repas,
        r.mode_paiement,
        r.montant,
        u.nom_complet as saisi_par,
        r.date_saisie
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
    
    $sql .= " ORDER BY r.date_repas DESC, p.nom, p.prenom";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $repas = $stmt->fetchAll();
    
    // Statistiques par type de repas
    $statsSql = "SELECT 
        tr.nom as type_repas,
        COUNT(*) as nombre,
        SUM(CASE WHEN r.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) as gratuits,
        SUM(CASE WHEN r.mode_paiement = 'comptant' THEN 1 ELSE 0 END) as comptant,
        SUM(CASE WHEN r.mode_paiement = 'credit' THEN 1 ELSE 0 END) as credit,
        SUM(CASE WHEN r.mode_paiement != 'gratuit' THEN IFNULL(r.montant, 0) ELSE 0 END) as revenus
    FROM repas r
    JOIN types_repas tr ON r.type_repas_id = tr.id
    WHERE r.date_repas BETWEEN :date_debut AND :date_fin";
    
    if ($typeRepasId) {
        $statsSql .= " AND r.type_repas_id = :type_repas_id";
    }
    if ($modePaiement) {
        $statsSql .= " AND r.mode_paiement = :mode_paiement";
    }
    if ($personneId) {
        $statsSql .= " AND r.personne_id = :personne_id";
    }
    
    $statsSql .= " GROUP BY tr.id, tr.nom ORDER BY tr.id";
    
    $statsStmt = $db->prepare($statsSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetchAll();
    
    // Créer le fichier CSV
    $filename = sprintf("GMH_Repas_%s_%s.csv", 
        str_replace('-', '', $dateDebut), 
        str_replace('-', '', $dateFin)
    );
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // UTF-8 BOM pour Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Titre et période
    fputcsv($output, ['RAPPORT DES REPAS - ' . SITE_NAME], ';');
    fputcsv($output, ['Période: du ' . formatDate($dateDebut, 'd/m/Y') . ' au ' . formatDate($dateFin, 'd/m/Y')], ';');
    fputcsv($output, ['Généré le ' . formatDate(date('Y-m-d'), 'd/m/Y à H:i') . ' par ' . $_SESSION['user_fullname']], ';');
    fputcsv($output, [], ';');
    
    // Statistiques par type de repas
    if (!empty($stats)) {
        fputcsv($output, ['STATISTIQUES PAR TYPE DE REPAS'], ';');
        fputcsv($output, ['Type de repas', 'Total', 'Gratuits', 'Comptant', 'Crédit', 'Revenus ($)'], ';');
        
        $totalGeneral = 0;
        $totalGratuits = 0;
        $totalComptant = 0;
        $totalCredit = 0;
        $totalRevenus = 0;
        
        foreach ($stats as $stat) {
            fputcsv($output, [
                $stat['type_repas'],
                $stat['nombre'],
                $stat['gratuits'],
                $stat['comptant'],
                $stat['credit'],
                number_format($stat['revenus'], 2, ',', ' ')
            ], ';');
            
            $totalGeneral += $stat['nombre'];
            $totalGratuits += $stat['gratuits'];
            $totalComptant += $stat['comptant'];
            $totalCredit += $stat['credit'];
            $totalRevenus += $stat['revenus'];
        }
        
        // Totaux
        fputcsv($output, [
            'TOTAL',
            $totalGeneral,
            $totalGratuits,
            $totalComptant,
            $totalCredit,
            number_format($totalRevenus, 2, ',', ' ')
        ], ';');
        
        fputcsv($output, [], ';');
        fputcsv($output, [], ';');
    }
    
    // Liste détaillée
    fputcsv($output, ['LISTE DÉTAILLÉE DES REPAS (' . count($repas) . ' enregistrements)'], ';');
    fputcsv($output, [
        'Date',
        'Nom',
        'Prénom',
        'Type personne',
        'Type de repas',
        'Mode paiement',
        'Montant ($)',
        'Saisi par',
        'Date saisie'
    ], ';');
    
    foreach ($repas as $r) {
        fputcsv($output, [
            formatDate($r['date_repas'], 'd/m/Y'),
            $r['nom'],
            $r['prenom'],
            $r['personne_role'] === 'benevole' ? 'Bénévole' : 'Client',
            $r['type_repas'],
            ucfirst($r['mode_paiement']),
            $r['montant'] > 0 ? number_format($r['montant'], 2, ',', ' ') : '',
            $r['saisi_par'],
            formatDate($r['date_saisie'], 'd/m/Y H:i')
        ], ';');
    }
    
    fclose($output);
    
    // Journaliser l'export
    $details = "Export repas - Période: $dateDebut à $dateFin";
    if ($typeRepasId) $details .= ", Type: $typeRepasId";
    if ($modePaiement) $details .= ", Paiement: $modePaiement";
    if ($personneId) $details .= ", Personne: $personneId";
    $details .= " - " . count($repas) . " enregistrements";
    
    logAction('Export repas', 'repas', null, $details);

} catch (PDOException $e) {
    error_log("Erreur export repas: " . $e->getMessage());
    redirect('repas/', 'Erreur lors de l\'export des données', 'error');
}

exit();
?>