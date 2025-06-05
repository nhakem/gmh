<?php
// test_direct.php - Test sans redirection
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration minimale SANS les redirections
define('DB_HOST', 'localhost');
define('DB_NAME', 'exalink_GMH');
define('DB_USER', 'exalink_gmhuser');
define('DB_PASS', 'Lapiaule2025');

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                  DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Connexion DB réussie!</h2>";
    
    // Créer un mot de passe simple
    $password = 'test123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Mettre à jour
    $sql = "UPDATE utilisateurs SET mot_de_passe = ? WHERE nom_utilisateur = 'admin'";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$hash]);
    
    if ($result) {
        echo "<h3 style='color: green;'>✅ Mot de passe mis à jour!</h3>";
        echo "Utilisateur: <strong>admin</strong><br>";
        echo "Mot de passe: <strong>test123</strong><br><br>";
        echo "<a href='login.php'>Essayer de se connecter</a>";
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>