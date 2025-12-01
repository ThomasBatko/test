<?php
session_start();

// Génération d'un token CSRF si inexistant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérification des permissions (Admin ou Directeur)
if (!isset($_SESSION['ID_Profil']) || !in_array($_SESSION['ID_Profil'], [0, 1])) {
    die("Accès refusé. Vous n'avez pas les permissions nécessaires.");
}

// Inclusions des fichiers de configuration et de traitement
include '../db/paraBDD.php';
include 'prepaImportSQL.php';
include 'extractionXML.php';

$pageTitle = "Import élèves";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Passeport Promotion de la Santé</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/importXML.css" rel="stylesheet">
    <?php if (isset($extraStyle)) echo $extraStyle; ?>
</head>
<body>
    <!-- Inclusion du header -->
    <?php include '../view/header.php'; ?>
    <?php include '../view/breadcrumb.php'; ?>

    <!-- Wrapper pour le contenu principal -->
    <div class="wrapper mt-5 pt-5">
        <div class="container">
            <div class="text-white text-center py-3 rounded mb-4" style="background-color: #009CA0;">
                <h2 class="h5 mb-0 text-white">Importation depuis un fichier XML</h2>
            </div>

            <div class="card shadow-sm mb-4" style="background-color: #009CA0">
                <div class="card-body">
                    <!-- Formulaire d'importation -->
                    <form method="post" enctype="multipart/form-data" id="importForm" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3">
                            <label for="xml_file" class="form-label fw-bold text-white">Sélectionnez un fichier XML :</label>
                            <div class="input-group">
                                <input type="file" name="xml_file" id="xml_file" accept=".xml" class="form-control" required>
                                <button type="submit" name="import_file" class="btn" style="background-color: #c5e7e7">
                                    <i class="fas fa-upload me-2"></i>Importer
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Conteneur pour les alertes -->
                    <div id="alerts-container">
                        <?php
                        // Vérification du token CSRF
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                                die('Invalid CSRF token');
                            }
                        }

                        /**
                         * Compte le nombre d'élèves dans un fichier XML.
                         * @param string $xml_file - Chemin du fichier XML.
                         * @return int - Nombre d'élèves.
                         */
                        function countImportedStudentsFromFile(string $xml_file): int {
                            libxml_use_internal_errors(true);
                            $xml = simplexml_load_file($xml_file);
                            if ($xml === false) {
                                return 0;
                            }
                            return count($xml->DONNEES->ELEVES->ELEVE);
                        }

                        /**
                         * Compte le nombre total d'élèves dans la base de données.
                         * @param PDO $pdo - Objet PDO pour la connexion à la base de données.
                         * @return int - Nombre total d'élèves.
                         */
                        function countTotalStudentsInDatabase(PDO $pdo): int {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM eleves");
                            return $stmt->fetchColumn();
                        }

                        // Comptage des élèves avant l'import
                        $initialCount = countTotalStudentsInDatabase($pdo);

                        // Traitement du fichier XML si soumis
                        if (isset($_POST['import_file']) && isset($_FILES['xml_file'])) {
                            $xml_file = $_FILES['xml_file']['tmp_name'];
                            $file_extension = pathinfo($_FILES['xml_file']['name'], PATHINFO_EXTENSION);

                            // Vérification de l'extension du fichier
                            if ($file_extension !== 'xml') {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Erreur : Le fichier fourni n\'est pas un XML valide123456.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                            } else {
                                // Vérification du contenu du fichier
                                libxml_use_internal_errors(true);
                                $xml = simplexml_load_file($xml_file);
                                if ($xml === false) {
                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Erreur : Le fichier fourni n\'est pas un XML valideppppppppp.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                                } else {
                                    if (!empty($_SESSION['queries'])) {
                                        try {
                                            // Début de la transaction
                                            $pdo->beginTransaction();
                                            foreach ($_SESSION['queries'] as $item) {
                                                $stmt = $pdo->prepare($item['query']);
                                                $stmt->execute($item['params']);
                                            }
                                            // Validation de la transaction
                                            $pdo->commit();
                                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Données importées avec succès.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                                            unset($_SESSION['queries']);

                                            // Comptage des élèves après l'import
                                            $finalCount = countTotalStudentsInDatabase($pdo);
                                            $importedCount = $finalCount - $initialCount;
                                            echo '<div class="alert alert-info alert-dismissible fade show" role="alert">Nombre d\'élèves importés : ' . $importedCount . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                                        } catch (PDOException $e) {
                                            // Annulation de la transaction en cas d'erreur
                                            if ($pdo->inTransaction()) {
                                                $pdo->rollBack();
                                            }
                                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Erreur lors de l\'importation : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                                        }
                                    }
                                }
                            }
                        }

                        // Affichage des alertes pour les élèves déjà présents
                        if (!empty($_SESSION['warnings'])) {
                            foreach ($_SESSION['warnings'] as $warning) {
                                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">' . htmlspecialchars($warning) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                            }
                            unset($_SESSION['warnings']);
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Modal pour la mise à jour des élèves -->
            <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="updateModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>Avertissement
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Voulez-vous mettre à jour les informations de cet élève ?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="continueImport()">
                                <i class="fas fa-times me-2"></i>Ignorer
                            </button>
                            <button type="button" class="btn btn-primary" onclick="updateStudent()">
                                <i class="fas fa-check me-2"></i>Mettre à jour
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function continueImport() {
                    var modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                    if (modal) {
                        modal.hide();
                    }
                }

                function updateStudent() {
                    var ine = document.querySelector('[name="ine"]')?.value || '';
                    var modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                    if (modal) {
                        modal.hide();
                    }
                    window.location.href = window.location.pathname + "?update=yes&ine=" + ine;
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.pathname;
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'update';
                    input.value = 'yes';
                    form.appendChild(input);
                    var ineInput = document.createElement('input');
                    ineInput.type = 'hidden';
                    ineInput.name = 'ine';
                    ineInput.value = ine;
                    form.appendChild(ineInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            </script>
        </div>
    </div>

    <!-- Footer -->
    <?php include "../view/footer.php"; ?>

    <!-- Bootstrap JS et Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
