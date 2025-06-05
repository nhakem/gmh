<?php
// maintenance/repair.php - Réparation automatique des problèmes courants
require_once '../config.php';
require_once ROOT_PATH . '/includes/classes/Auth.php';

// Vérification authentification et permissions
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

if (!hasPermission('administrateur')) {
    redirect('dashboard/', MSG_PERMISSION_DENIED, 'error');
}

try {
    $db = getDB();
    $repairActions = [];
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'fix_old_nuitees':
            // Fermer automatiquement les nuitées anciennes sans date de fin
            $stmt = $db->prepare("
                UPDATE nuitees 
                SET date_fin = DATE_SUB(CURDATE(), INTERVAL 1 DAY),
                    montant_total = CASE 
                        WHEN tarif_journalier IS NOT NULL 
                        THEN tarif_journalier * DATEDIFF(DATE_SUB(CURDATE(), INTERVAL 1 DAY), date_debut) + 1
                        ELSE montant_total 
                    END
                WHERE date_fin IS NULL 
                  AND date_debut < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  AND actif = 1
            ");
            $stmt->execute();
            $fixedNuitees = $stmt->rowCount();
            $repairActions[] = "$fixedNuitees nuitées anciennes fermées automatiquement";
            break;
            
        case 'remove_orphan_data':
            // Supprimer les données orphelines
            $db->beginTransaction();
            
            try {
                // Repas orphelins
                $stmt = $db->query("
                    DELETE r FROM repas r
                    LEFT JOIN personnes p ON r.personne_id = p.id
                    WHERE p.id IS NULL
                ");
                $deletedRepas = $stmt->rowCount();
                
                // Prescriptions orphelines
                $stmt = $db->query("
                    DELETE pr FROM prescriptions pr
                    LEFT JOIN personnes p ON pr.personne_id = p.id
                    WHERE p.id IS NULL
                ");
                $deletedPrescriptions = $stmt->rowCount();
                
                // Nuitées orphelines (personne supprimée)
                $stmt = $db->query("
                    DELETE n FROM nuitees n
                    LEFT JOIN personnes p ON n.personne_id = p.id
                    WHERE p.id IS NULL
                ");
                $deletedNuitees = $stmt->rowCount();
                
                $db->commit();
                
                $repairActions[] = "$deletedRepas repas orphelins supprimés";
                $repairActions[] = "$deletedPrescriptions prescriptions orphelines supprimées";
                $repairActions[] = "$deletedNuitees nuitées orphelines supprimées";
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'update_ages':
            // Mettre à jour les âges selon les dates de naissance
            $stmt = $db->query("
                UPDATE personnes 
                SET age = TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())
                WHERE date_naissance IS NOT NULL
                  AND (age IS NULL OR age != TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()))
            ");
            $updatedAges = $stmt->rowCount();
            $repairActions[] = "$updatedAges âges mis à jour selon les dates de naissance";
            break;
            
        case 'fix_chamber_conflicts':
            // Analyser les conflits de chambre et proposer des solutions
            $stmt = $db->query("
                SELECT 
                    n1.id as nuitee1_id,
                    n2.id as nuitee2_id,
                    c.numero as chambre,
                    c.id as chambre_id,
                    n1.date_debut as debut1,
                    n1.date_fin as fin1,
                    n2.date_debut as debut2,
                    n2.date_fin as fin2,
                    p1.nom as nom1,
                    p1.prenom as prenom1,
                    p2.nom as nom2,
                    p2.prenom as prenom2
                FROM nuitees n1
                JOIN nuitees n2 ON n1.chambre_id = n2.chambre_id 
                JOIN chambres c ON n1.chambre_id = c.id
                JOIN personnes p1 ON n1.personne_id = p1.id
                JOIN personnes p2 ON n2.personne_id = p2.id
                WHERE n1.id < n2.id
                  AND n1.actif = 1 AND n2.actif = 1
                  AND n1.date_debut <= IFNULL(n2.date_fin, CURDATE())
                  AND IFNULL(n1.date_fin, CURDATE()) >= n2.date_debut
                ORDER BY c.numero, n1.date_debut
            ");
            $conflicts = $stmt->fetchAll();
            
            $resolvedConflicts = 0;
            
            foreach ($conflicts as $conflict) {
                // Stratégie 1: Fermer la nuitée la plus ancienne si elle n'a pas de date de fin
                if (is_null($conflict['fin1']) && !is_null($conflict['debut2'])) {
                    $newEndDate = date('Y-m-d', strtotime($conflict['debut2'] . ' -1 day'));
                    
                    $updateStmt = $db->prepare("
                        UPDATE nuitees 
                        SET date_fin = ? 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$newEndDate, $conflict['nuitee1_id']]);
                    $resolvedConflicts++;
                }
                // Stratégie 2: Si les deux ont des dates de fin, raccourcir la première
                elseif (!is_null($conflict['fin1']) && !is_null($conflict['fin2'])) {
                    if ($conflict['debut2'] <= $conflict['fin1']) {
                        $newEndDate = date('Y-m-d', strtotime($conflict['debut2'] . ' -1 day'));
                        
                        $updateStmt = $db->prepare("
                            UPDATE nuitees 
                            SET date_fin = ? 
                            WHERE id = ? AND date_fin > ?
                        ");
                        $updateStmt->execute([$newEndDate, $conflict['nuitee1_id'], $newEndDate]);
                        if ($updateStmt->rowCount() > 0) {
                            $resolvedConflicts++;
                        }
                    }
                }
            }
            
            if ($resolvedConflicts > 0) {
                $repairActions[] = "$resolvedConflicts conflits de chambre résolus automatiquement";
            } else {
                $repairActions[] = count($conflicts) . " conflits détectés nécessitant une intervention manuelle";
            }
            break;
            
        case 'normalize_data':
            // Normaliser les données (majuscules, espaces, etc.)
            $normalized = 0;
            
            // Normaliser les noms et prénoms
            $stmt = $db->query("
                UPDATE personnes 
                SET nom = TRIM(UPPER(nom)),
                    prenom = TRIM(CONCAT(UPPER(LEFT(prenom, 1)), LOWER(SUBSTRING(prenom, 2))))
                WHERE nom != TRIM(UPPER(nom)) 
                   OR prenom != TRIM(CONCAT(UPPER(LEFT(prenom, 1)), LOWER(SUBSTRING(prenom, 2))))
            ");
            $normalized += $stmt->rowCount();
            
            // Normaliser les villes
            $stmt = $db->query("
                UPDATE personnes 
                SET ville = TRIM(CONCAT(UPPER(LEFT(ville, 1)), LOWER(SUBSTRING(ville, 2))))
                WHERE ville IS NOT NULL 
                  AND ville != TRIM(CONCAT(UPPER(LEFT(ville, 1)), LOWER(SUBSTRING(ville, 2))))
            ");
            $normalized += $stmt->rowCount();
            
            // Nettoyer les numéros de téléphone
            $stmt = $db->query("
                UPDATE personnes 
                SET telephone = REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '(', ''), ')', '')
                WHERE telephone IS NOT NULL 
                  AND telephone REGEXP '[^0-9+]'
            ");
            $normalized += $stmt->rowCount();
            
            $repairActions[] = "$normalized enregistrements normalisés";
            break;
            
        case 'fix_duplicates':
            // Détecter et marquer les doublons potentiels
            $stmt = $db->query("
                SELECT 
                    GROUP_CONCAT(id) as ids,
                    nom, 
                    prenom, 
                    date_naissance,
                    COUNT(*) as count
                FROM personnes 
                WHERE nom IS NOT NULL AND prenom IS NOT NULL
                GROUP BY nom, prenom, date_naissance
                HAVING COUNT(*) > 1
            ");
            $duplicates = $stmt->fetchAll();
            
            $markedDuplicates = 0;
            foreach ($duplicates as $duplicate) {
                $ids = explode(',', $duplicate['ids']);
                // Garder le premier, marquer les autres comme inactifs
                array_shift($ids); // Enlever le premier ID
                
                if (!empty($ids)) {
                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    $updateStmt = $db->prepare("
                        UPDATE personnes 
                        SET actif = 0, 
                            nom = CONCAT(nom, ' (DOUBLON)')
                        WHERE id IN ($placeholders)
                    ");
                    $updateStmt->execute($ids);
                    $markedDuplicates += $updateStmt->rowCount();
                }
            }
            
            $repairActions[] = "$markedDuplicates doublons marqués comme inactifs";
            break;
            
        case 'recalculate_amounts':
            // Recalculer les montants des nuitées
            $stmt = $db->query("
                UPDATE nuitees 
                SET montant_total = tarif_journalier * (DATEDIFF(IFNULL(date_fin, CURDATE()), date_debut) + 1)
                WHERE tarif_journalier IS NOT NULL 
                  AND tarif_journalier > 0
                  AND (montant_total IS NULL 
                       OR montant_total != tarif_journalier * (DATEDIFF(IFNULL(date_fin, CURDATE()), date_debut) + 1))
            ");
            $recalculated = $stmt->rowCount();
            $repairActions[] = "$recalculated montants de nuitées recalculés";
            break;
            
        default:
            throw new Exception("Action de réparation non reconnue: $action");
    }
    
    // Log des actions de réparation
    $repairSummary = implode(', ', $repairActions);
    logAction('system_repair', 'maintenance', null, "Action: $action - $repairSummary");
    
    redirect('maintenance/', "Réparation terminée: " . $repairSummary, 'success');
    
} catch (Exception $e) {
    redirect('maintenance/', 'Erreur lors de la réparation: ' . $e->getMessage(), 'error');
}
?>