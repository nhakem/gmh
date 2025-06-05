<?php
// hebergement/chambres.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Gestion des chambres - ' . SITE_NAME;
$currentPage = 'hebergement';

$db = getDB();

// Récupérer les types d'hébergement
$typesHebergement = $db->query("SELECT * FROM types_hebergement ORDER BY id")->fetchAll();

// Traitement du formulaire d'ajout de chambre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ajouter_chambre') {
        $data = [
            'numero' => sanitize($_POST['numero'] ?? ''),
            'type_hebergement_id' => intval($_POST['type_hebergement_id'] ?? 0),
            'nombre_lits' => intval($_POST['nombre_lits'] ?? 1),
            'tarif_standard' => floatval($_POST['tarif_standard'] ?? 0)
        ];
        
        // Validation
        $errors = [];
        if (empty($data['numero'])) {
            $errors[] = "Le numéro de chambre est obligatoire.";
        }
        if (!$data['type_hebergement_id']) {
            $errors[] = "Le type d'hébergement est obligatoire.";
        }
        if ($data['nombre_lits'] < 1) {
            $errors[] = "Le nombre de lits doit être au moins 1.";
        }
        
        // Vérifier l'unicité du numéro
        $checkSql = "SELECT COUNT(*) FROM chambres WHERE numero = :numero";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':numero' => $data['numero']]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Ce numéro de chambre existe déjà.";
        }
        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO chambres (numero, type_hebergement_id, nombre_lits, tarif_standard, disponible) 
                        VALUES (:numero, :type_hebergement_id, :nombre_lits, :tarif_standard, TRUE)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':numero' => $data['numero'],
                    ':type_hebergement_id' => $data['type_hebergement_id'],
                    ':nombre_lits' => $data['nombre_lits'],
                    ':tarif_standard' => $data['tarif_standard']
                ]);
                
                logAction('Ajout chambre', 'chambres', $db->lastInsertId(), "Nouvelle chambre: " . $data['numero']);
                redirect('hebergement/chambres.php', 'Chambre ajoutée avec succès.', 'success');
                
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    }
    
    // Ajout d'un type d'hébergement
    elseif ($_POST['action'] === 'ajouter_type') {
        $nom = sanitize($_POST['nom_type'] ?? '');
        $description = sanitize($_POST['description_type'] ?? '');
        
        if (empty($nom)) {
            $errors[] = "Le nom du type d'hébergement est obligatoire.";
        } else {
            try {
                $sql = "INSERT INTO types_hebergement (nom, description) VALUES (:nom, :description)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':nom' => $nom,
                    ':description' => $description
                ]);
                
                redirect('hebergement/chambres.php', 'Type d\'hébergement ajouté avec succès.', 'success');
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de l'ajout du type : " . $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des chambres
$chambresSql = "SELECT c.*, th.nom as type_hebergement_nom,
                (SELECT COUNT(*) FROM nuitees WHERE chambre_id = c.id AND actif = TRUE) as occupee
                FROM chambres c
                JOIN types_hebergement th ON c.type_hebergement_id = th.id
                ORDER BY th.id, c.numero";
$chambres = $db->query($chambresSql)->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-door-open me-2 text-primary"></i>
            Gestion des chambres
        </h1>
        <a href="<?php echo BASE_URL; ?>hebergement/" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Retour
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Erreurs :</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulaire d'ajout de chambre -->
        <div class="col-lg-4">
            <div class="table-container mb-4">
                <h5 class="mb-3">
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Ajouter une chambre
                </h5>
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="ajouter_chambre">
                    
                    <div class="mb-3">
                        <label for="numero" class="form-label">Numéro de chambre <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="numero" 
                               name="numero" 
                               required
                               placeholder="Ex: 101, A-12">
                        <div class="invalid-feedback">
                            Le numéro est obligatoire.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_hebergement_id" class="form-label">Type d'hébergement <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_hebergement_id" name="type_hebergement_id" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($typesHebergement as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner un type.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre_lits" class="form-label">Nombre de lits <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="nombre_lits" 
                               name="nombre_lits" 
                               min="1" 
                               value="1"
                               required>
                        <div class="invalid-feedback">
                            Minimum 1 lit.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tarif_standard" class="form-label">Tarif standard par jour ($)</label>
                        <input type="number" 
                               class="form-control" 
                               id="tarif_standard" 
                               name="tarif_standard" 
                               min="0" 
                               step="0.01"
                               value="0.00"
                               placeholder="0.00">
                        <small class="text-muted">Laissez 0 pour les chambres gratuites</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter la chambre
                    </button>
                </form>
            </div>

            <!-- Formulaire d'ajout de type -->
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-tags me-2 text-primary"></i>
                    Types d'hébergement
                </h5>
                
                <div class="mb-3">
                    <?php foreach ($typesHebergement as $type): ?>
                        <div class="badge bg-secondary me-1 mb-1">
                            <?php echo htmlspecialchars($type['nom']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="ajouter_type">
                    
                    <div class="mb-3">
                        <label for="nom_type" class="form-label">Nouveau type</label>
                        <input type="text" 
                               class="form-control" 
                               id="nom_type" 
                               name="nom_type" 
                               placeholder="Ex: Urgence">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_type" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description_type" 
                                  name="description_type" 
                                  rows="2"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter le type
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des chambres -->
        <div class="col-lg-8">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-list me-2 text-primary"></i>
                    Liste des chambres
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Type</th>
                                <th>Lits</th>
                                <th>Tarif/jour</th>
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
                                <td><?php echo htmlspecialchars($chambre['type_hebergement_nom']); ?></td>
                                <td><?php echo $chambre['nombre_lits']; ?></td>
                                <td>
                                    <?php if ($chambre['tarif_standard'] > 0): ?>
                                        <strong><?php echo number_format($chambre['tarif_standard'], 2); ?> $</strong>
                                    <?php else: ?>
                                        <span class="badge bg-success">Gratuit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($chambre['occupee']): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-user"></i> Occupée
                                        </span>
                                    <?php elseif (!$chambre['disponible']): ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-tools"></i> Indisponible
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Disponible
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if (!$chambre['occupee']): ?>
                                            <button onclick="toggleDisponibilite(<?php echo $chambre['id']; ?>, <?php echo $chambre['disponible'] ? 'false' : 'true'; ?>)" 
                                                    class="btn btn-outline-<?php echo $chambre['disponible'] ? 'warning' : 'success'; ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?php echo $chambre['disponible'] ? 'Rendre indisponible' : 'Rendre disponible'; ?>">
                                                <i class="fas fa-<?php echo $chambre['disponible'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                            
                                            <button onclick="if(confirm('Supprimer cette chambre ?')) window.location='<?php echo BASE_URL; ?>hebergement/supprimer_chambre.php?id=<?php echo $chambre['id']; ?>'" 
                                                    class="btn btn-outline-danger"
                                                    data-bs-toggle="tooltip"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDisponibilite(chambreId, disponible) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo BASE_URL; ?>hebergement/toggle_chambre.php';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'chambre_id';
    idInput.value = chambreId;
    
    const disponibleInput = document.createElement('input');
    disponibleInput.type = 'hidden';
    disponibleInput.name = 'disponible';
    disponibleInput.value = disponible;
    
    form.appendChild(idInput);
    form.appendChild(disponibleInput);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once '../includes/footer.php'; ?>