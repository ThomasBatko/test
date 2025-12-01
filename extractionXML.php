<?php
// Vérification de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['xml_file'])) {
    $xml_file = $_FILES['xml_file']['tmp_name'];

    if (file_exists($xml_file) && mime_content_type($xml_file) === 'application/xml') {
        try {
            $xml = simplexml_load_file($xml_file);

            // Initialisation des tableaux pour stocker les données extraites
            $eleves = [];
            $options = [];
            $structures = [];
            $bourses = [];
            $eleveMap = [];

            // Fonction pour extraire les informations des élèves
            function extractEleves($xml, &$eleves, &$eleveMap) {
                if (isset($xml->DONNEES->ELEVES->ELEVE)) {
                    foreach ($xml->DONNEES->ELEVES->ELEVE as $eleve) {
                        $eleveData = extractEleveData($eleve);
                        $eleves[] = $eleveData;
                        $eleveMap[$eleveData['ELEVE_ID']] = $eleveData['ID_NATIONAL'];
                    }
                }
            }

            // Fonction pour extraire les données d'un élève
            function extractEleveData($eleve) {
                return [
                    'ELEVE_ID' => (string)$eleve['ELEVE_ID'],
                    'ID_NATIONAL' => (string)$eleve->ID_NATIONAL,
                    'ID_ELEVE_ETAB' => (string)$eleve->ID_ELEVE_ETAB,
                    'NOM' => (string)$eleve->NOM_DE_FAMILLE,
                    'PRENOM' => (string)$eleve->PRENOM,
                    'PRENOM2' => (string)$eleve->PRENOM2,
                    'PRENOM3' => (string)$eleve->PRENOM3,
                    'DATE_NAISSANCE' => (string)$eleve->DATE_NAISS,
                    'DOUBLEMENT' => (string)$eleve->DOUBLEMENT,
                    'CODE_PAYS' => (string)$eleve->CODE_PAYS,
                    'EMAIL' => (string)$eleve->MEL,
                    'CODE_REGIME' => (string)$eleve->CODE_REGIME,
                    'DATE_ENTREE' => (string)$eleve->DATE_ENTREE,
                    'VILLE_NAISS' => (string)$eleve->VILLE_NAISS,
                    'ADRESSE_ID' => (string)$eleve->ADRESSE_ID,
                    'CODE_SEXE' => (string)$eleve->CODE_SEXE,
                    'TEL_PORTABLE' => (string)$eleve->TEL_PORTABLE,
                    'CODE_PAYS_NAT' => (string)$eleve->CODE_PAYS_NAT,
                    'CODE_STATUT' => (string)$eleve->CODE_STATUT,
                    'CODE_MEF' => (string)$eleve->CODE_MEF,
                    'CODE_RNE' => (string)$eleve->CODE_RNE,
                    'CODE_DEPARTEMENT_NAISS' => (string)$eleve->CODE_DEPARTEMENT_NAISS,
                    'CODE_COMMUNE_INSEE_NAISS' => (string)$eleve->CODE_COMMUNE_INSEE_NAISS,
                    'CODE_STRUCTURE' => (string)$eleve->CODE_STRUCTURE,
                ];
            }

            // Fonction pour extraire les options des élèves
            function extractOptions($xml, &$options, $eleveMap) {
                if (isset($xml->DONNEES->ELEVES->OPTION)) {
                    foreach ($xml->DONNEES->ELEVES->OPTION as $option) {
                        $eleve_id = (string)$option['ELEVE_ID'];
                        if (isset($eleveMap[$eleve_id])) {
                            $options[] = [
                                'ELEVE_ID' => $eleve_id,
                                'ID_NATIONAL' => $eleveMap[$eleve_id],
                                'NUM_OPTION' => (string)$option->OPTIONS_ELEVE->NUM_OPTION,
                                'CODE_MODALITE_ELECT' => (string)$option->OPTIONS_ELEVE->CODE_MODALITE_ELECT,
                                'CODE_MATIERE' => (string)$option->OPTIONS_ELEVE->CODE_MATIERE
                            ];
                        }
                    }
                }
            }

            // Fonction pour extraire les structures des élèves
            function extractStructures($xml, &$structures, $eleveMap) {
                if (isset($xml->DONNEES->ELEVES->STRUCTURES_ELEVE)) {
                    foreach ($xml->DONNEES->ELEVES->STRUCTURES_ELEVE as $structure) {
                        $eleve_id = (string)$structure['ELEVE_ID'];
                        if (isset($eleveMap[$eleve_id])) {
                            $structures[] = [
                                'ELEVE_ID' => $eleve_id,
                                'ID_NATIONAL' => $eleveMap[$eleve_id],
                                'CODE_STRUCTURE' => (string)$structure->STRUCTURE->CODE_STRUCTURE,
                                'TYPE_STRUCTURE' => (string)$structure->STRUCTURE->TYPE_STRUCTURE
                            ];
                        }
                    }
                }
            }

            // Fonction pour extraire les bourses des élèves
            function extractBourses($xml, &$bourses, $eleveMap) {
                if (isset($xml->DONNEES->ELEVES->BOURSE)) {
                    foreach ($xml->DONNEES->ELEVES->BOURSE as $bourse) {
                        $eleve_id = (string)$bourse['ELEVE_ID'];
                        if (isset($eleveMap[$eleve_id])) {
                            $bourses[] = [
                                'ELEVE_ID' => $eleve_id,
                                'ID_NATIONAL' => $eleveMap[$eleve_id],
                                'CODE_BOURSE' => (string)$bourse->CODE_BOURSE
                            ];
                        }
                    }
                }
            }

            // Extraction des données
            extractEleves($xml, $eleves, $eleveMap);
            extractOptions($xml, $options, $eleveMap);
            extractStructures($xml, $structures, $eleveMap);
            extractBourses($xml, $bourses, $eleveMap);

        } catch (Exception $e) {
            error_log("Erreur lors du chargement du fichier XML : " . $e->getMessage());
            echo "Erreur lors du traitement du fichier XML.";
        }
    } else {
        echo "Erreur : le fichier XML est introuvable ou n'est pas un fichier XML valide.";
    }
}
?>
