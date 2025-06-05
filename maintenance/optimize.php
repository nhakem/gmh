<?php
// maintenance/optimize.php - Optimisation de la base de données
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
    
    // Récupération de toutes les tables de la base
    $stmt = $db->prepare("SHOW TABLES FROM " . DB_NAME);
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $optimizedTables = 0;
    $errors = [];
    $results = [];
    
    foreach ($tables as $table) {
        try {
            $optimizeResult = $db->query("OPTIMIZE TABLE `$table`");
            $result = $optimizeResult->fetch();
            
            $results[$table] = [
                'status' => $result['Msg_type'] ?? 'success',
                'message' => $result['Msg_text'] ?? 'Optimisée'
            ];
            
            if (($result['Msg_type'] ?? '') !== 'error') {
                $optimizedTables++;
            }
            
        } catch (PDOException $e) {
            $errors[] = "Erreur optimisation table $table: " . $e->getMessage();
            $results[$table] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Analyse et réparation si nécessaire
    $repairedTables = 0;
    foreach ($tables as $table) {
        try {
            $checkResult = $db->query("CHECK TABLE `$table`");
            $check = $checkResult->fetch();
            
            if (isset($check['Msg_text']) && strpos($check['Msg_text'], 'corrupt') !== false) {
                $db->query("REPAIR TABLE `$table`");
                $repairedTables++;
                $results[$table]['repaired'] = true;
            }
        } catch (PDOException $e) {
            // Ignorer les erreurs de vérification
        }
    }
    
    // Log de l'action
    logAction('optimize_database', 'maintenance', null, "Optimisation de $optimizedTables tables" . ($repairedTables > 0 ? ", $repairedTables réparées" : ""));
    
    if (empty($errors)) {
        echo json_encode([
            'success' => true, 
            'message' => "$optimizedTables tables optimisées avec succès" . ($repairedTables > 0 ? ", $repairedTables tables réparées" : ""),
            'details' => $results
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "Optimisation partielle: " . implode(', ', $errors),
            'details' => $results
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>