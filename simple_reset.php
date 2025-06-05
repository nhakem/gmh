<?php
require_once 'config.php';

$db = getDB();

// Mot de passe simple pour test
$simple_password = 'Admin2025';
$password_hash = password_hash($simple_password, PASSWORD_BCRYPT);

$sql = "UPDATE utilisateurs SET mot_de_passe = :password WHERE nom_utilisateur = 'admin'";
$stmt = $db->prepare($sql);
$result = $stmt->execute([':password' => $password_hash]);

if ($result) {
    echo "Mot de passe réinitialisé!<br>";
    echo "Utilisateur: admin<br>";
    echo "Nouveau mot de passe: Admin2025<br>";
    echo "<br><strong>IMPORTANT: Changez ce mot de passe après connexion!</strong>";
} else {
    echo "Erreur lors de la réinitialisation.";
}
?>