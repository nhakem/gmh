<?php
// maintenance/check_integrity.php - Vérification d'intégrité
require_once '../config.php';
require_once ROOT_PATH . '/includes/classes/Auth.php';

header('Content-Type: application/json');

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!hasPermission('administrateur')) {
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit;
}

try {
    $db = getDB();
    
    $results = [];
    $issues = [];
    
    // Vérification 1: Personnes sans nuitées
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM personnes p 
        LEFT JOIN nuitees n ON p.id = n.personne_id 
        WHERE n.personne_id IS NULL AND p.role = 'client'
    ");
    $count = $stmt->fetch()['count'];
    $results['Personnes sans nuitées'] = $count;
    if ($count > 0) $issues[] = "$count personnes clients sans nuitées";
    
    // Vérification 2: Nuitées sans date de fin anciennes
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM nuitees 
        WHERE date_fin IS NULL AND date_debut < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    ");
    $count = $stmt->fetch()['count'];
    $results['Nuitées anciennes ouvertes'] = $count;
    if ($count > 0) $issues[] = "$count nuitées ouvertes depuis plus d'un an";
    
    // Vérification 3: Conflits de réservation
    $stmt = $db->query("
        SELECT COUNT(DISTINCT n1.id) as count 
        FROM nuitees n1
        JOIN nuitees n2 ON n1.chambre_id = n2.chambre_id 
            AND n1.id < n2.id
            AND n1.date_debut <= IFNULL(n2.date_fin, CURDATE())
            AND IFNULL(n1.date_fin, CURDATE()) >= n2.date_debut
        WHERE n1.actif = 1 AND n2.actif = 1
    ");
    $count = $stmt->fetch()['count'];
    $results['Conflits de réservation'] = $count;
    if ($count > 0) $issues[] = "$count conflits de réservation détectés";
    
    // Vérification 4: Utilisateurs inactifs
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM utilisateurs 
        WHERE (derniere_connexion < DATE_SUB(CURDATE(), INTERVAL 90 DAY) 
               OR derniere_connexion IS NULL) 
              AND actif = 1
    ");
    $count = $stmt->fetch()['count'];
    $results['Utilisateurs inactifs'] = $count;
    if ($count > 5) $issues[] = "$count utilisateurs inactifs depuis 90+ jours";
    
    // Vérification 5: Repas orphelins
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM repas r
        LEFT JOIN personnes p ON r.personne_id = p.id
        WHERE p.id IS NULL
    ");
    $count = $stmt->fetch()['count'];
    $results['Repas orphelins'] = $count;
    if ($count > 0) $issues[] = "$count repas sans personne associée";
    
    // Vérification 6: Prescriptions orphelines
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM prescriptions pr
        LEFT JOIN personnes p ON pr.personne_id = p.id
        WHERE p.id IS NULL
    ");
    $count = $stmt->fetch()['count'];
    $results['Prescriptions orphelines'] = $count;
    if ($count > 0) $issues[] = "$count prescriptions sans personne associée";
    
    // Vérification 7: Médicaments non utilisés
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM medicaments m
        LEFT JOIN prescriptions p ON m.id = p.medicament_id
        WHERE p.medicament_id IS NULL
    ");
    $count = $stmt->fetch()['count'];
    $results['Médicaments non utilisés'] = $count;
    
    // Vérification 8: Âges incohérents
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM personnes 
        WHERE date_naissance IS NOT NULL 
          AND age IS NOT NULL
          AND age != TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())
    ");
    $count = $stmt->fetch()['count'];
    $results['Âges incohérents'] = $count;
    if ($count > 0) $issues[] = "$count âges ne correspondent pas aux dates de naissance";
    
    // Vérification 9: Chambres surnuméraires
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM nuitees n
        JOIN chambres c ON n.chambre_id = c.id
        WHERE n.actif = 1 
          AND (n.date_fin IS NULL OR n.date_fin >= CURDATE())
        GROUP BY n.chambre_id
        HAVING COUNT(*) > 1
    ");
    $conflicts = $stmt->fetchAll();
    $conflictCount = count($conflicts);
    $results['Chambres en surréservation'] = $conflictCount;
    if ($conflictCount > 0) $issues[] = "$conflictCount chambres avec plusieurs occupants simultanés";
    
    // Vérification 10: Intégrité des tables
    $tableIntegrity = [];
    $tables = ['personnes', 'nuitees', 'repas', 'medicaments', 'prescriptions', 'utilisateurs'];
    
    foreach ($tables as $table) {
        try {
            $checkResult = $db->query("CHECK TABLE `$table`");
            $check = $checkResult->fetch();
            $tableIntegrity[$table] = $check['Msg_text'] ?? 'OK';
            
            if (isset($check['Msg_text']) && strpos($check['Msg_text'], 'corrupt') !== false) {
                $issues[] = "Table $table corrompue";
            }
        } catch (PDOException $e) {
            $tableIntegrity[$table] = 'Erreur: ' . $e->getMessage();
            $issues[] = "Erreur vérification table $table";
        }
    }
    
    // Calcul du score de santé
    $totalChecks = count($results);
    $criticalIssues = count($issues);
    $healthScore = max(0, (($totalChecks - $criticalIssues) / $totalChecks) * 100);
    
    // Log de l'action
    logAction('check_integrity', 'maintenance', null, "Vérification d'intégrité: $criticalIssues problèmes détectés");
    
    echo json_encode([
        'success' => true, 
        'results' => $results,
        'table_integrity' => $tableIntegrity,
        'issues' => $issues,
        'health_score' => round($healthScore, 1),
        'summary' => count($issues) === 0 ? 
            'Aucun problème détecté. Système en bon état.' : 
            count($issues) . ' problème(s) détecté(s) nécessitant une attention.'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>