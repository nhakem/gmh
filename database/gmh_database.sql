-- Base de données GMH - Gestion d'établissement d'aide humanitaire
-- Création de la base de données
CREATE DATABASE IF NOT EXISTS `exalink_GMH` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `exalink_GMH`;

-- Table des utilisateurs
CREATE TABLE `utilisateurs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom_utilisateur` VARCHAR(50) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `nom_complet` VARCHAR(100) NOT NULL,
  `role` ENUM('agent_saisie', 'administrateur') NOT NULL DEFAULT 'agent_saisie',
  `actif` BOOLEAN DEFAULT TRUE,
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des personnes accueillies
CREATE TABLE `personnes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `sexe` ENUM('M', 'F', 'Autre') NOT NULL,
  `date_naissance` DATE NULL,
  `age` INT(3) NULL,
  `ville` VARCHAR(100) NULL,
  `origine` VARCHAR(100) NULL,
  `role` ENUM('client', 'benevole') NOT NULL DEFAULT 'client',
  `telephone` VARCHAR(20) NULL,
  `email` VARCHAR(100) NULL,
  `date_inscription` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `actif` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  INDEX `idx_nom_prenom` (`nom`, `prenom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des types d'hébergement
CREATE TABLE `types_hebergement` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des types d'hébergement par défaut
INSERT INTO `types_hebergement` (`nom`, `description`) VALUES
('MV', 'Maison de ville'),
('HD', 'Hébergement de dépannage'),
('Convalescence', 'Hébergement de convalescence'),
('Urgence', 'Hébergement d''urgence');

-- Table des chambres
CREATE TABLE `chambres` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero` VARCHAR(20) NOT NULL UNIQUE,
  `type_hebergement_id` INT(11) NOT NULL,
  `nombre_lits` INT(2) NOT NULL DEFAULT 1,
  `tarif_standard` DECIMAL(8,2) NULL DEFAULT 0.00,
  `disponible` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`type_hebergement_id`) REFERENCES `types_hebergement`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des nuitées
CREATE TABLE `nuitees` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `personne_id` INT(11) NOT NULL,
  `chambre_id` INT(11) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NULL,
  `mode_paiement` ENUM('gratuit', 'comptant', 'credit') NOT NULL DEFAULT 'gratuit',
  `tarif_journalier` DECIMAL(8,2) NULL,
  `montant_total` DECIMAL(8,2) NULL,
  `actif` BOOLEAN DEFAULT TRUE,
  `saisi_par` INT(11) NOT NULL,
  `date_saisie` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`personne_id`) REFERENCES `personnes`(`id`),
  FOREIGN KEY (`chambre_id`) REFERENCES `chambres`(`id`),
  FOREIGN KEY (`saisi_par`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_dates` (`date_debut`, `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des types de repas
CREATE TABLE `types_repas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des types de repas par défaut
INSERT INTO `types_repas` (`nom`) VALUES
('Petit-déjeuner'),
('Dîner'),
('Souper'),
('Collation');

-- Table des repas
CREATE TABLE `repas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `personne_id` INT(11) NOT NULL,
  `type_repas_id` INT(11) NOT NULL,
  `date_repas` DATE NOT NULL,
  `mode_paiement` ENUM('gratuit', 'comptant', 'credit') NOT NULL DEFAULT 'gratuit',
  `montant` DECIMAL(8,2) NULL,
  `saisi_par` INT(11) NOT NULL,
  `date_saisie` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`personne_id`) REFERENCES `personnes`(`id`),
  FOREIGN KEY (`type_repas_id`) REFERENCES `types_repas`(`id`),
  FOREIGN KEY (`saisi_par`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_date_repas` (`date_repas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des médicaments
CREATE TABLE `medicaments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `forme` VARCHAR(50) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des prescriptions
CREATE TABLE `prescriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `personne_id` INT(11) NOT NULL,
  `medicament_id` INT(11) NOT NULL,
  `dosage` VARCHAR(50) NOT NULL,
  `frequence` VARCHAR(100) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NULL,
  `gratuit` BOOLEAN DEFAULT TRUE,
  `cout` DECIMAL(8,2) NULL,
  `instructions` TEXT NULL,
  `saisi_par` INT(11) NOT NULL,
  `date_saisie` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`personne_id`) REFERENCES `personnes`(`id`),
  FOREIGN KEY (`medicament_id`) REFERENCES `medicaments`(`id`),
  FOREIGN KEY (`saisi_par`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table de journalisation
CREATE TABLE `logs_saisie` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` INT(11) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `table_concernee` VARCHAR(50) NOT NULL,
  `id_enregistrement` INT(11) NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `date_action` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_date_action` (`date_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vues pour les statistiques
-- Vue des nuitées actives
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
    DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1 AS nombre_nuits
FROM `nuitees` n
JOIN `personnes` p ON n.personne_id = p.id
JOIN `chambres` c ON n.chambre_id = c.id
JOIN `types_hebergement` th ON c.type_hebergement_id = th.id
WHERE n.actif = TRUE;

-- Vue des statistiques mensuelles
CREATE VIEW `v_stats_mensuelles` AS
SELECT 
    YEAR(n.date_debut) AS annee,
    MONTH(n.date_debut) AS mois,
    COUNT(DISTINCT n.personne_id) AS nombre_personnes,
    SUM(DATEDIFF(IFNULL(n.date_fin, LAST_DAY(n.date_debut)), n.date_debut) + 1) AS total_nuitees,
    COUNT(CASE WHEN p.sexe = 'M' THEN 1 END) AS hommes,
    COUNT(CASE WHEN p.sexe = 'F' THEN 1 END) AS femmes,
    AVG(p.age) AS age_moyen
FROM `nuitees` n
JOIN `personnes` p ON n.personne_id = p.id
WHERE n.actif = TRUE
GROUP BY YEAR(n.date_debut), MONTH(n.date_debut);

-- Insertion d'un utilisateur administrateur par défaut
-- Mot de passe : Admin#2025 (à changer lors de la première connexion)
INSERT INTO `utilisateurs` (`nom_utilisateur`, `mot_de_passe`, `nom_complet`, `role`) 
VALUES ('admin', '$2y$12$eImiTXuWOxBoBRMONxY2Q.37bZb5Bw1J9aKpFqGFhkBgKdItuBhOi', 'Administrateur Principal', 'administrateur');

-- Procédure stockée pour calculer les statistiques d'une période
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
        COUNT(DISTINCT n.chambre_id) AS chambres_utilisees
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

    -- Statistiques par type d'hébergement
    SELECT 
        th.nom AS type_hebergement,
        COUNT(DISTINCT n.personne_id) AS nombre_personnes,
        SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), date_fin), 
            GREATEST(n.date_debut, date_debut)) + 1) AS total_nuitees
    FROM `nuitees` n
    JOIN `chambres` c ON n.chambre_id = c.id
    JOIN `types_hebergement` th ON c.type_hebergement_id = th.id
    WHERE n.actif = TRUE
        AND n.date_debut <= date_fin
        AND (n.date_fin IS NULL OR n.date_fin >= date_debut)
    GROUP BY th.id, th.nom;

    -- Statistiques des repas
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

-- Index supplémentaires pour optimiser les performances
CREATE INDEX `idx_personne_origine` ON `personnes` (`origine`);
CREATE INDEX `idx_personne_age` ON `personnes` (`age`);
CREATE INDEX `idx_nuitees_personne` ON `nuitees` (`personne_id`);
CREATE INDEX `idx_repas_personne` ON `repas` (`personne_id`);
CREATE INDEX `idx_prescriptions_personne` ON `prescriptions` (`personne_id`);