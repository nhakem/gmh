<?php
// maintenance/backup.php - Création de sauvegarde
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
    $backupType = $_POST['backup_type'] ?? 'full';
    $compress = isset($_POST['compress']);
    
    $backupDir = ROOT_PATH . '/backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "GMH_backup_{$backupType}_{$timestamp}.sql";
    $filepath = $backupDir . $filename;
    
    // Commande mysqldump
    $command = "mysqldump";
    $command .= " --host=" . escapeshellarg(DB_HOST);
    $command .= " --user=" . escapeshellarg(DB_USER);
    $command .= " --password=" . escapeshellarg(DB_PASS);
    
    switch ($backupType) {
        case 'structure':
            $command .= " --no-data";
            break;
        case 'data':
            $command .= " --no-create-info";
            break;
        case 'full':
        default:
            // Sauvegarde complète (par défaut)
            break;
    }
    
    $command .= " " . escapeshellarg(DB_NAME);
    $command .= " > " . escapeshellarg($filepath);
    
    // Exécution de la sauvegarde
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Erreur lors de la création de la sauvegarde (code: $returnCode)");
    }
    
    // Compression si demandée
    if ($compress && file_exists($filepath)) {
        $zipFilename = $backupDir . "GMH_backup_{$backupType}_{$timestamp}.zip";
        $zip = new ZipArchive();
        
        if ($zip->open($zipFilename, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filepath, $filename);
            $zip->close();
            
            // Suppression du fichier SQL non compressé
            unlink($filepath);
            $finalFile = $zipFilename;
        } else {
            $finalFile = $filepath;
        }
    } else {
        $finalFile = $filepath;
    }
    
    // Vérification que le fichier existe et n'est pas vide
    if (!file_exists($finalFile) || filesize($finalFile) === 0) {
        throw new Exception("La sauvegarde n'a pas pu être créée correctement");
    }
    
    $fileSize = formatBytes(filesize($finalFile));
    
    // Log de l'action
    logAction('create_backup', 'backup', null, "Sauvegarde $backupType créée: " . basename($finalFile) . " ($fileSize)");
    
    redirect('maintenance/', "Sauvegarde créée avec succès: " . basename($finalFile) . " ($fileSize)", 'success');
    
} catch (Exception $e) {
    redirect('maintenance/', 'Erreur lors de la sauvegarde: ' . $e->getMessage(), 'error');
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>