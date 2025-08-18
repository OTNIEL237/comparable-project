<?php
require_once 'classes/Database.php';
require_once 'classes/Rapport.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['encadreur', 'admin'])) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

if (isset($_GET['id'])) {
    $rapport_id = intval($_GET['id']);
    $db = Database::getConnection();
    
    $sql = "SELECT r.fichier_pdf, r.titre, u.nom, u.prenom 
            FROM rapports r
            JOIN utilisateurs u ON r.stagiaire_id = u.id
            WHERE r.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $rapport_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rapport = $result->fetch_assoc();
        $file_path = __DIR__ . '/uploads/rapports/' . $rapport['fichier_pdf'];
        
        if (file_exists($file_path)) {
            $file_name = "Rapport_" . $rapport['prenom'] . "_" . $rapport['nom'] . "_" . $rapport['titre'] . ".pdf";
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            readfile($file_path);
            exit;
        }
    }
}

header('HTTP/1.1 404 Not Found');
echo "Fichier non trouvé";