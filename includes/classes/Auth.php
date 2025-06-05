<?php
// includes/classes/Auth.php

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Authentifier un utilisateur
     */
    public function login($username, $password) {
        try {
            // Récupérer l'utilisateur
            $sql = "SELECT id, nom_utilisateur, mot_de_passe, nom_complet, role, actif 
                    FROM utilisateurs 
                    WHERE nom_utilisateur = :username";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
            
            // Vérifier si l'utilisateur existe et est actif
            if (!$user || !$user['actif']) {
                return ['success' => false, 'message' => 'Nom d\'utilisateur ou mot de passe incorrect.'];
            }
            
            // Vérifier le mot de passe
            if (!password_verify($password, $user['mot_de_passe'])) {
                return ['success' => false, 'message' => 'Nom d\'utilisateur ou mot de passe incorrect.'];
            }
            
            // Créer la session
            $this->createSession($user);
            
            // Mettre à jour la dernière connexion
            $this->updateLastLogin($user['id']);
            
            // Journaliser la connexion
            logAction('Connexion', 'utilisateurs', $user['id']);
            
            return ['success' => true, 'message' => 'Connexion réussie.'];
            
        } catch (PDOException $e) {
            error_log("Erreur de connexion : " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue lors de la connexion.'];
        }
    }
    
    /**
     * Déconnecter l'utilisateur
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logAction('Déconnexion', 'utilisateurs', $_SESSION['user_id']);
        }
        
        // Détruire la session
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['user_role'] === $role || $_SESSION['user_role'] === 'administrateur';
    }
    
    /**
     * Créer un nouvel utilisateur
     */
    public function createUser($data) {
        try {
            // Vérifier que l'utilisateur n'existe pas déjà
            $checkSql = "SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur = :username";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([':username' => $data['nom_utilisateur']]);
            
            if ($checkStmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Ce nom d\'utilisateur existe déjà.'];
            }
            
            // Hasher le mot de passe
            $hashedPassword = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Insérer l'utilisateur
            $sql = "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role) 
                    VALUES (:username, :password, :fullname, :role)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':username' => $data['nom_utilisateur'],
                ':password' => $hashedPassword,
                ':fullname' => $data['nom_complet'],
                ':role' => $data['role']
            ]);
            
            if ($result) {
                $userId = $this->db->lastInsertId();
                logAction('Création utilisateur', 'utilisateurs', $userId, "Nouvel utilisateur : " . $data['nom_utilisateur']);
                return ['success' => true, 'message' => 'Utilisateur créé avec succès.'];
            }
            
            return ['success' => false, 'message' => 'Erreur lors de la création de l\'utilisateur.'];
            
        } catch (PDOException $e) {
            error_log("Erreur création utilisateur : " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue.'];
        }
    }
    
    /**
     * Modifier le mot de passe d'un utilisateur
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            // Récupérer le mot de passe actuel
            $sql = "SELECT mot_de_passe FROM utilisateurs WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $userId]);
            $currentPassword = $stmt->fetchColumn();
            
            // Vérifier l'ancien mot de passe
            if (!password_verify($oldPassword, $currentPassword)) {
                return ['success' => false, 'message' => 'L\'ancien mot de passe est incorrect.'];
            }
            
            // Hasher le nouveau mot de passe
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Mettre à jour le mot de passe
            $updateSql = "UPDATE utilisateurs SET mot_de_passe = :password WHERE id = :id";
            $updateStmt = $this->db->prepare($updateSql);
            $result = $updateStmt->execute([
                ':password' => $hashedPassword,
                ':id' => $userId
            ]);
            
            if ($result) {
                logAction('Changement mot de passe', 'utilisateurs', $userId);
                return ['success' => true, 'message' => 'Mot de passe modifié avec succès.'];
            }
            
            return ['success' => false, 'message' => 'Erreur lors de la modification du mot de passe.'];
            
        } catch (PDOException $e) {
            error_log("Erreur changement mot de passe : " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue.'];
        }
    }
    
    /**
     * Créer la session utilisateur
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['nom_utilisateur'];
        $_SESSION['user_fullname'] = $user['nom_complet'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Régénérer l'ID de session pour la sécurité
        session_regenerate_id(true);
    }
    
    /**
     * Mettre à jour la dernière connexion
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
    }
    
    /**
     * Vérifier le timeout de session
     */
    public function checkSessionTimeout() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (isset($_SESSION['login_time'])) {
            $elapsed = time() - $_SESSION['login_time'];
            
            if ($elapsed > SESSION_LIFETIME) {
                $this->logout();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Obtenir la liste des utilisateurs
     */
    public function getUsers() {
        try {
            $sql = "SELECT id, nom_utilisateur, nom_complet, role, actif, date_creation, derniere_connexion 
                    FROM utilisateurs 
                    ORDER BY nom_complet";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erreur récupération utilisateurs : " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Activer/Désactiver un utilisateur
     */
    public function toggleUserStatus($userId, $status) {
        try {
            $sql = "UPDATE utilisateurs SET actif = :status WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status ? 1 : 0,
                ':id' => $userId
            ]);
            
            if ($result) {
                $action = $status ? 'Activation' : 'Désactivation';
                logAction($action . ' utilisateur', 'utilisateurs', $userId);
                return ['success' => true, 'message' => 'Statut de l\'utilisateur modifié.'];
            }
            
            return ['success' => false, 'message' => 'Erreur lors de la modification du statut.'];
            
        } catch (PDOException $e) {
            error_log("Erreur modification statut utilisateur : " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue.'];
        }
    }
}