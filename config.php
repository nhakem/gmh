<?php
// config.php - Configuration centrale du projet GMH

// Configuration de l'environnement
define('ENV', 'development'); // development ou production

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'exalink_GMH');
define('DB_USER', 'exalink_gmhuser');
define('DB_PASS', 'Lapiaule2025');
define('DB_CHARSET', 'utf8mb4');

// Configuration des chemins
define('ROOT_PATH', dirname(__FILE__));
define('BASE_URL', '/');  // Changé de '/GMH/' à '/' pour le sous-domaine
define('SITE_NAME', 'GMH - Gestion Humanitaire');

// Configuration de sécurité
define('SESSION_NAME', 'GMH_SESSION');
define('SESSION_LIFETIME', 3600); // 1 heure
define('BCRYPT_COST', 12);

// Configuration des logs
define('LOG_PATH', ROOT_PATH . '/logs/');
define('LOG_ERRORS', true);
define('LOG_ACCESS', true);

// Configuration des uploads
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Messages d'erreur
define('MSG_LOGIN_REQUIRED', 'Vous devez être connecté pour accéder à cette page.');
define('MSG_PERMISSION_DENIED', 'Vous n\'avez pas les permissions nécessaires.');
define('MSG_ERROR_GENERIC', 'Une erreur est survenue. Veuillez réessayer.');

// Timezone
date_default_timezone_set('America/Montreal');

// Error reporting
if (ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Autoload des classes
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/includes/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Démarrage de la session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', SESSION_NAME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Fonction de connexion à la base de données
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (ENV === 'development') {
                die("Erreur de connexion : " . $e->getMessage());
            } else {
                die("Erreur de connexion à la base de données.");
            }
        }
    }
    
    return $db;
}

// Fonction de journalisation
function logAction($action, $table = null, $recordId = null, $details = null) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $db = getDB();
        $sql = "INSERT INTO logs_saisie (utilisateur_id, action, table_concernee, id_enregistrement, details, ip_address) 
                VALUES (:user_id, :action, :table, :record_id, :details, :ip)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action,
            ':table' => $table,
            ':record_id' => $recordId,
            ':details' => $details,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur de journalisation : " . $e->getMessage());
        return false;
    }
}

// Fonction de validation des données
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Fonction de redirection
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: " . BASE_URL . $url);
    exit();
}

// Fonction pour afficher les messages flash
function displayFlash() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error', 'danger' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

// Fonction de vérification des permissions
function hasPermission($requiredRole = null) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if ($requiredRole === null) {
        return true;
    }
    
    return $_SESSION['user_role'] === $requiredRole || $_SESSION['user_role'] === 'administrateur';
}

// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

// Fonction pour calculer l'âge
function calculateAge($dateNaissance) {
    if (empty($dateNaissance)) {
        return null;
    }
    $dateNaissance = new DateTime($dateNaissance);
    $today = new DateTime();
    $age = $today->diff($dateNaissance)->y;
    return $age;
}