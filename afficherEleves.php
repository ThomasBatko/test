<?php
session_start();

// Inclusion du fichier de configuration de la base de données
include '../db/paraBDD.php';

// Vérification des permissions de l'utilisateur (Admin ou Directeur)
// Si l'utilisateur n'a pas les permissions nécessaires, l'accès est refusé.
if (!isset($_SESSION['ID_Profil']) || !in_array($_SESSION['ID_Profil'], [0, 1])) {
    die("Accès refusé. Vous n'avez pas les permissions nécessaires.");
}

// Titre de la page
$pageTitle = "Liste des Élèves";

// Styles supplémentaires pour la page
$extraStyle = '
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../css/afficherEleves.css" rel="stylesheet">';

/**
 * Fonction pour récupérer la liste des classes depuis la base de données.
 * @param PDO $pdo - Objet PDO pour la connexion à la base de données.
 * @return array - Liste des classes.
 */
function fetchClasses($pdo) {
    try {
        $stmt = $pdo->query("SELECT ID_Classe, Nom_Classe FROM classes");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handleDatabaseError("Erreur lors de la récupération des classes : " . $e->getMessage());
    }
}

/**
 * Fonction pour récupérer les élèves filtrés par classe.
 * @param PDO $pdo - Objet PDO pour la connexion à la base de données.
 * @param string $classFilter - Filtre de classe.
 * @param int $offset - Décalage pour la pagination.
 * @param int $itemsPerPage - Nombre d'éléments par page.
 * @return array - Liste des élèves filtrés.
 */
function fetchFilteredStudents($pdo, $classFilter, $offset, $itemsPerPage) {
    $sql = "SELECT eleves.*, classes.Nom_Classe
            FROM eleves
            LEFT JOIN classes ON eleves.ID_Classe = classes.ID_Classe";

    if ($classFilter !== '') {
        $sql .= " WHERE eleves.ID_Classe = :class";
    }

    $sql .= " LIMIT :offset, :limit";

    try {
        $stmt = $pdo->prepare($sql);

        if ($classFilter !== '') {
            $stmt->bindValue(':class', $classFilter, PDO::PARAM_INT);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handleDatabaseError("Erreur lors de la récupération des élèves : " . $e->getMessage());
    }
}

/**
 * Fonction pour compter le nombre total d'élèves.
 * @param PDO $pdo - Objet PDO pour la connexion à la base de données.
 * @param string $classFilter - Filtre de classe.
 * @return int - Nombre total d'élèves.
 */
function countTotalStudents($pdo, $classFilter) {
    $sqlCount = "SELECT COUNT(*) FROM eleves";
    if ($classFilter !== '') {
        $sqlCount .= " WHERE ID_Classe = :class";
    }

    try {
        $stmtCount = $pdo->prepare($sqlCount);
        if ($classFilter !== '') {
            $stmtCount->bindValue(':class', $classFilter, PDO::PARAM_INT);
        }
        $stmtCount->execute();
        return $stmtCount->fetchColumn();
    } catch (PDOException $e) {
        handleDatabaseError("Erreur lors du comptage des élèves : " . $e->getMessage());
    }
}

/**
 * Fonction pour gérer les erreurs de base de données.
 * @param string $message - Message d'erreur.
 */
function handleDatabaseError($message) {
    error_log($message); // Enregistre l'erreur dans le journal pour le débogage
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

// Récupération des classes depuis la base de données
$classes = fetchClasses($pdo);

// Configuration de la pagination
$itemsPerPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Récupération du filtre de classe depuis les données POST
$classFilter = isset($_POST['classe']) ? $_POST['classe'] : '';

// Récupération des élèves filtrés et du nombre total d'élèves
$students = fetchFilteredStudents($pdo, $classFilter, $offset, $itemsPerPage);
$totalCount = countTotalStudents($pdo, $classFilter);

// Gestion de la sélection de classe pour la réinitialisation des mots de passe
$selectedClass = isset($_POST['classe']) ? htmlspecialchars($_POST['classe']) : '';
$confirmScript = '';
if ($selectedClass === '') {
    $confirmScript = 'onclick="return confirm(\'Attention ! Vous allez réinitialiser les mots de passe pour TOUS les élèves. Voulez-vous continuer ?\');"';
    $selectedClass = 'all';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php echo $extraStyle; ?>
</head>
<body>
    <!-- En-tête de la page -->
    <?php include '../view/header.php'; include '../view/breadcrumb.php'; ?>

    <!-- Conteneur principal -->
    <div class="wrapper" style="margin-top: 120px; margin-bottom: 100px;">
        <div class="container">

            <!-- En-tête de la section -->
            <div class="header bg-primary text-white py-3 rounded">
                <h2 class="h4 mb-0 text-center">Liste des Élèves</h2>
            </div>

            <!-- Contenu principal -->
            <div class="card" style="background-color: #009ca0">
                <div class="card-body">
                    <!-- Formulaire de filtrage par classe -->
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="classe" class="form-label text-white">Filtrer par classe:</label>
                            <select name="classe" id="classe" class="form-select">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <!-- Options de classe générées dynamiquement -->
                                    <option value="<?= htmlspecialchars($classe['ID_Classe']) ?>">
                                        <?= htmlspecialchars($classe['Nom_Classe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Bouton pour appliquer le filtre -->
                        <button type="submit" class="btn mb-3" style="background-color: #c5e7e7">Filtrer</button>
                        <!-- Lien pour réinitialiser les mots de passe -->
                        <a href="generation_pdf_classe.php?classe[]=<?= $selectedClass ?>"
                           class="btn btn-secondary mb-3 ms-2"
                           <?= $confirmScript ?>>Réinitialiser les mots de passe</a>
                    </form>

                    <!-- Tableau des élèves -->
                    <div class="table-responsive">
                        <?php if ($students): ?>
                            <table class="table table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>INE</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Prénom2</th>
                                        <th>Prénom3</th>
                                        <th>Date de Naissance</th>
                                        <th>Ville de Naissance</th>
                                        <th>Sexe</th>
                                        <th>Classe</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <!-- Ligne de données pour chaque élève -->
                                        <tr>
                                            <td><?= htmlspecialchars($student['INE_Eleves'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Nom'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Prenom'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Deuxieme_Prenom'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Troisieme_Prenom'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Date_Naissance'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Ville_Naissance'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Sexe'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($student['Nom_Classe'] ?? '') ?></td>
                                            <td>
                                                <!-- Lien pour générer un PDF pour chaque élève -->
                                                <a href="generate_pdf.php?ine=<?= htmlspecialchars($student['INE_Eleves']) ?>" class="btn btn-primary">
                                                    Générer PDF
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <?php
                            $totalPages = ceil($totalCount / $itemsPerPage);
                            if ($totalPages > 1):
                            ?>
                                <nav>
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                                <a class="page-link text-black" style="background-color: #c5e7e7" href="?page=<?= $i ?>&classe=<?= htmlspecialchars($classFilter) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Message si aucun élève n'est trouvé -->
                            <div class="alert alert-info" role="alert">Aucun élève trouvé.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pied de page -->
    <footer>
        <?php include '../view/footer.php'; ?>
    </footer>

    <!-- Scripts JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>
