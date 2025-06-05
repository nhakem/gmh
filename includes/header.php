<?php
// includes/header.php
if (!defined('ROOT_PATH')) {
    die('Accès direct interdit');
}

// Vérifier l'authentification
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
    redirect('login.php', MSG_LOGIN_REQUIRED, 'warning');
}

// Définir le rôle de l'utilisateur pour l'affichage du menu
$userRole = $_SESSION['user_role'] ?? '';
$isAdmin = ($userRole === 'administrateur');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-left-color: white;
        }
        
        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .sidebar-menu .dropdown-menu {
            background: rgba(255,255,255,0.95);
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 5px;
            margin-left: 20px;
            min-width: 200px;
        }
        
        .sidebar-menu .dropdown-item {
            color: #495057;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
        }
        
        .sidebar-menu .dropdown-item:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .sidebar-menu .dropdown-item i {
            width: 18px;
            margin-right: 0.5rem;
        }
        
        .sidebar-menu .dropdown-toggle::after {
            margin-left: auto;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }
        
        .navbar-top {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 1.5rem;
        }
        
        .navbar-top .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .content-wrapper {
            padding: 2rem;
        }
        
        /* Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Forms */
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        /* Menu section headers */
        .menu-section {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 1rem 1.5rem 0.5rem 1.5rem;
            opacity: 0.7;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-hand-holding-heart fa-2x mb-2"></i>
            <h3><?php echo SITE_NAME; ?></h3>
        </div>
        
        <ul class="nav flex-column sidebar-menu">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>dashboard/">
                    <i class="fas fa-tachometer-alt"></i>
                    Tableau de bord
                </a>
            </li>
            
            <!-- Gestion des personnes -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'personnes' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>personnes/">
                    <i class="fas fa-users"></i>
                    Personnes
                </a>
            </li>
            
            <!-- Services -->
            <div class="menu-section">Services</div>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'hebergement' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>hebergement/">
                    <i class="fas fa-bed"></i>
                    Hébergement
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'hebergement_urgence' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>hebergement_urgence/">
                    <i class="fas fa-ambulance"></i>
                    Hébergement d'urgence (HD)
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'repas' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>repas/">
                    <i class="fas fa-utensils"></i>
                    Repas
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'medicaments' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>medicaments/">
                    <i class="fas fa-pills"></i>
                    Médicaments
                </a>
            </li>
            
            <?php if ($isAdmin): ?>
            <!-- Statistiques et rapports -->
            <div class="menu-section">Statistiques</div>
            
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?php echo ($currentPage ?? '') === 'statistiques' ? 'active' : ''; ?>" 
                   href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-chart-bar"></i>
                    Statistiques
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>statistiques/">
                            <i class="fas fa-tachometer-alt"></i>
                            Tableau de bord
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>statistiques/rapports.php">
                            <i class="fas fa-file-alt"></i>
                            Rapports détaillés
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>statistiques/rapport_mensuel.php">
                            <i class="fas fa-table"></i>
                            Rapport mensuel
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>statistiques/export.php">
                            <i class="fas fa-download"></i>
                            Export des données
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- Administration -->
            <div class="menu-section">Administration</div>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'utilisateurs' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>utilisateurs/">
                    <i class="fas fa-user-cog"></i>
                    Utilisateurs
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'logs' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>logs/">
                    <i class="fas fa-history"></i>
                    Journaux
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>maintenance/">
                    <i class="fas fa-tools"></i>
                    Maintenance
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Support et aide -->
            <div class="menu-section">Support</div>
            
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>aide/">
                    <i class="fas fa-question-circle"></i>
                    Aide
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="navbar navbar-top">
            <div class="container-fluid">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="ms-auto user-info">
                    <span class="text-muted">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['user_fullname']); ?>
                        <small class="text-muted">
                            (<?php echo $userRole === 'administrateur' ? 'Admin' : 'Agent'; ?>)
                        </small>
                    </span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle ms-2" 
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog me-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>profil/">
                                    <i class="fas fa-user me-2"></i>
                                    Mon profil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>parametres/">
                                    <i class="fas fa-cog me-2"></i>
                                    Paramètres
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Déconnexion
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Content -->
        <div class="content-wrapper">
            <?php displayFlash(); ?>