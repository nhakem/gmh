<?php
// includes/classes/Statistics.php - Classe pour la gestion des statistiques
class Statistics {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Obtient les statistiques générales pour une période donnée
     */
    public function getGeneralStats($dateDebut = null, $dateFin = null) {
        if (!$dateDebut) $dateDebut = date('Y-m-01');
        if (!$dateFin) $dateFin = date('Y-m-t');
        
        try {
            $stmt = $this->db->prepare("CALL sp_statistiques_periode(?, ?)");
            $stmt->execute([$dateDebut, $dateFin]);
            
            $results = [];
            $results['general'] = $stmt->fetch();
            $stmt->nextRowset();
            $results['sexe'] = $stmt->fetchAll();
            $stmt->nextRowset();
            $results['hebergement'] = $stmt->fetchAll();
            $stmt->nextRowset();
            $results['repas'] = $stmt->fetchAll();
            
            return $results;
        } catch (PDOException $e) {
            error_log("Erreur statistiques générales: " . $e->getMessage());
            throw new Exception("Erreur lors du calcul des statistiques");
        }
    }
    
    /**
     * Calcule l'évolution mensuelle sur les N derniers mois
     */
    public function getEvolutionMensuelle($nombreMois = 12) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(date_debut, '%Y-%m') as mois,
                    DATE_FORMAT(date_debut, '%M %Y') as mois_libelle,
                    COUNT(DISTINCT personne_id) as personnes,
                    SUM(DATEDIFF(IFNULL(date_fin, LAST_DAY(date_debut)), date_debut) + 1) as nuitees,
                    AVG(DATEDIFF(IFNULL(date_fin, LAST_DAY(date_debut)), date_debut) + 1) as duree_moyenne
                FROM nuitees 
                WHERE actif = 1 
                    AND date_debut >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(date_debut, '%Y-%m')
                ORDER BY mois
            ");
            $stmt->execute([$nombreMois]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur évolution mensuelle: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtient la répartition par tranches d'âge
     */
    public function getRepartitionAge($dateDebut = null, $dateFin = null) {
        if (!$dateDebut) $dateDebut = date('Y-m-01');
        if (!$dateFin) $dateFin = date('Y-m-t');
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN p.age IS NULL THEN 'Non renseigné'
                        WHEN p.age < 18 THEN 'Moins de 18 ans'
                        WHEN p.age BETWEEN 18 AND 25 THEN '18-25 ans'
                        WHEN p.age BETWEEN 26 AND 40 THEN '26-40 ans'
                        WHEN p.age BETWEEN 41 AND 60 THEN '41-60 ans'
                        WHEN p.age > 60 THEN 'Plus de 60 ans'
                        ELSE 'Non renseigné'
                    END as tranche_age,
                    COUNT(DISTINCT n.personne_id) as nombre,
                    AVG(p.age) as age_moyen_tranche
                FROM nuitees n
                JOIN personnes p ON n.personne_id = p.id
                WHERE n.actif = 1 
                    AND n.date_debut BETWEEN ? AND ?
                GROUP BY tranche_age
                ORDER BY MIN(IFNULL(p.age, 999))
            ");
            $stmt->execute([$dateDebut, $dateFin]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur répartition âge: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calcule le taux d'occupation des chambres
     */
    public function getTauxOccupation($dateDebut = null, $dateFin = null) {
        if (!$dateDebut) $dateDebut = date('Y-m-01');
        if (!$dateFin) $dateFin = date('Y-m-t');
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.id,
                    c.numero,
                    th.nom as type_hebergement,
                    c.nombre_lits,
                    COUNT(n.id) as nombre_sejours,
                    SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, ?), ?), 
                        GREATEST(n.date_debut, ?)) + 1) as jours_occupation,
                    ROUND(
                        SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, ?), ?), 
                            GREATEST(n.date_debut, ?)) + 1) / 
                        (DATEDIFF(?, ?) + 1) * 100, 2
                    ) as taux_occupation,
                    AVG(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_moyenne_sejour
                FROM chambres c
                JOIN types_hebergement th ON c.type_hebergement_id = th.id
                LEFT JOIN nuitees n ON c.id = n.chambre_id 
                    AND n.actif = 1
                    AND n.date_debut <= ?
                    AND (n.date_fin IS NULL OR n.date_fin >= ?)
                GROUP BY c.id, c.numero, th.nom, c.nombre_lits
                ORDER BY taux_occupation DESC
            ");
            $stmt->execute([
                $dateFin, $dateFin, $dateDebut, 
                $dateFin, $dateFin, $dateDebut,
                $dateFin, $dateDebut,
                $dateFin, $dateDebut
            ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur taux occupation: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtient les top origines géographiques
     */
    public function getTopOrigines($limite = 10, $nombreMois = 3) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.origine,
                    COUNT(DISTINCT n.personne_id) as nombre,
                    SUM(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as total_nuitees,
                    AVG(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_moyenne
                FROM nuitees n
                JOIN personnes p ON n.personne_id = p.id
                WHERE n.actif = 1 
                    AND p.origine IS NOT NULL 
                    AND p.origine != ''
                    AND n.date_debut >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY p.origine
                ORDER BY nombre DESC
                LIMIT ?
            ");
            $stmt->execute([$nombreMois, $limite]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur top origines: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Analyse des modes de paiement
     */
    public function getAnalysePaiements($dateDebut = null, $dateFin = null) {
        if (!$dateDebut) $dateDebut = date('Y-m-01');
        if (!$dateFin) $dateFin = date('Y-m-t');
        
        try {
            // Analyse des nuitées
            $nuitees = $this->db->prepare("
                SELECT 
                    mode_paiement,
                    COUNT(*) as nombre_transactions,
                    SUM(CASE WHEN tarif_journalier IS NOT NULL AND montant_total IS NOT NULL 
                        THEN montant_total ELSE 0 END) as total_montant,
                    AVG(CASE WHEN tarif_journalier IS NOT NULL 
                        THEN tarif_journalier ELSE NULL END) as tarif_moyen
                FROM nuitees 
                WHERE actif = 1 AND date_debut BETWEEN ? AND ?
                GROUP BY mode_paiement
            ");
            $nuitees->execute([$dateDebut, $dateFin]);
            $resultNuitees = $nuitees->fetchAll();
            
            // Analyse des repas
            $repas = $this->db->prepare("
                SELECT 
                    mode_paiement,
                    COUNT(*) as nombre_transactions,
                    SUM(CASE WHEN montant IS NOT NULL THEN montant ELSE 0 END) as total_montant,
                    AVG(CASE WHEN montant IS NOT NULL THEN montant ELSE NULL END) as montant_moyen
                FROM repas 
                WHERE date_repas BETWEEN ? AND ?
                GROUP BY mode_paiement
            ");
            $repas->execute([$dateDebut, $dateFin]);
            $resultRepas = $repas->fetchAll();
            
            return [
                'nuitees' => $resultNuitees,
                'repas' => $resultRepas
            ];
        } catch (PDOException $e) {
            error_log("Erreur analyse paiements: " . $e->getMessage());
            return ['nuitees' => [], 'repas' => []];
        }
    }
    
    /**
     * Obtient les données pour le tableau de bord en temps réel
     */
    public function getDashboardData() {
        try {
            $data = [];
            
            // Statistiques instantanées
            $instant = $this->db->query("
                SELECT 
                    (SELECT COUNT(*) FROM personnes WHERE actif = 1) as total_personnes,
                    (SELECT COUNT(*) FROM personnes WHERE actif = 1 AND role = 'client') as total_clients,
                    (SELECT COUNT(*) FROM personnes WHERE actif = 1 AND role = 'benevole') as total_benevoles,
                    (SELECT COUNT(*) FROM nuitees WHERE actif = 1 AND (date_fin IS NULL OR date_fin >= CURDATE())) as hebergements_actifs,
                    (SELECT COUNT(*) FROM chambres WHERE disponible = 1) as chambres_disponibles,
                    (SELECT COUNT(*) FROM chambres) as total_chambres,
                    (SELECT COUNT(*) FROM repas WHERE date_repas = CURDATE()) as repas_aujourdhui
            ")->fetch();
            $data['instant'] = $instant;
            
            // Statistiques du mois courant
            $moisCourant = $this->db->query("
                SELECT 
                    COUNT(DISTINCT n.personne_id) as personnes_hebergees,
                    SUM(DATEDIFF(LEAST(IFNULL(n.date_fin, CURDATE()), LAST_DAY(CURDATE())), 
                        GREATEST(n.date_debut, DATE_FORMAT(CURDATE(), '%Y-%m-01'))) + 1) as total_nuitees,
                    (SELECT COUNT(*) FROM repas WHERE date_repas BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())) as total_repas_mois
                FROM nuitees n
                WHERE n.actif = 1 
                    AND n.date_debut <= LAST_DAY(CURDATE())
                    AND (n.date_fin IS NULL OR n.date_fin >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))
            ")->fetch();
            $data['mois_courant'] = $moisCourant;
            
            // Taux d'occupation global
            $occupation = $this->db->query("
                SELECT 
                    ROUND(
                        (SELECT COUNT(*) FROM nuitees WHERE actif = 1 AND (date_fin IS NULL OR date_fin >= CURDATE())) /
                        (SELECT COUNT(*) FROM chambres) * 100, 1
                    ) as taux_occupation_global
            ")->fetch();
            $data['occupation'] = $occupation;
            
            return $data;
        } catch (PDOException $e) {
            error_log("Erreur dashboard: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Génère un rapport personnalisé selon les critères
     */
    public function generateCustomReport($criteria) {
        $dateDebut = $criteria['date_debut'] ?? date('Y-m-01');
        $dateFin = $criteria['date_fin'] ?? date('Y-m-t');
        $groupBy = $criteria['group_by'] ?? 'jour';
        $filters = $criteria['filters'] ?? [];
        
        try {
            $sql = "
                SELECT 
                    " . $this->getGroupByField($groupBy) . " as periode,
                    COUNT(DISTINCT n.personne_id) as personnes,
                    SUM(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as nuitees,
                    COUNT(n.id) as sejours
                FROM nuitees n
                JOIN personnes p ON n.personne_id = p.id
                JOIN chambres c ON n.chambre_id = c.id
                JOIN types_hebergement th ON c.type_hebergement_id = th.id
                WHERE n.actif = 1 
                    AND n.date_debut BETWEEN ? AND ?
            ";
            
            $params = [$dateDebut, $dateFin];
            
            // Ajouter les filtres
            if (!empty($filters['sexe'])) {
                $sql .= " AND p.sexe = ?";
                $params[] = $filters['sexe'];
            }
            
            if (!empty($filters['type_hebergement'])) {
                $sql .= " AND th.id = ?";
                $params[] = $filters['type_hebergement'];
            }
            
            if (!empty($filters['age_min'])) {
                $sql .= " AND p.age >= ?";
                $params[] = $filters['age_min'];
            }
            
            if (!empty($filters['age_max'])) {
                $sql .= " AND p.age <= ?";
                $params[] = $filters['age_max'];
            }
            
            $sql .= " GROUP BY periode ORDER BY periode";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur rapport personnalisé: " . $e->getMessage());
            return [];
        }
    }
    
    private function getGroupByField($groupBy) {
        switch ($groupBy) {
            case 'jour':
                return "DATE(n.date_debut)";
            case 'semaine':
                return "YEARWEEK(n.date_debut)";
            case 'mois':
                return "DATE_FORMAT(n.date_debut, '%Y-%m')";
            case 'annee':
                return "YEAR(n.date_debut)";
            default:
                return "DATE(n.date_debut)";
        }
    }
    
    /**
     * Obtient les indicateurs de performance clés (KPI)
     */
    public function getKPI($dateDebut = null, $dateFin = null) {
        if (!$dateDebut) $dateDebut = date('Y-m-01');
        if (!$dateFin) $dateFin = date('Y-m-t');
        
        try {
            $kpi = $this->db->prepare("
                SELECT 
                    -- Capacité et utilisation
                    (SELECT COUNT(*) FROM chambres) as capacite_totale,
                    COUNT(DISTINCT n.chambre_id) as chambres_utilisees,
                    ROUND(COUNT(DISTINCT n.chambre_id) / (SELECT COUNT(*) FROM chambres) * 100, 1) as taux_utilisation_chambres,
                    
                    -- Personnes et séjours
                    COUNT(DISTINCT n.personne_id) as personnes_uniques,
                    COUNT(n.id) as nombre_sejours,
                    ROUND(COUNT(n.id) / COUNT(DISTINCT n.personne_id), 2) as sejours_par_personne,
                    
                    -- Durées
                    AVG(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_moyenne_sejour,
                    MIN(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_min_sejour,
                    MAX(DATEDIFF(IFNULL(n.date_fin, CURDATE()), n.date_debut) + 1) as duree_max_sejour,
                    
                    -- Nuitées
                    SUM(DATEDIFF(IFNULL(n.date_fin, ?), n.date_debut) + 1) as total_nuitees,
                    ROUND(SUM(DATEDIFF(IFNULL(n.date_fin, ?), n.date_debut) + 1) / (DATEDIFF(?, ?) + 1), 2) as nuitees_par_jour,
                    
                    -- Revenus (approximatifs)
                    SUM(CASE WHEN n.montant_total IS NOT NULL THEN n.montant_total ELSE 0 END) as revenus_hebergement
                    
                FROM nuitees n
                WHERE n.actif = 1 
                    AND n.date_debut BETWEEN ? AND ?
            ");
            $kpi->execute([$dateFin, $dateFin, $dateFin, $dateDebut, $dateDebut, $dateFin]);
            
            return $kpi->fetch();
        } catch (PDOException $e) {
            error_log("Erreur KPI: " . $e->getMessage());
            return [];
        }
    }
}

// includes/classes/ReportGenerator.php - Générateur de rapports
class ReportGenerator {
    private $statistics;
    private $db;
    
    public function __construct() {
        $this->statistics = new Statistics();
        $this->db = getDB();
    }
    
    /**
     * Génère un rapport PDF (nécessite une librairie PDF)
     */
    public function generatePDFReport($dateDebut, $dateFin, $options = []) {
        // Cette méthode nécessiterait une librairie comme TCPDF ou DomPDF
        // Pour l'instant, on génère un rapport HTML imprimable
        return $this->generateHTMLReport($dateDebut, $dateFin, $options);
    }
    
    /**
     * Génère un rapport HTML imprimable
     */
    public function generateHTMLReport($dateDebut, $dateFin, $options = []) {
        $stats = $this->statistics->getGeneralStats($dateDebut, $dateFin);
        $evolution = $this->statistics->getEvolutionMensuelle(12);
        $kpi = $this->statistics->getKPI($dateDebut, $dateFin);
        
        ob_start();
        include ROOT_PATH . '/statistiques/templates/rapport_html.php';
        return ob_get_clean();
    }
    
    /**
     * Exporte les données en JSON pour les API
     */
    public function exportToJSON($dateDebut, $dateFin, $format = 'complete') {
        $data = [
            'periode' => [
                'debut' => $dateDebut,
                'fin' => $dateFin,
                'generated_at' => date('Y-m-d H:i:s')
            ],
            'statistics' => $this->statistics->getGeneralStats($dateDebut, $dateFin),
            'kpi' => $this->statistics->getKPI($dateDebut, $dateFin)
        ];
        
        if ($format === 'complete') {
            $data['evolution'] = $this->statistics->getEvolutionMensuelle(12);
            $data['occupation'] = $this->statistics->getTauxOccupation($dateDebut, $dateFin);
            $data['origines'] = $this->statistics->getTopOrigines(10, 3);
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
?>