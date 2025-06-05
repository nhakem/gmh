<?php
// statistiques/api.php - API pour les données statistiques (AJAX)
require_once '../config.php';
require_once ROOT_PATH . '/includes/classes/Auth.php';
require_once ROOT_PATH . '/includes/classes/Statistics.php';

header('Content-Type: application/json');

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

if (!hasPermission('administrateur')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permissions insuffisantes']);
    exit;
}

$action = $_GET['action'] ?? '';
$statistics = new Statistics();

try {
    switch ($action) {
        case 'dashboard':
            $data = $statistics->getDashboardData();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'evolution':
            $mois = intval($_GET['mois'] ?? 12);
            $data = $statistics->getEvolutionMensuelle($mois);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'occupation':
            $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
            $dateFin = $_GET['date_fin'] ?? date('Y-m-t');
            $data = $statistics->getTauxOccupation($dateDebut, $dateFin);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'kpi':
            $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
            $dateFin = $_GET['date_fin'] ?? date('Y-m-t');
            $data = $statistics->getKPI($dateDebut, $dateFin);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'custom_report':
            $criteria = [
                'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
                'date_fin' => $_GET['date_fin'] ?? date('Y-m-t'),
                'group_by' => $_GET['group_by'] ?? 'jour',
                'filters' => [
                    'sexe' => $_GET['sexe'] ?? null,
                    'type_hebergement' => $_GET['type_hebergement'] ?? null,
                    'age_min' => $_GET['age_min'] ?? null,
                    'age_max' => $_GET['age_max'] ?? null
                ]
            ];
            $data = $statistics->generateCustomReport($criteria);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'origines':
            $limite = intval($_GET['limite'] ?? 10);
            $mois = intval($_GET['mois'] ?? 3);
            $data = $statistics->getTopOrigines($limite, $mois);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action non reconnue']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erreur API statistiques: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur', 'message' => $e->getMessage()]);
}

// Log de l'accès API
logAction('api_access', 'statistiques', null, "Action: $action");
?>

