-- Ajout du type d'hébergement HD (Hébergement d'urgence) si il n'existe pas déjà
-- Script pour ajouter le support de l'hébergement d'urgence

-- Vérifier et ajouter le type d'hébergement HD
INSERT INTO `types_hebergement` (`nom`, `description`) 
SELECT 'HD', 'Hébergement d\'urgence' 
WHERE NOT EXISTS (
    SELECT 1 FROM `types_hebergement` WHERE `nom` = 'HD'
);

-- Ajouter quelques chambres HD d'exemple (optionnel)
-- Décommentez les lignes suivantes si vous voulez créer des chambres HD automatiquement

/*
-- Récupérer l'ID du type HD
SET @type_hd_id = (SELECT id FROM types_hebergement WHERE nom = 'HD');

-- Créer 10 chambres HD si le type existe
INSERT INTO `chambres` (`numero`, `type_hebergement_id`, `nombre_lits`, `tarif_standard`, `disponible`)
SELECT 
    CONCAT('HD-', LPAD(numero, 3, '0')) as numero,
    @type_hd_id,
    1 as nombre_lits,
    0.00 as tarif_standard,
    TRUE as disponible
FROM (
    SELECT 1 as numero UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
) numbers
WHERE @type_hd_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM chambres WHERE type_hebergement_id = @type_hd_id
);
*/

-- Afficher un message de confirmation
SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM types_hebergement WHERE nom = 'HD') 
        THEN 'Type HD (Hébergement d\'urgence) disponible dans la base de données' 
        ELSE 'Erreur : Type HD non créé' 
    END as statut;