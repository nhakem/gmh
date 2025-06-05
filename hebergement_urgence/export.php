<?php
// hebergement_urgence/export.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'error');
}

$db = getDB();

// Récupérer l'ID du type d'hébergement HD
$typeHDSql = "SELECT id FROM types_hebergement WHERE nom = 'HD'";
$typeHD = $db->query($typeHDSql)->fetch();
$typeHDId = $typeHD['id'] ?? null;

// Paramètres de l'export
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

// Récupérer les données
$sql = "SELECT 
    p.nom as personne_nom,
    p.prenom as personne_prenom,
    p.sexe,
    p.age,
    p.ville,
    p.origine,
    c.numero as chambre_numero,
    n.date_debut,
    n.date_fin,
    DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 as duree_jours,
    n.mode_paiement,
    n.tarif_journalier,
    n.tarif_journalier * DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 as montant_total,
    n.actif,
    u.nom_complet as saisi_par,
    n.date_saisie
FROM nuitees n
JOIN personnes p ON n.personne_id = p.id
JOIN chambres c ON n.chambre_id = c.id
JOIN utilisateurs u ON n.saisi_par = u.id
WHERE c.type_hebergement_id = :type_id
AND n.date_debut <= :date_fin
AND (n.date_fin IS NULL OR n.date_fin >= :date_debut)
ORDER BY n.date_debut DESC, p.nom, p.prenom";

$stmt = $db->prepare($sql);
$stmt->execute([
    ':type_id' => $typeHDId,
    ':date_debut' => $date_debut,
    ':date_fin' => $date_fin
]);

// Générer le fichier CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hebergement_urgence_HD_' . date('Y-m-d') . '.csv"');

// BOM pour Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// En-têtes
fputcsv($output, [
    'Nom',
    'Prénom',
    'Sexe',
    'Âge',
    'Ville',
    'Origine',
    'Chambre HD',
    'Date début',
    'Date fin',
    'Durée (jours)',
    'Mode paiement',
    'Tarif journalier',
    'Montant total',
    'Statut',
    'Saisi par',
    'Date saisie'
], ';');

// Données
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['personne_nom'],
        $row['personne_prenom'],
        $row['sexe'],
        $row['age'] ?? 'N/A',
        $row['ville'] ?? 'N/A',
        $row['origine'] ?? 'N/A',
        $row['chambre_numero'],
        formatDate($row['date_debut'], 'd/m/Y'),
        $row['date_fin'] ? formatDate($row['date_fin'], 'd/m/Y') : 'En cours',
        $row['duree_jours'],
        ucfirst($row['mode_paiement']),
        $row['mode_paiement'] !== 'gratuit' ? number_format($row['tarif_journalier'], 2) : '0',
        $row['mode_paiement'] !== 'gratuit' ? number_format($row['montant_total'], 2) : '0',
        $row['actif'] ? 'Actif' : 'Terminé',
        $row['saisi_par'],
        formatDate($row['date_saisie'], 'd/m/Y H:i')
    ], ';');
}

// Statistiques récapitulatives
fputcsv($output, [], ';');
fputcsv($output, ['STATISTIQUES HÉBERGEMENT D\'URGENCE (HD)'], ';');
fputcsv($output, ['Période : du ' . formatDate($date_debut, 'd/m/Y') . ' au ' . formatDate($date_fin, 'd/m/Y')], ';');

// Calculer les statistiques
$statsSql = "SELECT 
    COUNT(DISTINCT n.personne_id) as nb_personnes,
    COUNT(n.id) as nb_sejours,
    SUM(DATEDIFF(IFNULL(n.date_fin, LEAST(CURDATE(), :stat_date_fin)), 
        GREATEST(n.date_debut, :stat_date_debut)) + 1) as total_nuitees,
    AVG(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_moyenne,
    SUM(CASE WHEN n.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) as nb_gratuits,
    SUM(CASE WHEN n.mode_paiement != 'gratuit' THEN 
        n.tarif_journalier * DATEDIFF(IFNULL(n.date_fin, LEAST(CURDATE(), :stat_date_fin2)), 
        GREATEST(n.date_debut, :stat_date_debut2)) + 1 ELSE 0 END) as revenus_totaux
FROM nuitees n
JOIN chambres c ON n.chambre_id = c.id
WHERE c.type_hebergement_id = :type_id
AND n.date_debut <= :stat_date_fin3
AND (n.date_fin IS NULL OR n.date_fin >= :stat_date_debut3)";

$statsStmt = $db->prepare($statsSql);
$statsStmt->execute([
    ':type_id' => $typeHDId,
    ':stat_date_debut' => $date_debut,
    ':stat_date_debut2' => $date_debut,
    ':stat_date_debut3' => $date_debut,
    ':stat_date_fin' => $date_fin,
    ':stat_date_fin2' => $date_fin,
    ':stat_date_fin3' => $date_fin
]);
$stats = $statsStmt->fetch();

fputcsv($output, [], ';');
fputcsv($output, ['Nombre de personnes HD', $stats['nb_personnes'] ?? 0], ';');
fputcsv($output, ['Nombre de séjours HD', $stats['nb_sejours'] ?? 0], ';');
fputcsv($output, ['Total des nuitées HD', $stats['total_nuitees'] ?? 0], ';');
fputcsv($output, ['Durée moyenne de séjour HD', number_format($stats['duree_moyenne'] ?? 0, 1) . ' jours'], ';');
fputcsv($output, ['Hébergements gratuits HD', $stats['nb_gratuits'] ?? 0], ';');
fputcsv($output, ['Revenus totaux HD', number_format($stats['revenus_totaux'] ?? 0, 2) . ' $'], ';');

// Statistiques par sexe
fputcsv($output, [], ';');
fputcsv($output, ['RÉPARTITION PAR SEXE (HD)'], ';');

$sexeSql = "SELECT p.sexe, COUNT(DISTINCT n.personne_id) as nombre
FROM nuitees n
JOIN personnes p ON n.personne_id = p.id
JOIN chambres c ON n.chambre_id = c.id
WHERE c.type_hebergement_id = :type_id
AND n.date_debut <= :date_fin
AND (n.date_fin IS NULL OR n.date_fin >= :date_debut)
GROUP BY p.sexe";

$sexeStmt = $db->prepare($sexeSql);
$sexeStmt->execute([
    ':type_id' => $typeHDId,
    ':date_debut' => $date_debut,
    ':date_fin' => $date_fin
]);

while ($sexe = $sexeStmt->fetch()) {
    fputcsv($output, [$sexe['sexe'], $sexe['nombre']], ';');
}

fclose($output);

// Journaliser l'export
logAction('Export données HD', null, null, "Période: $date_debut à $date_fin");

exit;
?>