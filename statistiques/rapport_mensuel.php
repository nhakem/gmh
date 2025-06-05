<?php
// statistiques/rapport_mensuel.php - Rapport statistique mensuel style tableau
require_once '../config.php';
require_once ROOT_PATH . '/includes/classes/Auth.php';
require_once ROOT_PATH . '/includes/classes/Statistics.php';

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

if (!hasPermission('administrateur')) {
    redirect('dashboard/', MSG_PERMISSION_DENIED, 'error');
}

$currentPage = 'statistiques';
$pageTitle = 'Rapport Mensuel - ' . SITE_NAME;

// Paramètres
$annee = $_GET['annee'] ?? date('Y');
$moisCourant = date('n');

try {
    $db = getDB();
    
    // Récupération des données pour chaque mois de l'année
    $statistiquesMensuelles = [];
    
    for ($mois = 1; $mois <= 12; $mois++) {
        $dateDebut = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
        $dateFin = date('Y-m-t', strtotime($dateDebut));
        
        // Statistiques générales du mois
        $stmt = $db->prepare("
            SELECT 
                -- Hébergement
                COUNT(DISTINCT CASE WHEN n.date_debut BETWEEN ? AND ? THEN n.personne_id END) as nouvelles_personnes,
                COUNT(DISTINCT CASE WHEN n.date_debut <= ? AND (n.date_fin IS NULL OR n.date_fin >= ?) THEN n.personne_id END) as personnes_presentes,
                SUM(CASE WHEN n.date_debut <= ? AND (n.date_fin IS NULL OR n.date_fin >= ?) 
                    THEN DATEDIFF(LEAST(IFNULL(n.date_fin, ?), ?), GREATEST(n.date_debut, ?)) + 1 
                    ELSE 0 END) as total_nuitees,
                
                -- Repas
                (SELECT COUNT(*) FROM repas r JOIN types_repas tr ON r.type_repas_id = tr.id 
                 WHERE r.date_repas BETWEEN ? AND ? AND tr.nom = 'Petit-déjeuner') as petits_dejeuners,
                (SELECT COUNT(*) FROM repas r JOIN types_repas tr ON r.type_repas_id = tr.id 
                 WHERE r.date_repas BETWEEN ? AND ? AND tr.nom = 'Dîner') as diners,
                (SELECT COUNT(*) FROM repas r JOIN types_repas tr ON r.type_repas_id = tr.id 
                 WHERE r.date_repas BETWEEN ? AND ? AND tr.nom = 'Souper') as soupers,
                (SELECT COUNT(*) FROM repas r JOIN types_repas tr ON r.type_repas_id = tr.id 
                 WHERE r.date_repas BETWEEN ? AND ? AND tr.nom = 'Collation') as collations
                 
            FROM nuitees n
            WHERE n.actif = 1
        ");
        
        $stmt->execute([
            $dateDebut, $dateFin,  // nouvelles_personnes
            $dateFin, $dateDebut,  // personnes_presentes  
            $dateFin, $dateDebut, $dateFin, $dateFin, $dateDebut,  // total_nuitees
            $dateDebut, $dateFin,  // petits_dejeuners
            $dateDebut, $dateFin,  // diners
            $dateDebut, $dateFin,  // soupers
            $dateDebut, $dateFin   // collations
        ]);
        
        $stats = $stmt->fetch();
        
        // Répartition par sexe pour le mois
        $sexeStmt = $db->prepare("
            SELECT 
                p.sexe,
                COUNT(DISTINCT n.personne_id) as nombre
            FROM nuitees n
            JOIN personnes p ON n.personne_id = p.id
            WHERE n.actif = 1 
                AND n.date_debut <= ? 
                AND (n.date_fin IS NULL OR n.date_fin >= ?)
            GROUP BY p.sexe
        ");
        $sexeStmt->execute([$dateFin, $dateDebut]);
        $repartitionSexe = $sexeStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Répartition par type d'hébergement
        $hebergementStmt = $db->prepare("
            SELECT 
                th.nom,
                COUNT(DISTINCT n.personne_id) as nombre
            FROM nuitees n
            JOIN chambres c ON n.chambre_id = c.id
            JOIN types_hebergement th ON c.type_hebergement_id = th.id
            WHERE n.actif = 1 
                AND n.date_debut <= ? 
                AND (n.date_fin IS NULL OR n.date_fin >= ?)
            GROUP BY th.nom
        ");
        $hebergementStmt->execute([$dateFin, $dateDebut]);
        $repartitionHebergement = $hebergementStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Répartition par tranches d'âge
        $ageStmt = $db->prepare("
            SELECT 
                CASE 
                    WHEN p.age IS NULL THEN 'Inconnu'
                    WHEN p.age < 18 THEN '0-17'
                    WHEN p.age BETWEEN 18 AND 25 THEN '18-25'
                    WHEN p.age BETWEEN 26 AND 35 THEN '26-35'
                    WHEN p.age BETWEEN 36 AND 50 THEN '36-50'
                    WHEN p.age BETWEEN 51 AND 65 THEN '51-65'
                    WHEN p.age > 65 THEN '65+'
                    ELSE 'Inconnu'
                END as tranche_age,
                COUNT(DISTINCT n.personne_id) as nombre
            FROM nuitees n
            JOIN personnes p ON n.personne_id = p.id
            WHERE n.actif = 1 
                AND n.date_debut <= ? 
                AND (n.date_fin IS NULL OR n.date_fin >= ?)
            GROUP BY tranche_age
        ");
        $ageStmt->execute([$dateFin, $dateDebut]);
        $repartitionAge = $ageStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $statistiquesMensuelles[$mois] = [
            'stats' => $stats,
            'sexe' => $repartitionSexe,
            'hebergement' => $repartitionHebergement,
            'age' => $repartitionAge
        ];
    }
    
    // Calcul des totaux annuels
    $totauxAnnuels = [
        'nouvelles_personnes' => 0,
        'total_nuitees' => 0,
        'petits_dejeuners' => 0,
        'diners' => 0,
        'soupers' => 0,
        'collations' => 0
    ];
    
    foreach ($statistiquesMensuelles as $mois => $data) {
        if ($mois <= $moisCourant) { // Seulement les mois écoulés
            foreach ($totauxAnnuels as $key => &$total) {
                $total += $data['stats'][$key] ?? 0;
            }
        }
    }

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
    error_log($error);
}

include ROOT_PATH . '/includes/header.php';
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { font-size: 12px; }
    .table th, .table td { padding: 4px 6px; font-size: 11px; }
}

.rapport-tableau {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table-statistiques {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
}

.table-statistiques th,
.table-statistiques td {
    border: 1px solid #dee2e6;
    padding: 8px 12px;
    text-align: center;
    vertical-align: middle;
}

.table-statistiques thead th {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #495057;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-statistiques .section-header {
    background-color: #e9ecef;
    font-weight: bold;
    text-align: left;
    padding-left: 20px;
}

.table-statistiques .sous-total {
    background-color: #f8f9fa;
    font-weight: bold;
}

.table-statistiques .total-annuel {
    background-color: #667eea;
    color: white;
    font-weight: bold;
}

.mois-passe {
    background-color: #f8f9fa;
}

.mois-courant {
    background-color: #e3f2fd;
}

.mois-futur {
    background-color: #fff3e0;
    color: #999;
}

.en-tete-rapport {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #667eea;
}

.legend {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
    font-size: 0.9em;
}

.legend-item {
    display: inline-block;
    margin-right: 20px;
    margin-bottom: 5px;
}

.legend-color {
    display: inline-block;
    width: 15px;
    height: 15px;
    margin-right: 5px;
    vertical-align: middle;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h1 class="h3 mb-0">
        <i class="fas fa-table me-2"></i>
        Rapport Statistique Mensuel
    </h1>
    <div class="btn-group">
        <select class="form-select" onchange="window.location.href='?annee='+this.value" style="width: auto;">
            <?php for ($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                <option value="<?php echo $a; ?>" <?php echo $a == $annee ? 'selected' : ''; ?>>
                    <?php echo $a; ?>
                </option>
            <?php endfor; ?>
        </select>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print me-1"></i>
            Imprimer
        </button>
        <a href="export.php?type=mensuel&annee=<?php echo $annee; ?>" class="btn btn-success">
            <i class="fas fa-download me-1"></i>
            Exporter
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

<div class="rapport-tableau">
    <div class="en-tete-rapport">
        <h2><?php echo SITE_NAME; ?></h2>
        <h3>Statistiques de fréquentation - Année <?php echo $annee; ?></h3>
        <p class="mb-0">Rapport généré le <?php echo formatDate(date('Y-m-d'), 'd/m/Y à H:i'); ?></p>
    </div>

    <div class="table-responsive">
        <table class="table-statistiques">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align: middle; width: 200px;">Indicateurs</th>
                    <th colspan="12">Mois de l'année <?php echo $annee; ?></th>
                    <th rowspan="2" style="vertical-align: middle;">Total</th>
                </tr>
                <tr>
                    <?php 
                    $moisNoms = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                    foreach ($moisNoms as $moisNom): 
                    ?>
                        <th><?php echo $moisNom; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- SECTION HÉBERGEMENT -->
                <tr class="section-header">
                    <td colspan="14"><strong>HÉBERGEMENT</strong></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Nouvelles personnes</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['stats']['nouvelles_personnes'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel"><?php echo number_format($totauxAnnuels['nouvelles_personnes']); ?></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Total nuitées</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['stats']['total_nuitees'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel"><?php echo number_format($totauxAnnuels['total_nuitees']); ?></td>
                </tr>
                
                <!-- SECTION REPAS -->
                <tr class="section-header">
                    <td colspan="14"><strong>RESTAURATION</strong></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Petits-déjeuners</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['stats']['petits_dejeuners'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel"><?php echo number_format($totauxAnnuels['petits_dejeuners']); ?></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Dîners</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['stats']['diners'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel"><?php echo number_format($totauxAnnuels['diners']); ?></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Soupers</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['stats']['soupers'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel"><?php echo number_format($totauxAnnuels['soupers']); ?></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Collations</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['stats']['collations'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel"><?php echo number_format($totauxAnnuels['collations']); ?></td>
                </tr>
                
                <!-- SECTION RÉPARTITION PAR SEXE -->
                <tr class="section-header">
                    <td colspan="14"><strong>RÉPARTITION PAR SEXE</strong></td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Hommes</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['sexe']['M'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel">
                        <?php 
                        $totalHommes = 0;
                        for ($m = 1; $m <= $moisCourant; $m++) {
                            $totalHommes += $statistiquesMensuelles[$m]['sexe']['M'] ?? 0;
                        }
                        echo number_format($totalHommes);
                        ?>
                    </td>
                </tr>
                
                <tr>
                    <td class="text-start"><strong>Femmes</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['sexe']['F'] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel">
                        <?php 
                        $totalFemmes = 0;
                        for ($m = 1; $m <= $moisCourant; $m++) {
                            $totalFemmes += $statistiquesMensuelles[$m]['sexe']['F'] ?? 0;
                        }
                        echo number_format($totalFemmes);
                        ?>
                    </td>
                </tr>
                
                <!-- SECTION TYPES D'HÉBERGEMENT -->
                <tr class="section-header">
                    <td colspan="14"><strong>TYPES D'HÉBERGEMENT</strong></td>
                </tr>
                
                <?php 
                // Récupérer tous les types d'hébergement
                $typesHebergement = $db->query("SELECT nom FROM types_hebergement ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($typesHebergement as $type):
                ?>
                <tr>
                    <td class="text-start"><strong><?php echo htmlspecialchars($type); ?></strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['hebergement'][$type] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel">
                        <?php 
                        $totalType = 0;
                        for ($m = 1; $m <= $moisCourant; $m++) {
                            $totalType += $statistiquesMensuelles[$m]['hebergement'][$type] ?? 0;
                        }
                        echo number_format($totalType);
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- SECTION TRANCHES D'ÂGE -->
                <tr class="section-header">
                    <td colspan="14"><strong>TRANCHES D'ÂGE</strong></td>
                </tr>
                
                <?php 
                $tranchesAge = ['0-17', '18-25', '26-35', '36-50', '51-65', '65+', 'Inconnu'];
                
                foreach ($tranchesAge as $tranche):
                ?>
                <tr>
                    <td class="text-start"><strong><?php echo $tranche; ?> ans</strong></td>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $valeur = $statistiquesMensuelles[$m]['age'][$tranche] ?? 0;
                        $classMois = $m < $moisCourant ? 'mois-passe' : ($m == $moisCourant ? 'mois-courant' : 'mois-futur');
                    ?>
                        <td class="<?php echo $classMois; ?>">
                            <?php echo $m <= $moisCourant ? number_format($valeur) : '-'; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="total-annuel">
                        <?php 
                        $totalTranche = 0;
                        for ($m = 1; $m <= $moisCourant; $m++) {
                            $totalTranche += $statistiquesMensuelles[$m]['age'][$tranche] ?? 0;
                        }
                        echo number_format($totalTranche);
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="legend">
        <h6><strong>Légende :</strong></h6>
        <div class="legend-item">
            <span class="legend-color mois-passe"></span>
            Mois écoulés (données réelles)
        </div>
        <div class="legend-item">
            <span class="legend-color mois-courant"></span>
            Mois en cours
        </div>
        <div class="legend-item">
            <span class="legend-color mois-futur"></span>
            Mois à venir (pas de données)
        </div>
    </div>
</div>

<?php endif; ?>

<?php
include ROOT_PATH . '/includes/footer.php';
?>