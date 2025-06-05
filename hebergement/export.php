<?php
// hebergement/export.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$db = getDB();

// Paramètres
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$typeHebergementId = $_GET['type_hebergement'] ?? '';
$statut = $_GET['statut'] ?? '';

// Récupérer les données
$sql = "SELECT 
    n.date_debut,
    n.date_fin,
    p.nom,
    p.prenom,
    p.sexe,
    p.age,
    p.origine,
    c.numero as chambre,
    th.nom as type_hebergement,
    DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 as duree_jours,
    CASE WHEN n.actif = TRUE THEN 'En cours' ELSE 'Terminé' END as statut,
    u.nom_complet as saisi_par,
    n.date_saisie
FROM nuitees n
JOIN personnes p ON n.personne_id = p.id
JOIN chambres c ON n.chambre_id = c.id
JOIN types_hebergement th ON c.type_hebergement_id = th.id
JOIN utilisateurs u ON n.saisi_par = u.id
WHERE 1=1";

$params = [];

if ($dateDebut && $dateFin) {
    $sql .= " AND ((n.date_debut BETWEEN :date_debut AND :date_fin) 
              OR (n.date_fin BETWEEN :date_debut2 AND :date_fin2)
              OR (n.date_debut <= :date_debut3 AND (n.date_fin IS NULL OR n.date_fin >= :date_fin3)))";
    $params[':date_debut'] = $dateDebut;
    $params[':date_fin'] = $dateFin;
    $params[':date_debut2'] = $dateDebut;
    $params[':date_fin2'] = $dateFin;
    $params[':date_debut3'] = $dateDebut;
    $params[':date_fin3'] = $dateFin;
}

if ($typeHebergementId) {
    $sql .= " AND th.id = :type_hebergement_id";
    $params[':type_hebergement_id'] = $typeHebergementId;
}

if ($statut === 'actif') {
    $sql .= " AND n.actif = TRUE";
} elseif ($statut === 'termine') {
    $sql .= " AND n.actif = FALSE";
}

$sql .= " ORDER BY n.date_debut DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$hebergements = $stmt->fetchAll();

// Statistiques par type d'hébergement
$statsSql = "SELECT 
    th.nom as type_hebergement,
    COUNT(DISTINCT n.personne_id) as personnes_uniques,
    COUNT(*) as nombre_sejours,
    SUM(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as total_nuitees,
    AVG(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_moyenne
FROM nuitees n
JOIN chambres c ON n.chambre_id = c.id
JOIN types_hebergement th ON c.type_hebergement_id = th.id
WHERE 1=1";

// Appliquer les mêmes filtres
if ($dateDebut && $dateFin) {
    $statsSql .= " AND ((n.date_debut BETWEEN :date_debut AND :date_fin) 
                   OR (n.date_fin BETWEEN :date_debut2 AND :date_fin2)
                   OR (n.date_debut <= :date_debut3 AND (n.date_fin IS NULL OR n.date_fin >= :date_fin3)))";
}
if ($statut === 'actif') {
    $statsSql .= " AND n.actif = TRUE";
} elseif ($statut === 'termine') {
    $statsSql .= " AND n.actif = FALSE";
}

$statsSql .= " GROUP BY th.id, th.nom ORDER BY th.nom";

$statsStmt = $db->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetchAll();

// Créer le fichier CSV
$filename = sprintf("hebergements_%s_%s.csv", 
    str_replace('-', '', $dateDebut), 
    str_replace('-', '', $dateFin)
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// UTF-8 BOM pour Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Titre et période
fputcsv($output, ['RAPPORT DES HÉBERGEMENTS - GMH'], ';');
fputcsv($output, ['Période: du ' . formatDate($dateDebut, 'd/m/Y') . ' au ' . formatDate($dateFin, 'd/m/Y')], ';');
fputcsv($output, [], ';');

// Statistiques par type
fputcsv($output, ['STATISTIQUES PAR TYPE D\'HÉBERGEMENT'], ';');
fputcsv($output, ['Type', 'Personnes uniques', 'Nombre de séjours', 'Total nuitées', 'Durée moyenne (jours)'], ';');

$totalPersonnes = 0;
$totalSejours = 0;
$totalNuitees = 0;

foreach ($stats as $stat) {
    fputcsv($output, [
        $stat['type_hebergement'],
        $stat['personnes_uniques'],
        $stat['nombre_sejours'],
        $stat['total_nuitees'],
        number_format($stat['duree_moyenne'], 1, ',', ' ')
    ], ';');
    
    $totalPersonnes += $stat['personnes_uniques'];
    $totalSejours += $stat['nombre_sejours'];
    $totalNuitees += $stat['total_nuitees'];
}

// Totaux
fputcsv($output, [
    'TOTAL',
    $totalPersonnes,
    $totalSejours,
    $totalNuitees,
    $totalSejours > 0 ? number_format($totalNuitees / $totalSejours, 1, ',', ' ') : '0'
], ';');

fputcsv($output, [], ';');
fputcsv($output, [], ';');

// Liste détaillée
fputcsv($output, ['LISTE DÉTAILLÉE DES HÉBERGEMENTS'], ';');
fputcsv($output, [
    'Date début',
    'Date fin',
    'Nom',
    'Prénom',
    'Sexe',
    'Âge',
    'Origine',
    'Chambre',
    'Type',
    'Durée (jours)',
    'Statut',
    'Saisi par',
    'Date saisie'
], ';');

foreach ($hebergements as $h) {
    fputcsv($output, [
        formatDate($h['date_debut'], 'd/m/Y'),
        $h['date_fin'] ? formatDate($h['date_fin'], 'd/m/Y') : 'En cours',
        $h['nom'],
        $h['prenom'],
        $h['sexe'],
        $h['age'] ?? '',
        $h['origine'] ?? '',
        $h['chambre'],
        $h['type_hebergement'],
        $h['duree_jours'],
        $h['statut'],
        $h['saisi_par'],
        formatDate($h['date_saisie'], 'd/m/Y H:i')
    ], ';');
}

fclose($output);

// Journaliser l'export
logAction('Export hébergements', 'nuitees', null, "Période: $dateDebut à $dateFin");

exit();