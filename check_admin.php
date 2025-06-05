<?php
require_once 'config.php';

$db = getDB();
$sql = "SELECT * FROM utilisateurs WHERE nom_utilisateur = 'admin'";
$stmt = $db->query($sql);
$user = $stmt->fetch();

if ($user) {
    echo "Utilisateur admin trouvé!<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Nom d'utilisateur: " . $user['nom_utilisateur'] . "<br>";
    echo "Actif: " . ($user['actif'] ? 'Oui' : 'Non') . "<br>";
    
    // Test du mot de passe
    $test_password = 'Admin#2025';
    if (password_verify($test_password, $user['mot_de_passe'])) {
        echo "<br><strong>Le mot de passe Admin#2025 est correct!</strong>";
    } else {
        echo "<br><strong>Le mot de passe ne correspond pas!</strong>";
        echo "<br>Il faut réinitialiser le mot de passe.";
    }
} else {
    echo "Aucun utilisateur admin trouvé!";
}
?>