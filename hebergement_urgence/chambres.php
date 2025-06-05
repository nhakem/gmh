<?php
// hebergement_urgence/chambres.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'error');
}

$pageTitle = 'Gérer les chambres d\'urgence (HD) - ' . SITE_NAME;
$currentPage = 'hebergement_urgence';

$db = getDB();

// Récupérer l'ID du type d'hébergement HD
$typeHDSql = "SELECT id FROM types_hebergement WHERE nom = 'HD'";
$typeHD = $db->query($typeHDSql)->fetch();
$typeHDId = $typeHD['id'] ?? null;

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $numero = sanitize($_POST['numero'] ?? '');
        $nombre_lits = intval($_POST['nombre_lits'] ?? 1);
        $tarif = floatval($_POST['tarif_standard'] ?? 0);
        
        if ($numero) {
            try {
                // Vérifier que le numéro n'existe pas déjà
                $checkSql = "SELECT COUNT(*) FROM chambres WHERE numero = :numero";
                $checkStmt = $db->prepare($checkSql);
                $checkStmt->execute([':numero' => $numero]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    $_SESSION['flash_message'] = "Ce numéro de chambre existe déjà.";
                    $_SESSION['flash_type'] = 'error';
                } else {
                    $sql = "INSERT INTO chambres (numero, type_hebergement_id, nombre_lits, tarif_standard, disponible) 
                            VALUES (:numero, :type_id, :nombre_lits, :tarif, TRUE)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':numero' => $numero,
                        ':type_id' => $typeHDId,
                        ':nombre_lits' => $nombre_lits,
                        ':tarif' => $tarif
                    ]);
                    
                    logAction('Ajout chambre HD', 'chambres', $db->lastInsertId(), "Numéro: $numero");
                    $_SESSION['flash_message'] = "Chambre HD ajoutée avec succès.";
                    $_SESSION['flash_type'] = 'success';
                }
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Erreur lors de l'ajout : " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['chambre_id'])) {
        $chambre_id = intval($_POST['chambre_id']);
        $nombre_lits = intval($_POST['nombre_lits'] ?? 1);
        $tarif = floatval($_POST['tarif_standard'] ?? 0);
        
        try {
            $sql = "UPDATE chambres SET nombre_lits = :nombre_lits, tarif_standard = :tarif 
                    WHERE id = :id AND type_hebergement_id = :type_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nombre_lits' => $nombre_lits,
                ':tarif' => $tarif,
                ':id' => $chambre_id,
                ':type_id' => $typeHDId
            ]);
            
            logAction('Modification chambre HD', 'chambres', $chambre_id);
            $_SESSION['flash_message'] = "Chambre HD modifiée avec succès.";
            $_SESSION['flash_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Erreur lors de la modification : " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    redirect('hebergement_urgence/chambres.php');
}

// Gestion de l'activation/désactivation
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        // Vérifier que la chambre n'est pas occupée
        $checkSql = "SELECT COUNT(*) FROM nuitees WHERE chambre_id = :id AND actif = TRUE";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $_SESSION['flash_message'] = "Impossible de désactiver une chambre HD occupée.";
            $_SESSION['flash_type'] = 'error';
        } else {
            $sql = "UPDATE chambres SET disponible = NOT disponible WHERE id = :id AND type_hebergement_id = :type_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id, ':type_id' => $typeHDId]);
            
            logAction('Toggle statut chambre HD', 'chambres', $id);
            $_SESSION['flash_message'] = "Statut de la chambre HD modifié.";
            $_SESSION['flash_type'] = 'success';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erreur : " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    
    redirect('hebergement_urgence/chambres.php');
}

// Récupérer la liste des chambres HD
$chambresSql = "SELECT c.*, 
                (SELECT COUNT(*) FROM nuitees n WHERE n.chambre_id = c.id AND n.actif = TRUE) as est_occupee
                FROM chambres c
                WHERE c.type_hebergement_id = :type_id
                ORDER BY c.numero";
$stmt = $db->prepare($chambresSql);
$stmt->execute([':type_id' => $typeHDId]);
$chambres = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-cog me-2 text-danger"></i>
            Gérer les chambres d'urgence (HD)
        </h1>
        <a href="<?php echo BASE_URL; ?>hebergement_urgence/" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Retour
        </a>
    </div>

    <div class="row">
        <!-- Formulaire d'ajout -->
        <div class="col-lg-4">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-plus-circle me-2 text-danger"></i>
                    Ajouter une chambre HD
                </h5>
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="numero" class="form-label">Numéro de chambre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="numero" name="numero" required 
                               placeholder="Ex: HD-101">
                        <div class="form-text">Format recommandé : HD-XXX</div>
                    </div>
                    <div class="mb-3">
                        <label for="nombre_lits" class="form-label">Nombre de lits</label>
                        <input type="number" class="form-control" id="nombre_lits" name="nombre_lits" 
                               min="1" value="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="tarif_standard" class="form-label">Tarif standard ($/nuit)</label>
                        <input type="number" class="form-control" id="tarif_standard" name="tarif_standard" 
                               min="0" step="0.01" value="0">
                        <div class="form-text">Laisser à 0 pour gratuit</div>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save me-2"></i>
                        Ajouter la chambre HD
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des chambres -->
        <div class="col-lg-8">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-list me-2 text-danger"></i>
                    Chambres d'urgence existantes
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Lits</th>
                                <th>Tarif</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chambres as $chambre): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($chambre['numero']); ?></strong>
                                </td>
                                <td><?php echo $chambre['nombre_lits']; ?></td>
                                <td>
                                    <?php if ($chambre['tarif_standard'] > 0): ?>
                                        <?php echo number_format($chambre['tarif_standard'], 2); ?> $/nuit
                                    <?php else: ?>
                                        <span class="text-success">Gratuit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($chambre['est_occupee']): ?>
                                        <span class="badge bg-danger">Occupée</span>
                                    <?php elseif ($chambre['disponible']): ?>
                                        <span class="badge bg-success">Disponible</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Indisponible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?php echo $chambre['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$chambre['est_occupee']): ?>
                                    <a href="?toggle=1&id=<?php echo $chambre['id']; ?>" 
                                       class="btn btn-sm btn-outline-<?php echo $chambre['disponible'] ? 'warning' : 'success'; ?>"
                                       onclick="return confirm('Voulez-vous vraiment changer le statut de cette chambre HD ?');">
                                        <i class="fas fa-toggle-<?php echo $chambre['disponible'] ? 'on' : 'off'; ?>"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Modal de modification -->
                            <div class="modal fade" id="editModal<?php echo $chambre['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Modifier la chambre <?php echo htmlspecialchars($chambre['numero']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="chambre_id" value="<?php echo $chambre['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Nombre de lits</label>
                                                    <input type="number" class="form-control" name="nombre_lits" 
                                                           min="1" value="<?php echo $chambre['nombre_lits']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Tarif standard ($/nuit)</label>
                                                    <input type="number" class="form-control" name="tarif_standard" 
                                                           min="0" step="0.01" value="<?php echo $chambre['tarif_standard']; ?>">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    Annuler
                                                </button>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-save me-2"></i>
                                                    Enregistrer
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($chambres)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucune chambre d'urgence (HD) n'a été créée pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>