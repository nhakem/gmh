<?php
// test_db.php - Script de test pour vérifier la connexion à la base de données

// Configuration de la base de données (identique à config.php)
$db_config = [
    'host' => 'localhost',
    'name' => 'exalink_GMH',
    'user' => 'exalink_gmhuser',
    'pass' => 'Lapiaule2025',
    'charset' => 'utf8mb4'
];

// Résultats des tests
$results = [];

// Test 1: Connexion à MySQL
try {
    $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $results['mysql_connection'] = ['success' => true, 'message' => 'Connexion à MySQL réussie'];
} catch (PDOException $e) {
    $results['mysql_connection'] = ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    $pdo = null;
}

// Test 2: Accès à la base de données
if ($pdo) {
    try {
        $pdo->exec("USE {$db_config['name']}");
        $results['database_access'] = ['success' => true, 'message' => "Base de données '{$db_config['name']}' accessible"];
    } catch (PDOException $e) {
        $results['database_access'] = ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

// Test 3: Vérification des tables
$expected_tables = [
    'utilisateurs',
    'personnes',
    'types_hebergement',
    'chambres',
    'nuitees',
    'types_repas',
    'repas',
    'medicaments',
    'prescriptions',
    'logs_saisie'
];

if ($pdo && $results['database_access']['success']) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missing_tables = array_diff($expected_tables, $existing_tables);
        
        if (empty($missing_tables)) {
            $results['tables_check'] = ['success' => true, 'message' => 'Toutes les tables requises sont présentes'];
        } else {
            $results['tables_check'] = ['success' => false, 'message' => 'Tables manquantes: ' . implode(', ', $missing_tables)];
        }
        
        $results['tables_list'] = $existing_tables;
    } catch (PDOException $e) {
        $results['tables_check'] = ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

// Test 4: Vérification de l'utilisateur admin
if ($pdo && $results['database_access']['success']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur = 'admin'");
        $admin_exists = $stmt->fetchColumn() > 0;
        
        if ($admin_exists) {
            $results['admin_user'] = ['success' => true, 'message' => "L'utilisateur admin existe"];
        } else {
            $results['admin_user'] = ['success' => false, 'message' => "L'utilisateur admin n'existe pas"];
        }
    } catch (PDOException $e) {
        $results['admin_user'] = ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

// Test 5: Test d'insertion et de suppression
if ($pdo && $results['database_access']['success'] && $results['tables_check']['success']) {
    try {
        // Insertion test
        $test_name = 'Test_' . time();
        $stmt = $pdo->prepare("INSERT INTO personnes (nom, prenom, sexe, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$test_name, 'Test', 'M', 'client']);
        $test_id = $pdo->lastInsertId();
        
        // Suppression test
        $stmt = $pdo->prepare("DELETE FROM personnes WHERE id = ?");
        $stmt->execute([$test_id]);
        
        $results['write_test'] = ['success' => true, 'message' => 'Test d\'écriture/suppression réussi'];
    } catch (PDOException $e) {
        $results['write_test'] = ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Base de Données - GMH</title>
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
        .test-result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .config-info {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table-list {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .table-list ul {
            list-style-type: none;
            padding-left: 20px;
        }
        .table-list li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Test de connexion à la base de données - GMH</h1>
        
        <div class="config-info">
            <h3>Configuration utilisée:</h3>
            <ul>
                <li><strong>Hôte:</strong> <code><?php echo htmlspecialchars($db_config['host']); ?></code></li>
                <li><strong>Base de données:</strong> <code><?php echo htmlspecialchars($db_config['name']); ?></code></li>
                <li><strong>Utilisateur:</strong> <code><?php echo htmlspecialchars($db_config['user']); ?></code></li>
                <li><strong>Charset:</strong> <code><?php echo htmlspecialchars($db_config['charset']); ?></code></li>
            </ul>
        </div>
        
        <?php foreach ($results as $test => $result): ?>
            <?php if ($test !== 'tables_list'): ?>
            <div class="test-result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                <h3>
                    <?php 
                    $icons = [
                        'mysql_connection' => '🔌',
                        'database_access' => '🗃️',
                        'tables_check' => '📊',
                        'admin_user' => '👤',
                        'write_test' => '✍️'
                    ];
                    $titles = [
                        'mysql_connection' => 'Connexion MySQL',
                        'database_access' => 'Accès à la base de données',
                        'tables_check' => 'Vérification des tables',
                        'admin_user' => 'Utilisateur administrateur',
                        'write_test' => 'Test d\'écriture'
                    ];
                    echo $icons[$test] ?? '📌';
                    echo ' ';
                    echo $titles[$test] ?? $test;
                    ?>
                </h3>
                <p>
                    <?php echo $result['success'] ? '✓' : '✗'; ?>
                    <?php echo htmlspecialchars($result['message']); ?>
                </p>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if (isset($results['tables_list']) && !empty($results['tables_list'])): ?>
        <div class="table-list">
            <h3>📋 Tables trouvées dans la base de données:</h3>
            <ul>
                <?php foreach ($results['tables_list'] as $table): ?>
                    <li><?php echo htmlspecialchars($table); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="warning">
            <h3>⚠️ Sécurité importante:</h3>
            <ul>
                <li><strong>Supprimez ce fichier immédiatement après les tests!</strong></li>
                <li>Ce fichier contient des informations sensibles sur votre base de données</li>
                <li>Ne le laissez jamais sur un serveur en production</li>
            </ul>
        </div>
        
        <?php
        $all_success = true;
        foreach ($results as $test => $result) {
            if ($test !== 'tables_list' && !$result['success']) {
                $all_success = false;
                break;
            }
        }
        ?>
        
        <div class="test-result <?php echo $all_success ? 'success' : 'error'; ?>" style="margin-top: 30px;">
            <h2>
                <?php echo $all_success ? '✅' : '❌'; ?>
                Résultat global
            </h2>
            <p>
                <?php if ($all_success): ?>
                    <strong>Tous les tests sont réussis!</strong> La base de données est correctement configurée et prête pour GMH.
                <?php else: ?>
                    <strong>Certains tests ont échoué.</strong> Veuillez vérifier:
                    <ul>
                        <li>Que la base de données <code>exalink_GMH</code> existe</li>
                        <li>Que l'utilisateur <code>exalink_gmhuser</code> a les bonnes permissions</li>
                        <li>Que vous avez importé le script SQL fourni</li>
                        <li>Que les informations de connexion sont correctes</li>
                    </ul>
                <?php endif; ?>
            </p>
        </div>
    </div>
</body>
</html>