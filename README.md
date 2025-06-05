# GMH - Gestion d'établissement d'aide humanitaire

## Description
Application web de gestion pour un établissement d'aide humanitaire offrant hébergement temporaire, repas et soutien médical aux populations vulnérables.

## Prérequis
- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur Apache avec mod_rewrite activé
- Accès cPanel/FTP pour le déploiement

## Installation

### 1. Base de données
1. Créer une base de données MySQL nommée `exalink_GMH`
2. Importer le fichier `database/gmh_database.sql` via phpMyAdmin
3. Créer un utilisateur MySQL avec tous les privilèges sur cette base

### 2. Configuration
1. Éditer le fichier `config.php` avec vos informations :
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'exalink_GMH');
   define('DB_USER', 'votre_utilisateur');
   define('DB_PASS', 'votre_mot_de_passe');
   ```

### 3. Upload des fichiers
1. Uploader tous les fichiers dans `/public_html/GMH/` via FTP
2. Donner les permissions 755 aux dossiers :
   - `logs/`
   - `uploads/`

### 4. Première connexion
- **Utilisateur** : admin
- **Mot de passe** : admin123
- **⚠️ IMPORTANT** : Changer ce mot de passe immédiatement après la première connexion

## Structure des dossiers
```
GMH/
├── config.php          # Configuration principale
├── index.php          # Redirection automatique
├── login.php          # Page de connexion
├── logout.php         # Script de déconnexion
├── includes/          # Fichiers inclus (header, footer, classes)
├── dashboard/         # Tableaux de bord (admin et agent)
├── personnes/         # Gestion des personnes
├── hebergement/       # Gestion de l'hébergement
├── repas/            # Gestion des repas
├── medicaments/      # Gestion des médicaments
├── statistiques/     # Statistiques (admin seulement)
├── utilisateurs/     # Gestion des utilisateurs (admin)
├── logs/             # Journaux système
└── uploads/          # Fichiers uploadés
```

## Rôles utilisateurs

### Administrateur
- Accès complet au système
- Visualisation des statistiques
- Gestion des utilisateurs
- Export des données
- Consultation des journaux

### Agent de saisie
- Enregistrement des personnes
- Attribution des chambres
- Enregistrement des repas
- Prescription de médicaments
- Pas d'accès aux statistiques

## Fonctionnalités principales

### 1. Gestion des personnes
- Enregistrement avec nom, prénom, sexe, âge, origine
- Historique des passages
- Recherche et filtres

### 2. Hébergement
- Attribution de chambres par type (MV, HD, convalescence, etc.)
- Suivi des nuitées
- Libération des chambres

### 3. Repas
- Enregistrement par type (petit-déjeuner, dîner, souper, collation)
- Mode de paiement (gratuit, comptant)
- Statistiques de consommation

### 4. Médicaments
- Prescription avec dosage et fréquence
- Suivi gratuit/payant
- Historique par personne

### 5. Statistiques (Admin)
- Rapports mensuels et annuels
- Répartition par sexe, âge, origine
- Export Excel/PDF
- Graphiques interactifs

## Sécurité
- Mots de passe hashés (bcrypt)
- Sessions sécurisées
- Protection CSRF
- Journalisation des actions
- Validation des entrées
- Requêtes SQL préparées (PDO)

## Maintenance

### Sauvegardes
- Effectuer des sauvegardes régulières de la base de données
- Sauvegarder le dossier `uploads/` si utilisé

### Logs
- Consulter régulièrement `logs/php_errors.log`
- Vérifier les journaux d'actions dans l'interface admin

### Mises à jour
- Toujours tester en environnement de développement
- Sauvegarder avant toute mise à jour
- Vérifier la compatibilité PHP/MySQL

## Support
Pour toute question ou problème :
1. Consulter les logs d'erreur
2. Vérifier la configuration de la base de données
3. S'assurer que les permissions des dossiers sont correctes

## Licence
Ce projet est développé spécifiquement pour [Nom de l'organisation].
Tous droits réservés.

---
Version 1.0 - Mai 2024