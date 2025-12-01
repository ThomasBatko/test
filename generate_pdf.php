<?php
require('fpdf/fpdf.php'); // Inclure la bibliothèque FPDF
require('../db/paraBDD.php'); // Inclure les paramètres de connexion à la base de données
$connBDD = $pdo;
// Vérifier si l'INE est fourni
if (!isset($_GET['ine'])) {
    die("INE non fourni.");
}

$ine = $_GET['ine'];

// Connexion à la base de données
$conn = $connBDD;

// Récupérer l'INE, le nom et l'ID utilisateur de l'élève
$sql = "SELECT e.INE_Eleves, e.Nom, e.Prenom, e.ID_Utilisateur FROM eleves e WHERE e.INE_Eleves = :ine";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':ine', $ine);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Générer un nouveau mot de passe aléatoire
    $nouveauMotDePasse = bin2hex(random_bytes(4)); // Génère un mot de passe de 8 caractères
    
    // Hacher le mot de passe avant de le stocker dans la base de données
    $motDePasseHache = $nouveauMotDePasse;     //  password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe dans la base de données
    $updateSql = "UPDATE utilisateurs SET Mot_de_passe = :mdp WHERE ID_Utilisateur = :id";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bindParam(':mdp', $motDePasseHache);
    $updateStmt->bindParam(':id', $row['ID_Utilisateur']);
    $updateStmt->execute();

    // Créer un nouveau document PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(0, 10, 'Informations de l\'eleve', 0, 1, 'C', true);
    $pdf->Ln(10);

    // Ajouter le contenu au PDF dans une seule boîte
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'INE: ' . substr($row['INE_Eleves'], 0, 50), 1, 1);
    $pdf->Cell(0, 10, 'Nom: ' . substr($row['Nom'], 0, 50), 1, 1);
    $pdf->Cell(0, 10, 'Prenom: ' . substr($row['Prenom'], 0, 50), 1, 1);
    $pdf->Cell(0, 10, 'Nouveau mot de passe: ' . $nouveauMotDePasse, 1, 1);

    // Générer le PDF
    $pdf->Output('D', 'eleve_password_' . $row['INE_Eleves'] . '.pdf');
} else {
    echo "Aucun enregistrement trouvé.";
}

$conn = null;
?>
