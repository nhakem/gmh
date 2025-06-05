-- Script de migration pour ajouter la gestion des coûts d'hébergement
-- GMH - Gestion d'établissement d'aide humanitaire
-- À exécuter sur la base de données existante

-- 1. Ajouter le tarif standard aux chambres
ALTER TABLE `chambres` 
ADD COLUMN `tarif_standard` DECIMAL(8,2) NULL DEFAULT 0.00 
AFTER `nombre_lits`;

-- 2. Ajouter les colonnes de facturation aux nuitées
ALTER TABLE `nuitees` 
ADD COLUMN `mode_paiement` ENUM('gratuit', 'comptant', 'credit') NOT NULL DEFAULT 'gratuit' 
AFTER `date_fin`;

ALTER TABLE `nuitees` 
ADD COLUMN `tarif_journalier` DECIMAL(8,2) NULL 
AFTER `mode_paiement`;

ALTER TABLE `nuitees` 
ADD COLUMN `montant_total` DECIMAL(8,2) NULL 
AFTER `tarif_journalier`;

-- 3. Mettre à jour les vues pour inclure les informations financières
DROP VIEW IF EXISTS `v_nuitees_actives`;

CREATE VIEW `v_nuitees_actives` AS
SELECT 
    n.id,
    p.nom,
    p.prenom,
    p.sexe,
    p.age,
    p.origine,
    c.numero AS chambre,
    th.nom AS type_hebergement,
    n.date_debut,
    n.date_fin,
    n.mode_paiement,
    n.tarif_journalier,
    n.montant_total,
    DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 AS nombre_nuits
FROM `nuitees` n
JOIN `personnes` p ON n.personne_id = p.id
JOIN `chambres` c ON n.chambre_id = c.id
JOIN `types_hebergement` th ON c.type_hebergement_id = th.id
WHERE n.actif = TRUE;

-- 4. Créer une vue pour les statistiques financières
CREATE VIEW `v_stats_financieres_hebergement` AS
SELECT 
    DATE_FORMAT(n.date_debut, '%Y-%m') AS mois,
    COUNT(DISTINCT n.personne_id) AS nombre_personnes,
    COUNT(*) AS nombre_sejours,
    SUM(CASE WHEN n.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) AS sejours_gratuits,
    SUM(CASE WHEN n.mode_paiement = 'comptant' THEN 1 ELSE 0 END) AS sejours_comptant,
    SUM(CASE WHEN n.mode_paiement = 'credit' THEN 1 ELSE 0 END) AS sejours_credit,
    SUM(CASE 
        WHEN n.mode_paiement != 'gratuit' AND n.tarif_journalier IS NOT NULL 
        THEN n.tarif_journalier * (DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1)
        ELSE 0 
    END) AS revenus_totaux,
    AVG(CASE 
        WHEN n.mode_paiement != 'gratuit' AND n.tarif_journalier IS NOT NULL 
        THEN n.tarif_journalier 
        ELSE NULL 
    END) AS tarif_moyen
FROM `nuitees` n
GROUP BY DATE_FORMAT(n.date_debut, '%Y-%m');

-- 5. Mettre à jour la procédure stockée pour inclure les statistiques financières
DROP PROCEDURE IF EXISTS `sp_statistiques_periode`;

DELIMITER //
CREATE PROCEDURE `sp_statistiques_periode`(
    IN date_debut DATE,
    IN date_fin DATE
)
BEGIN
    -- Statistiques générales
    SELECT 
        COUNT(DISTINCT n.personne_id) AS nombre_personnes_total,
        SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), date_fin), 
            GREATEST(n.date_debut, date_debut)) + 1) AS total_nuitees,
        COUNT(DISTINCT n.chambre_id) AS chambres_utilisees,
        SUM(CASE WHEN n.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) AS hebergements_gratuits,
        SUM(CASE WHEN n.mode_paiement != 'gratuit' THEN 1 ELSE 0 END) AS hebergements_payants,
        SUM(CASE 
            WHEN n.mode_paiement != 'gratuit' AND n.tarif_journalier IS NOT NULL 
            THEN n.tarif_journalier * DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), date_fin), 
                GREATEST(n.date_debut, date_debut)) + 1
            ELSE 0 
        END) AS revenus_totaux
    FROM `nuitees` n
    WHERE n.actif = TRUE
        AND n.date_debut <= date_fin
        AND (n.date_fin IS NULL OR n.date_fin >= date_debut);

    -- Statistiques par sexe
    SELECT 
        p.sexe,
        COUNT(DISTINCT n.personne_id) AS nombre_personnes,
        SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), date_fin), 
            GREATEST(n.date_debut, date_debut)) + 1) AS total_nuitees
    FROM `nuitees` n
    JOIN `personnes` p ON n.personne_id = p.id
    WHERE n.actif = TRUE
        AND n.date_debut <= date_fin
        AND (n.date_fin IS NULL OR n.date_fin >= date_debut)
    GROUP BY p.sexe;

    -- Statistiques par type d'hébergement avec revenus
    SELECT 
        th.nom AS type_hebergement,
        COUNT(DISTINCT n.personne_id) AS nombre_personnes,
        SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), date_fin), 
            GREATEST(n.date_debut, date_debut)) + 1) AS total_nuitees,
        SUM(CASE WHEN n.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) AS sejours_gratuits,
        SUM(CASE WHEN n.mode_paiement != 'gratuit' THEN 1 ELSE 0 END) AS sejours_payants,
        SUM(CASE 
            WHEN n.mode_paiement != 'gratuit' AND n.tarif_journalier IS NOT NULL 
            THEN n.tarif_journalier * DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), date_fin), 
                GREATEST(n.date_debut, date_debut)) + 1
            ELSE 0 
        END) AS revenus
    FROM `nuitees` n
    JOIN `chambres` c ON n.chambre_id = c.id
    JOIN `types_hebergement` th ON c.type_hebergement_id = th.id
    WHERE n.actif = TRUE
        AND n.date_debut <= date_fin
        AND (n.date_fin IS NULL OR n.date_fin >= date_debut)
    GROUP BY th.id, th.nom;

    -- Statistiques des repas (inchangées)
    SELECT 
        tr.nom AS type_repas,
        COUNT(*) AS nombre_repas,
        r.mode_paiement,
        SUM(CASE WHEN r.mode_paiement = 'gratuit' THEN 1 ELSE 0 END) AS repas_gratuits,
        SUM(CASE WHEN r.mode_paiement != 'gratuit' THEN IFNULL(r.montant, 0) ELSE 0 END) AS total_revenus
    FROM `repas` r
    JOIN `types_repas` tr ON r.type_repas_id = tr.id
    WHERE r.date_repas BETWEEN date_debut AND date_fin
    GROUP BY tr.id, tr.nom, r.mode_paiement;
END//
DELIMITER ;

-- 6. Optionnel : Définir des tarifs standards pour certains types de chambres
-- Exemple : Mettre un tarif de 25$ pour les chambres de type "Convalescence"
-- UPDATE chambres c 
-- JOIN types_hebergement th ON c.type_hebergement_id = th.id 
-- SET c.tarif_standard = 25.00 
-- WHERE th.nom = 'Convalescence';

-- 7. Index pour améliorer les performances des requêtes financières
CREATE INDEX `idx_nuitees_paiement` ON `nuitees` (`mode_paiement`, `date_debut`, `date_fin`);
CREATE INDEX `idx_nuitees_tarif` ON `nuitees` (`tarif_journalier`, `actif`);

-- Fin du script de migration