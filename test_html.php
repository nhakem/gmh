<?php
// test_html.php - Script de test pour vérifier que PHP et HTML fonctionnent correctement

// Information PHP
$php_version = phpversion();
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Non disponible';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'Non disponible';
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? 'Non disponible';

// Test des extensions PHP requises
$extensions_requises = [
    'PDO' => extension_loaded('PDO'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'session' => extension_loaded('session'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl')
];

// Test des fonctions importantes
$fonctions_requises = [
    'password_hash' => function_exists('password_hash'),
    'password_verify' => function_exists('password_verify'),
    'session_start' => function_exists('session_start'),
    'file_get_contents' => function_exists('file_get_contents')
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test du serveur - GMH</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #667eea;
            color: white;
        }
        .icon {
            font-size: 20px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test du serveur Web - GMH</h1>
        
        <div class="test-section">
            <h2>📊 Informations du serveur</h2>
            <table>
                <tr>
                    <th>Paramètre</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>Version PHP</td>
                    <td class="<?php echo version_compare($php_version, '8.0.0', '>=') ? 'success' : 'error'; ?>">
                        <?php echo $php_version; ?>
                        <?php echo version_compare($php_version, '8.0.0', '>=') ? '✓' : '✗ (PHP 8.0+ requis)'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Serveur Web</td>
                    <td><?php echo htmlspecialchars($server_software); ?></td>
                </tr>
                <tr>
                    <td>Document Root</td>
                    <td><?php echo htmlspecialchars($document_root); ?></td>
                </tr>
                <tr>
                    <td>Chemin du script</td>
                    <td><?php echo htmlspecialchars($script_filename); ?></td>
                </tr>
                <tr>
                    <td>Date/Heure serveur</td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="test-section">
            <h2>🔌 Extensions PHP requises</h2>
            <table>
                <tr>
                    <th>Extension</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($extensions_requises as $ext => $loaded): ?>
                <tr>
                    <td><?php echo $ext; ?></td>
                    <td class="<?php echo $loaded ? 'success' : 'error'; ?>">
                        <?php echo $loaded ? '✓ Installée' : '✗ Manquante'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="test-section">
            <h2>⚙️ Fonctions PHP requises</h2>
            <table>
                <tr>
                    <th>Fonction</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($fonctions_requises as $func => $exists): ?>
                <tr>
                    <td><?php echo $func; ?></td>
                    <td class="<?php echo $exists ? 'success' : 'error'; ?>">
                        <?php echo $exists ? '✓ Disponible' : '✗ Non disponible'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="test-section">
            <h2>📁 Test d'écriture des dossiers</h2>
            <?php
            $dossiers_test = ['logs', 'uploads'];
            foreach ($dossiers_test as $dossier) {
                $chemin = __DIR__ . '/' . $dossier;
                $existe = file_exists($chemin);
                $writable = $existe && is_writable($chemin);
                
                echo "<p>";
                echo "<strong>Dossier '$dossier':</strong> ";
                if (!$existe) {
                    echo "<span class='error'>✗ N'existe pas</span>";
                } elseif (!$writable) {
                    echo "<span class='error'>✗ Pas d'accès en écriture</span>";
                } else {
                    echo "<span class='success'>✓ OK (accessible en écriture)</span>";
                }
                echo "</p>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>🌐 Test JavaScript et CSS</h2>
            <p>Si vous voyez cette page avec un style correct et que le bouton ci-dessous fonctionne, HTML/CSS/JS fonctionnent correctement.</p>
            <button onclick="alert('✓ JavaScript fonctionne correctement!');" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Tester JavaScript
            </button>
        </div>
        
        <div class="test-section" style="background-color: #e8f4f8;">
            <h2>📋 Résumé</h2>
            <?php
            $all_extensions_ok = !in_array(false, $extensions_requises);
            $all_functions_ok = !in_array(false, $fonctions_requises);
            $php_version_ok = version_compare($php_version, '8.0.0', '>=');
            
            if ($all_extensions_ok && $all_functions_ok && $php_version_ok) {
                echo "<p class='success'>✓ Tous les tests sont passés avec succès! Le serveur est prêt pour GMH.</p>";
            } else {
                echo "<p class='error'>✗ Certains tests ont échoué. Veuillez corriger les problèmes ci-dessus.</p>";
            }
            ?>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <p class="info">
                ⚠️ <strong>Important:</strong> Supprimez ce fichier après les tests pour des raisons de sécurité!
            </p>
        </div>
    </div>
</body>
</html>