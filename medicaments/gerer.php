<?php
// medicaments/gerer.php
require_once '../config.php';
require_once '../includes/classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
    redirect('login.php', MSG_PERMISSION_DENIED, 'danger');
}

$pageTitle = 'Gérer les médicaments - ' . SITE_NAME;
$currentPage = 'medicaments';

$db = getDB();

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom' => sanitize($_POST['nom'] ?? ''),
        'forme' => sanitize($_POST['forme'] ?? ''),
        'description' => sanitize($_POST['description'] ?? '')
    ];
    
    // Validation
    $errors = [];
    if (empty($data['nom'])) {
        $errors[] = "Le nom du médicament est obligatoire.";
    }
    
    // Vérifier l'unicité
    $checkSql = "SELECT COUNT(*) FROM medicaments WHERE nom = :nom";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':nom' => $data['nom']]);
    if ($checkStmt->fetchColumn() > 0) {
        $errors[] = "Ce médicament existe déjà.";
    }
    
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO medicaments (nom, forme, description) VALUES (:nom, :forme, :description)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nom' => $data['nom'],
                ':forme' => $data['forme'],
                ':description' => $data['description']
            ]);
            
            logAction('Ajout médicament', 'medicaments', $db->lastInsertId(), "Nouveau: " . $data['nom']);
            redirect('medicaments/gerer.php', 'Médicament ajouté avec succès.', 'success');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    }
}

// Récupérer la liste des médicaments avec statistiques
$sql = "SELECT m.*, 
        (SELECT COUNT(*) FROM prescriptions p WHERE p.medicament_id = m.id) as nombre_prescriptions,
        (SELECT COUNT(DISTINCT p.personne_id) FROM prescriptions p WHERE p.medicament_id = m.id) as nombre_patients,
        (SELECT COUNT(*) FROM prescriptions p WHERE p.medicament_id = m.id AND p.date_debut <= CURDATE() AND (p.date_fin IS NULL OR p.date_fin >= CURDATE())) as prescriptions_actives
        FROM medicaments m
        ORDER BY m.nom";

$medicaments = $db->query($sql)->fetchAll();

require_once '../includes/header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-capsules me-2 text-primary"></i>
            Gérer les médicaments
        </h1>
        <a href="<?php echo BASE_URL; ?>medicaments/" class="btn btn-outline-secondary">
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
        <!-- Formulaire d'ajout -->
        <div class="col-lg-4">
            <div class="table-container mb-4">
                <h5 class="mb-3">
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Ajouter un médicament
                </h5>
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom du médicament <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="nom" 
                               name="nom" 
                               required
                               placeholder="Ex: Paracétamol">
                        <div class="invalid-feedback">
                            Le nom est obligatoire.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="forme" class="form-label">Forme galénique</label>
                        <input type="text" 
                               class="form-control" 
                               id="forme" 
                               name="forme" 
                               placeholder="Ex: Comprimé, Sirop, Gélule">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Informations supplémentaires..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter le médicament
                    </button>
                </form>
            </div>

            <!-- Statistiques globales -->
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                    Statistiques
                </h5>
                <?php
                $statsSql = "SELECT 
                    COUNT(DISTINCT id) as total_medicaments,
                    (SELECT COUNT(DISTINCT personne_id) FROM prescriptions) as patients_total,
                    (SELECT COUNT(*) FROM prescriptions WHERE date_debut <= CURDATE() AND (date_fin IS NULL OR date_fin >= CURDATE())) as prescriptions_actives_total
                    FROM medicaments";
                $statsGlobales = $db->query($statsSql)->fetch();
                ?>
                <div class="text-center">
                    <div class="mb-3">
                        <h4 class="text-primary mb-1"><?php echo $statsGlobales['total_medicaments']; ?></h4>
                        <small class="text-muted">Médicaments référencés</small>
                    </div>
                    <div class="mb-3">
                        <h4 class="text-info mb-1"><?php echo $statsGlobales['patients_total']; ?></h4>
                        <small class="text-muted">Patients traités</small>
                    </div>
                    <div>
                        <h4 class="text-success mb-1"><?php echo $statsGlobales['prescriptions_actives_total']; ?></h4>
                        <small class="text-muted">Prescriptions actives</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des médicaments -->
        <div class="col-lg-8">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-list me-2 text-primary"></i>
                    Médicaments référencés
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Forme</th>
                                <th>Utilisation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicaments as $med): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($med['nom']); ?></strong>
                                    <?php if ($med['description']): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars(substr($med['description'], 0, 50)) . '...'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($med['forme'] ?: '-'); ?></td>
                                <td>
                                    <small>
                                        <?php echo $med['nombre_patients']; ?> patient<?php echo $med['nombre_patients'] > 1 ? 's' : ''; ?><br>
                                        <?php echo $med['prescriptions_actives']; ?> actif<?php echo $med['prescriptions_actives'] > 1 ? 's' : ''; ?> / 
                                        <?php echo $med['nombre_prescriptions']; ?> total
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button onclick="editMedicament(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars($med['nom'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($med['forme'] ?? '', ENT_QUOTES); ?>')" 
                                                class="btn btn-outline-primary"
                                                data-bs-toggle="tooltip"
                                                title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($med['prescriptions_actives'] == 0): ?>
                                            <button onclick="if(confirm('Supprimer ce médicament ?')) window.location='<?php echo BASE_URL; ?>medicaments/supprimer_medicament.php?id=<?php echo $med['id']; ?>'" 
                                                    class="btn btn-outline-danger"
                                                    data-bs-toggle="tooltip"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled
                                                    data-bs-toggle="tooltip"
                                                    title="Suppression impossible - Prescriptions actives">
                                                <i class="fas fa-ban"></i>
                                            </button>
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

<!-- Modal de modification -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le médicament</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>medicaments/modifier_medicament.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_forme" class="form-label">Forme</label>
                        <input type="text" class="form-control" id="edit_forme" name="forme">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMedicament(id, nom, forme) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nom').value = nom;
    document.getElementById('edit_forme').value = forme;
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>