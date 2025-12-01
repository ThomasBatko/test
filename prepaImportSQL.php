<?php
$connBDD = $pdo;
// Vérifier si paraBDD.php n'a pas déjà été inclus
if (!isset($connBDD)) {
    include_once dirname(__FILE__) . '/../../../db/paraBDD.php';
}

// Utilisation de la connexion PDO depuis paraBDD.php
$pdo = $connBDD;

// Configuration de PDO pour afficher les erreurs détaillées
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Fonction pour valider le format des données
 * @param string $data La donnée à valider
 * @return bool True si la donnée est valide, False sinon
 */
function validateData($data) {
    return !empty(trim($data)) && strlen($data) <= 255;
}

// Vérification de la soumission du formulaire et de la présence du fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_file']) && isset($_FILES['xml_file'])) {
    $xml_file = $_FILES['xml_file']['tmp_name'];
    $file_type = $_FILES['xml_file']['type'];

    // Vérification du type MIME du fichier
    if ($file_type !== 'text/xml' && $file_type !== 'application/xml') {
        exit('<div class="alert alert-danger alert-dismissible fade show" role="alert">
                Erreur : Le fichier fourni n\'est pas un XML valide.123456
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>');
    }

    // Vérification et lecture du fichier XML
    if (is_uploaded_file($xml_file) && file_exists($xml_file)) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xml_file);
        
        if ($xml === false) {
            exit('<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Erreur de lecture du fichier XML.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>');
        }

        // Tableaux pour stocker les requêtes et les avertissements
        $queries = [];
        $warnings = [];

        try {
            // Parcours des élèves dans le fichier XML
            foreach ($xml->DONNEES->ELEVES->ELEVE as $eleve) {
                // Validation des données essentielles
                if (!validateData((string)$eleve->ID_NATIONAL) || 
                    !validateData((string)$eleve->NOM_DE_FAMILLE) || 
                    !validateData((string)$eleve->PRENOM)) {
                    throw new Exception('Données invalides pour l\'élève');
                }

                // Vérification si l'élève existe déjà par son INE
                $stmt = $pdo->prepare("SELECT e.ID_Utilisateur FROM eleves e WHERE e.INE_Eleves = ?");
                $stmt->execute([(string)$eleve->ID_NATIONAL]);
                $existing_eleve = $stmt->fetch();

                if ($existing_eleve) {
                    // Stocker l'ID de l'utilisateur existant
                    $id_utilisateur = $existing_eleve['ID_Utilisateur'];
                    $warnings[] = "L'élève avec l'INE " . htmlspecialchars((string)$eleve->ID_NATIONAL) . " existe déjà.";
                } else {
                    // Si l'élève n'existe pas, vérifier si un utilisateur avec le même nom/prénom existe déjà
                    $base_identifiant = strtolower((string) $eleve->PRENOM . '.' . $eleve->NOM_DE_FAMILLE);
                    
                    $stmt = $pdo->prepare("SELECT ID_Utilisateur FROM utilisateurs WHERE Identifiant = ?");
                    $stmt->execute([$base_identifiant]);
                    $existing_user = $stmt->fetch();

                    if ($existing_user) {
                        // Si un utilisateur avec cet identifiant existe déjà, utiliser son ID
                        $id_utilisateur = $existing_user['ID_Utilisateur'];
                    } else {
                        // Créer un nouvel utilisateur uniquement si aucun utilisateur avec cet identifiant n'existe
                        $stmt = $pdo->prepare("INSERT INTO utilisateurs (Identifiant, Email, Mot_de_passe, ID_Profil) VALUES (?, ?, ?, ?)");
                        $email = strtolower($base_identifiant . '@ecole.com');
                        $mot_de_passe = 'bonjour';//password_hash('bonjour', PASSWORD_BCRYPT);
                        $id_profil = 5; // ID du profil élève
                        $stmt->execute([$base_identifiant, $email, $mot_de_passe, $id_profil]);
                        $id_utilisateur = $pdo->lastInsertId();
                    }
                }

                // Trouver la structure correspondante pour cet élève
                $eleve_id = (string)$eleve['ELEVE_ID'];
                $structure = $xml->DONNEES->ELEVES->STRUCTURES_ELEVE[0];
                foreach ($xml->DONNEES->ELEVES->STRUCTURES_ELEVE as $struct) {
                    if ((string)$struct['ELEVE_ID'] === $eleve_id) {
                        $structure = $struct;
                        break;
                    }
                }

                // Comparer la valeur CODE_RNE dans le XML et dans la table etablissements
                $stmt = $pdo->prepare("SELECT ID_Etablissement FROM etablissements WHERE Code_RNE = ?");
                $stmt->execute([(string) $eleve->SCOLARITE_AN_DERNIER->CODE_RNE]);
                $etablissement = $stmt->fetch();

                if ($etablissement) {
                    // Comparer la valeur CODE_STRUCTURE dans le XML et dans la table classes
                    $stmt = $pdo->prepare("SELECT ID_Classe FROM classes WHERE Nom_Classe = ?");
                    $stmt->execute([(string) $structure->STRUCTURE->CODE_STRUCTURE]);
                    $classe = $stmt->fetch();

                    if ($classe) {
                        // Vérifier si l'élève est déjà dans la base de données
                        $stmt = $pdo->prepare("SELECT ID_Eleve FROM eleves WHERE INE_Eleves = ?");
                        $stmt->execute([(string) $eleve->ID_NATIONAL]);
                        $existing_eleve = $stmt->fetch();

                        if ($existing_eleve) {
                            // Mettre à jour les données de l'élève
                            $stmt = $pdo->prepare("UPDATE eleves SET ID_Etablissement = ?, ID_Classe = ?, Nom = ?, Prenom = ?, Deuxieme_Prenom = ?, Troisieme_Prenom = ?, Date_Naissance = STR_TO_DATE(?, '%d/%m/%Y'), Ville_Naissance = ?, Sexe = ? WHERE INE_Eleves = ?");
                            $stmt->execute([
                                (string) $etablissement['ID_Etablissement'],
                                (string) $classe['ID_Classe'],
                                (string) $eleve->NOM_DE_FAMILLE,
                                (string) $eleve->PRENOM,
                                (string) $eleve->PRENOM2,
                                (string) $eleve->PRENOM3,
                                (string) $eleve->DATE_NAISS,
                                (string) $eleve->VILLE_NAISS,
                                (string) $eleve->CODE_SEXE,
                                (string) $eleve->ID_NATIONAL
                            ]);
                            $warnings[] = 'Avertissement : L\'élève avec INE ' . htmlspecialchars($eleve->ID_NATIONAL) . ' a été mis à jour.';
                        } else {
                            // Préparer la requête d'insertion pour les nouveaux élèves
                            $queries[] = [
                                'query' => "INSERT INTO eleves (INE_Eleves, ID_Utilisateur, ID_Etablissement, ID_Classe, Nom, Prenom, Deuxieme_Prenom, Troisieme_Prenom, Date_Naissance, Ville_Naissance, Sexe) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y'), ?, ?)",
                                'params' => [
                                    (string) $eleve->ID_NATIONAL,
                                    $id_utilisateur,
                                    (string) $etablissement['ID_Etablissement'],
                                    (string) $classe['ID_Classe'],
                                    (string) $eleve->NOM_DE_FAMILLE,
                                    (string) $eleve->PRENOM,
                                    (string) $eleve->PRENOM2,
                                    (string) $eleve->PRENOM3,
                                    (string) $eleve->DATE_NAISS,
                                    (string) $eleve->VILLE_NAISS,
                                    (string) $eleve->CODE_SEXE
                                ]
                            ];
                        }
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Erreur : La classe avec CODE_STRUCTURE ' . htmlspecialchars($structure->STRUCTURE->CODE_STRUCTURE) . ' est introuvable dans la base de données.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                        exit;
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Erreur : L\'établissement avec CODE_RNE ' . htmlspecialchars($eleve->SCOLARITE_AN_DERNIER->CODE_RNE) . ' est introuvable dans la base de données.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('Erreur SQL: ' . $e->getMessage());
            exit('<div class="alert alert-danger">Une erreur est survenue lors de l\'accès à la base de données.</div>');
        } catch (Exception $e) {
            error_log('Erreur de validation: ' . $e->getMessage());
            exit('<div class="alert alert-danger">Les données fournies sont invalides.</div>');
        }

        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['queries'] = $queries;
        $_SESSION['warnings'] = $warnings;
    } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Erreur : Le fichier XML est introuvable ou invalide.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}
?>