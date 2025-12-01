<?php
require('fpdf/fpdf.php');
require('../db/paraBDD.php');
$connBDD = $pdo;
if (!isset($_GET['classe'])) {
    die("Classe non fournie.");
}

// Convertir le paramètre classe en tableau s'il ne l'est pas déjà
$classes = is_array($_GET['classe']) ? $_GET['classe'] : [$_GET['classe']];

// Fonction pour générer un mot de passe aléatoire
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

$conn = $connBDD;

// Modifier la requête SQL pour prendre en compte les classes multiples
if (in_array('all', $classes)) {
    $sql = "SELECT e.INE_Eleves, e.Nom, e.Prenom, e.ID_Utilisateur FROM eleves e";
    $stmt = $conn->prepare($sql);
} else {
    $placeholders = str_repeat('?,', count($classes) - 1) . '?';
    $sql = "SELECT e.INE_Eleves, e.Nom, e.Prenom, e.ID_Utilisateur FROM eleves e WHERE e.ID_Classe IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    foreach ($classes as $index => $classeId) {
        $stmt->bindValue($index + 1, $classeId);
    }
}

$stmt->execute();
$eleves = $stmt->fetchAll();

$updatedEleves = [];
foreach ($eleves as $eleve) {
    $newPassword = generatePassword();
    // Hacher le mot de passe avant de l'insérer dans la base de données
    $hashedPassword = $newPassword ;  //password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Mettre à jour le mot de passe dans la base de données avec la version hachée
    $updateSql = "UPDATE utilisateurs SET Mot_de_passe = :password WHERE ID_Utilisateur = :id";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':id', $eleve['ID_Utilisateur']);
    $updateStmt->execute();
    
    // Stocker les informations mises à jour (mot de passe en clair pour le PDF)
    $eleve['Mot_de_passe'] = $newPassword;
    $updatedEleves[] = $eleve;
}

if (count($updatedEleves) > 0) {
    // Créer un nouveau document PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(0, 10, (in_array('all', $classes) ? 'Informations de tous les eleves' : 'Informations des eleves'), 0, 1, 'C', true);
    $pdf->Ln(10);

    foreach ($updatedEleves as $row) {
        // Ajouter le contenu au PDF dans une seule boîte
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'INE: ' . substr($row['INE_Eleves'], 0, 50), 1, 1);
        $pdf->Cell(0, 10, 'Nom: ' . substr($row['Nom'], 0, 50), 1, 1);
        $pdf->Cell(0, 10, 'Prenom: ' . substr($row['Prenom'], 0, 50), 1, 1);
        $pdf->Cell(0, 10, 'Mot de passe: ' . substr($row['Mot_de_passe'], 0, 50), 1, 1);
        $pdf->Ln(10);
    }

    // Modifier le nom du fichier PDF pour refléter la sélection multiple
    $pdfFilename = 'eleves_' . (in_array('all', $classes) ? 'toutes_classes' : 'classes_' . implode('_', $classes)) . '.pdf';
    $pdf->Output('D', $pdfFilename);
} else {
    echo "Aucun enregistrement trouvé.";
}

$conn = null;
?>
