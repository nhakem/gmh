-- Script pour créer des chambres d'hébergement d'urgence (HD)
-- À exécuter après add_hd_type.sql

-- Récupérer l'ID du type HD
SET @type_hd_id = (SELECT id FROM types_hebergement WHERE nom = 'HD' LIMIT 1);

-- Vérifier que le type HD existe
SELECT 
    CASE 
        WHEN @type_hd_id IS NOT NULL 
        THEN CONCAT('Type HD trouvé avec ID: ', @type_hd_id)
        ELSE 'ERREUR: Type HD non trouvé. Exécutez d\'abord add_hd_type.sql'
    END as verification;

-- Créer 15 chambres HD avec différentes configurations
INSERT INTO `chambres` (`numero`, `type_hebergement_id`, `nombre_lits`, `tarif_standard`, `disponible`)
SELECT * FROM (
    -- Chambres individuelles HD (10 chambres)
    SELECT CONCAT('HD-', LPAD(num, 3, '0')), @type_hd_id, 1, 0.00, TRUE
    FROM (
        SELECT 101 as num UNION SELECT 102 UNION SELECT 103 UNION SELECT 104 UNION SELECT 105
        UNION SELECT 106 UNION SELECT 107 UNION SELECT 108 UNION SELECT 109 UNION SELECT 110
    ) t1
    
    UNION ALL
    
    -- Chambres doubles HD (5 chambres)
    SELECT CONCAT('HD-', LPAD(num, 3, '0')), @type_hd_id, 2, 0.00, TRUE
    FROM (
        SELECT 201 as num UNION SELECT 202 UNION SELECT 203 UNION SELECT 204 UNION SELECT 205
    ) t2
) AS new_chambers
WHERE @type_hd_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM chambres 
    WHERE numero = new_chambers.`CONCAT('HD-', LPAD(num, 3, '0'))`
);

-- Afficher le résultat
SELECT 
    COUNT(*) as nombre_chambres_hd,
    SUM(nombre_lits) as total_lits_hd,
    GROUP_CONCAT(numero ORDER BY numero SEPARATOR ', ') as liste_chambres
FROM chambres 
WHERE type_hebergement_id = @type_hd_id;

-- Statistiques par type de chambre
SELECT 
    CASE nombre_lits 
        WHEN 1 THEN 'Chambres individuelles HD'
        WHEN 2 THEN 'Chambres doubles HD'
        ELSE CONCAT('Chambres ', nombre_lits, ' lits HD')
    END as type_chambre,
    COUNT(*) as nombre,
    SUM(CASE WHEN disponible = TRUE THEN 1 ELSE 0 END) as disponibles
FROM chambres 
WHERE type_hebergement_id = @type_hd_id
GROUP BY nombre_lits
ORDER BY nombre_lits;