<?php
// statistiques/templates/rapport_html.php - Template HTML pour rapports imprimables
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport GMH - <?php echo formatDate($dateDebut); ?> au <?php echo formatDate($dateFin); ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .page-break { page-break-before: always; }
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #667eea;
            margin: 0 0 10px 0;
        }
        
        .periode {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-card h3 {
            margin: 0 0 5px 0;
            font-size: 2em;
            color: #667eea;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
        }
        
        .section {
            margin-bottom: 40px;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #667eea;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 0.9em;
            color: #666;
            text-align: center;
        }
        
        .no-data {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SITE_NAME; ?></h1>
        <h2>Rapport d'activité détaillé</h2>
    </div>
    
    <div class="periode">
        <strong>Période d'analyse :</strong> 
        Du <?php echo formatDate($dateDebut); ?> au <?php echo formatDate($dateFin); ?>
        <br>
        <small>Généré le <?php echo formatDate(date('Y-m-d'), 'd/m/Y à H:i'); ?></small>
    </div>
    
    <!-- Statistiques générales -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo number_format($stats['general']['nombre_personnes_total'] ?? 0); ?></h3>
            <p>Personnes hébergées</p>
        </div>
        <div class="stat-card">
            <h3><?php echo number_format($stats['general']['total_nuitees'] ?? 0); ?></h3>
            <p>Nuitées totales</p>
        </div>
        <div class="stat-card">
            <h3><?php echo number_format($stats['general']['chambres_utilisees'] ?? 0); ?></h3>
            <p>Chambres utilisées</p>
        </div>
        <div class="stat-card">
            <h3><?php echo number_format($kpi['duree_moyenne_sejour'] ?? 0, 1); ?></h3>
            <p>Durée moyenne (jours)</p>
        </div>
    </div>
    
    <!-- KPI détaillés -->
    <div class="section">
        <h2>Indicateurs de performance</h2>
        <table>
            <tr>
                <td><strong>Taux d'utilisation des chambres</strong></td>
                <td class="text-right"><?php echo number_format($kpi['taux_utilisation_chambres'] ?? 0, 1); ?>%</td>
            </tr>
            <tr>
                <td><strong>Séjours par personne</strong></td>
                <td class="text-right"><?php echo number_format($kpi['sejours_par_personne'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td><strong>Nuitées par jour</strong></td>
                <td class="text-right"><?php echo number_format($kpi['nuitees_par_jour'] ?? 0, 1); ?></td>
            </tr>
            <tr>
                <td><strong>Durée minimum de séjour</strong></td>
                <td class="text-right"><?php echo number_format($kpi['duree_min_sejour'] ?? 0); ?> jour(s)</td>
            </tr>
            <tr>
                <td><strong>Durée maximum de séjour</strong></td>
                <td class="text-right"><?php echo number_format($kpi['duree_max_sejour'] ?? 0); ?> jour(s)</td>
            </tr>
        </table>
    </div>
    
    <!-- Répartition par sexe -->
    <div class="section">
        <h2>Répartition par sexe</h2>
        <?php if (!empty($stats['sexe'])): ?>
        <table>
            <thead>
                <tr>
                    <th>Sexe</th>
                    <th class="text-center">Nombre de personnes</th>
                    <th class="text-center">Total nuitées</th>
                    <th class="text-center">Pourcentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalPersonnes = array_sum(array_column($stats['sexe'], 'nombre_personnes'));
                foreach ($stats['sexe'] as $stat): 
                    $pourcentage = $totalPersonnes > 0 ? ($stat['nombre_personnes'] / $totalPersonnes) * 100 : 0;
                    $sexeLibelle = $stat['sexe'] === 'M' ? 'Hommes' : ($stat['sexe'] === 'F' ? 'Femmes' : 'Autre');
                ?>
                <tr>
                    <td><?php echo $sexeLibelle; ?></td>
                    <td class="text-center"><?php echo number_format($stat['nombre_personnes']); ?></td>
                    <td class="text-center"><?php echo number_format($stat['total_nuitees']); ?></td>
                    <td class="text-center"><?php echo number_format($pourcentage, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">Aucune donnée disponible pour cette période</div>
        <?php endif; ?>
    </div>
    
    <!-- Types d'hébergement -->
    <div class="section">
        <h2>Utilisation par type d'hébergement</h2>
        <?php if (!empty($stats['hebergement'])): ?>
        <table>
            <thead>
                <tr>
                    <th>Type d'hébergement</th>
                    <th class="text-center">Nombre de personnes</th>
                    <th class="text-center">Total nuitées</th>
                    <th class="text-center">Nuitées par personne</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['hebergement'] as $hebergement): ?>
                <tr>
                    <td><?php echo htmlspecialchars($hebergement['type_hebergement']); ?></td>
                    <td class="text-center"><?php echo number_format($hebergement['nombre_personnes']); ?></td>
                    <td class="text-center"><?php echo number_format($hebergement['total_nuitees']); ?></td>
                    <td class="text-center">
                        <?php 
                        $ratio = $hebergement['nombre_personnes'] > 0 ? 
                                 $hebergement['total_nuitees'] / $hebergement['nombre_personnes'] : 0;
                        echo number_format($ratio, 1);
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">Aucune donnée disponible pour cette période</div>
        <?php endif; ?>
    </div>
    
    <div class="page-break"></div>
    
    <!-- Évolution mensuelle -->
    <div class="section">
        <h2>Évolution sur les 12 derniers mois</h2>
        <?php if (!empty($evolution)): ?>
        <table>
            <thead>
                <tr>
                    <th>Mois</th>
                    <th class="text-center">Personnes</th>
                    <th class="text-center">Nuitées</th>
                    <th class="text-center">Durée moyenne</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evolution as $mois): ?>
                <tr>
                    <td><?php echo htmlspecialchars($mois['mois_libelle']); ?></td>
                    <td class="text-center"><?php echo number_format($mois['personnes']); ?></td>
                    <td class="text-center"><?php echo number_format($mois['nuitees']); ?></td>
                    <td class="text-center"><?php echo number_format($mois['duree_moyenne'] ?? 0, 1); ?> jours</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">Aucune donnée d'évolution disponible</div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>
            Rapport généré automatiquement par le système GMH<br>
            <?php echo SITE_NAME; ?> - 
            Généré par <?php echo htmlspecialchars($_SESSION['user_fullname']); ?> 
            le <?php echo formatDate(date('Y-m-d'), 'd/m/Y à H:i'); ?>
        </p>
    </div>
    
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="
            background: #667eea; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 5px; 
            cursor: pointer;
            font-size: 14px;
        ">
            🖨️ Imprimer
        </button>
        <button onclick="window.close()" style="
            background: #6c757d; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 5px; 
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
        ">
            ✖️ Fermer
        </button>
    </div>
</body>
</html>

<?php
// statistiques/widgets.php - Widgets réutilisables pour les statistiques
function renderStatCard($title, $value, $icon, $color = 'primary', $subtitle = '') {
    $colorClass = "bg-$color";
    return "
    <div class='stat-card'>
        <div class='stat-icon $colorClass text-white'>
            <i class='$icon'></i>
        </div>
        <h3 class='mb-1'>" . number_format($value) . "</h3>
        <p class='text-muted mb-2'>$title</p>
        " . ($subtitle ? "<small class='text-$color'>$subtitle</small>" : "") . "
    </div>";
}

function renderProgressBar($label, $value, $total, $format = 'number') {
    $percentage = $total > 0 ? ($value / $total) * 100 : 0;
    $displayValue = $format === 'percentage' ? number_format($percentage, 1) . '%' : number_format($value);
    
    return "
    <div class='mb-3'>
        <div class='d-flex justify-content-between align-items-center mb-1'>
            <span>$label</span>
            <strong>$displayValue</strong>
        </div>
        <div class='progress'>
            <div class='progress-bar' role='progressbar' style='width: " . min($percentage, 100) . "%'></div>
        </div>
    </div>";
}

function renderSimpleTable($data, $headers, $title = '') {
    $html = '';
    if ($title) {
        $html .= "<h5 class='mb-3'>$title</h5>";
    }
    
    $html .= "<div class='table-responsive'><table class='table table-striped'><thead><tr>";
    foreach ($headers as $header) {
        $html .= "<th>$header</th>";
    }
    $html .= "</tr></thead><tbody>";
    
    foreach ($data as $row) {
        $html .= "<tr>";
        foreach ($row as $cell) {
            $html .= "<td>" . htmlspecialchars($cell) . "</td>";
        }
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table></div>";
    return $html;
}
?>