<?php
// maintenance/cleanup.php - Nettoyage du système
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
    redirect('maintenance/', 'Accès direct non autorisé', 'error');
}

try {
    $db = getDB();
    $cleanedItems = [];
    $totalCleaned = 0;
    
    // Nettoyage des logs anciens
    if (isset($_POST['clean_logs'])) {
        $stmt = $db->prepare("DELETE FROM logs_saisie WHERE date_action < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
        $stmt->execute();
        $deletedLogs = $stmt->rowCount();
        $cleanedItems[] = "$deletedLogs anciens logs supprimés";
        $totalCleaned += $deletedLogs;
    }
    
    // Nettoyage des fichiers temporaires
    if (isset($_POST['clean_temp'])) {
        $tempDir = sys_get_temp_dir();
        $tempFiles = glob($tempDir . '/GMH_*');
        $deletedTempFiles = 0;
        
        foreach ($tempFiles as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) { // Plus d'1 heure
                if (unlink($file)) {
                    $deletedTempFiles++;
                }
            }
        }
        
        // Nettoyage des fichiers tmp dans le répertoire de l'application
        $appTempDir = ROOT_PATH . '/temp/';
        if (is_dir($appTempDir)) {
            $appTempFiles = glob($appTempDir . '*');
            foreach ($appTempFiles as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 86400) { // Plus d'1 jour
                    if (unlink($file)) {
                        $deletedTempFiles++;
                    }
                }
            }
        }
        
        $cleanedItems[] = "$deletedTempFiles fichiers temporaires supprimés";
        $totalCleaned += $deletedTempFiles;
    }
    
    // Nettoyage des uploads orphelins
    if (isset($_POST['clean_uploads'])) {
        $uploadDir = ROOT_PATH . '/uploads/';
        if (is_dir($uploadDir)) {
            $uploadFiles = glob($uploadDir . '*');
            $deletedUploads = 0;
            
            foreach ($uploadFiles as $file) {
                if (is_file($file)) {
                    $filename = basename($file);
                    
                    // Vérifier si le fichier est référencé quelque part
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count FROM (
                            SELECT id FROM personnes WHERE photo = ?
                            UNION ALL
                            SELECT id FROM prescriptions WHERE fichier_ordonnance = ?
                        ) as refs
                    ");
                    $stmt->execute([$filename, $filename]);
                    $isReferenced = $stmt->fetch()['count'] > 0;
                    
                    // Supprimer si pas référencé et ancien (plus d'1 jour)
                    if (!$isReferenced && (time() - filemtime($file)) > 86400) {
                        if (unlink($file)) {
                            $deletedUploads++;
                        }
                    }
                }
            }
            $cleanedItems[] = "$deletedUploads fichiers uploadés orphelins supprimés";
            $totalCleaned += $deletedUploads;
        }
    }
    
    // Nettoyage des sessions expirées
    if (isset($_POST['clean_sessions'])) {
        $sessionPath = session_save_path();
        if (!$sessionPath) {
            $sessionPath = sys_get_temp_dir();
        }
        
        if ($sessionPath && is_dir($sessionPath)) {
            $sessionFiles = glob($sessionPath . '/sess_*');
            $deletedSessions = 0;
            
            foreach ($sessionFiles as $file) {
                if (is_file($file) && (time() - filemtime($file)) > SESSION_LIFETIME) {
                    if (unlink($file)) {
                        $deletedSessions++;
                    }
                }
            }
            $cleanedItems[] = "$deletedSessions sessions expirées supprimées";
            $totalCleaned += $deletedSessions;
        }
    }
    
    // Nettoyage des logs d'erreur anciens
    if (isset($_POST['clean_error_logs'])) {
        $errorLogFiles = [
            ROOT_PATH . '/logs/error.log',
            ROOT_PATH . '/logs/access.log',
            ini_get('error_log')
        ];
        
        $errorLogsProcessed = 0;
        foreach ($errorLogFiles as $logFile) {
            if ($logFile && file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // Plus de 10MB
                // Garder seulement les 1000 dernières lignes
                $lines = file($logFile);
                if (count($lines) > 1000) {
                    $keepLines = array_slice($lines, -1000);
                    file_put_contents($logFile, implode('', $keepLines));
                    $errorLogsProcessed++;
                }
            }
        }
        if ($errorLogsProcessed > 0) {
            $cleanedItems[] = "$errorLogsProcessed fichiers de log tronqués";
        }
    }
    
    // Optimisation après nettoyage
    if ($totalCleaned > 0) {
        try {
            $db->exec("OPTIMIZE TABLE logs_saisie");
        } catch (PDOException $e) {
            // Ignorer les erreurs d'optimisation
        }
    }
    
    // Log de l'action
    $cleanupSummary = implode(', ', $cleanedItems);
    logAction('system_cleanup', 'maintenance', null, $cleanupSummary ?: 'Aucun élément à nettoyer');
    
    if (!empty($cleanedItems)) {
        redirect('maintenance/', "Nettoyage terminé: " . $cleanupSummary, 'success');
    } else {
        redirect('maintenance/', "Aucun élément à nettoyer", 'info');
    }
    
} catch (Exception $e) {
    redirect('maintenance/', 'Erreur lors du nettoyage: ' . $e->getMessage(), 'error');
}
?>