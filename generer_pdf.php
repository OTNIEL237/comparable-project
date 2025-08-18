<?php
session_start();
require_once 'classes/Database.php';
require_once 'classes/Rapport.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier si un ID de rapport est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboardStagiaire.php?tab=rapports');
    exit();
}

$user_id = $_SESSION['user_id'];
$rapport_id = (int)$_GET['id'];

try {
    // Créer une instance de Rapport
    $rapport_obj = new Rapport($user_id);
    $rapport = $rapport_obj->getRapportById($rapport_id);

    if (!$rapport) {
        throw new Exception("Rapport non trouvé");
    }

    // Vérifier si un PDF existe déjà
    if ($rapport['fichier_pdf'] && file_exists(__DIR__ . '/uploads/rapports/' . $rapport['fichier_pdf'])) {
        $chemin_pdf = __DIR__ . '/uploads/rapports/' . $rapport['fichier_pdf'];
        $nom_fichier = $rapport['fichier_pdf'];
    } else {
        // Vérifier si TCPDF est disponible
        if (!file_exists(__DIR__ . '/TCPDF/tcpdf.php')) {
            throw new Exception("TCPDF n'est pas installé. Impossible de générer le PDF.");
        }

        require_once __DIR__ . '/TCPDF/tcpdf.php';

        // Récupérer les informations du stagiaire
        $conn = Database::getConnection();
        $sql = "SELECT u.nom, u.prenom, u.email,
                       ue.nom AS enc_nom, ue.prenom AS enc_prenom
                FROM utilisateurs u
                LEFT JOIN stagiaire s ON u.id = s.id_utilisateur
                LEFT JOIN utilisateurs ue ON s.encadreur_id = ue.id
                WHERE u.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $info_stagiaire = $stmt->get_result()->fetch_assoc();

        if (!$info_stagiaire) {
            throw new Exception("Informations du stagiaire non trouvées");
        }

        // Créer le PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Système de Gestion des Stagiaires');
        $pdf->SetAuthor($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']);
        $pdf->SetTitle('Rapport de stage - ' . $rapport['titre']);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        
        // Générer le contenu HTML
        $date_actuelle = date('d/m/Y', strtotime($rapport['date_soumission']));
        $type_formate = ucfirst($rapport['type']);
        
        $html = '
        <style>
            .header { text-align: center; margin-bottom: 30px; }
            .title { font-size: 18px; font-weight: bold; color: #0d47a1; }
            .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
            .info-box { background-color: #f5f5f5; padding: 15px; margin: 20px 0; }
            .section { margin: 20px 0; }
            .section-title { font-size: 14px; font-weight: bold; color: #0d47a1; border-bottom: 1px solid #0d47a1; padding-bottom: 5px; margin-bottom: 10px; }
            .content { line-height: 1.6; text-align: justify; }
        </style>
        
        <div class="header">
            <div class="title">RAPPORT DE STAGE ' . strtoupper($type_formate) . '</div>
            <div class="subtitle">' . htmlspecialchars($rapport['titre']) . '</div>
        </div>
        
        <div class="info-box">
            <strong>Stagiaire :</strong> ' . htmlspecialchars($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']) . '<br>
            <strong>Encadreur :</strong> ' . htmlspecialchars(($info_stagiaire['enc_prenom'] ?? '') . ' ' . ($info_stagiaire['enc_nom'] ?? '')) . '<br>
            <strong>Date de soumission :</strong> ' . $date_actuelle . '<br>
            <strong>Type de rapport :</strong> ' . $type_formate . '<br>
            <strong>Statut :</strong> ' . ucfirst($rapport['statut']) . '
        </div>
        
        <div class="section">
            <div class="section-title">ACTIVITÉS RÉALISÉES</div>
            <div class="content">' . nl2br(htmlspecialchars($rapport['activites'])) . '</div>
        </div>
        
        <div class="section">
            <div class="section-title">DIFFICULTÉS RENCONTRÉES</div>
            <div class="content">' . nl2br(htmlspecialchars($rapport['difficultes'])) . '</div>
        </div>
        
        <div class="section">
            <div class="section-title">SOLUTIONS APPORTÉES</div>
            <div class="content">' . nl2br(htmlspecialchars($rapport['solutions'])) . '</div>
        </div>';

        if ($rapport['commentaire_encadreur']) {
            $html .= '
            <div class="section">
                <div class="section-title">COMMENTAIRE DE L\'ENCADREUR</div>
                <div class="content">' . nl2br(htmlspecialchars($rapport['commentaire_encadreur'])) . '</div>
            </div>';
        }

        $html .= '
        <div style="margin-top: 40px; text-align: right;">
            <p><strong>Signature du stagiaire</strong></p>
            <p style="margin-top: 20px;">_________________________</p>
            <p>' . htmlspecialchars($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']) . '</p>
        </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Générer un nom de fichier temporaire
        $nom_fichier = 'rapport_' . $rapport['id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $chemin_pdf = sys_get_temp_dir() . '/' . $nom_fichier;
        
        $pdf->Output($chemin_pdf, 'F');
    }

    // Téléchargement du fichier
    if (file_exists($chemin_pdf)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
        header('Content-Length: ' . filesize($chemin_pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        readfile($chemin_pdf);
        
        // Supprimer le fichier temporaire s'il a été créé
        if (strpos($chemin_pdf, sys_get_temp_dir()) !== false) {
            unlink($chemin_pdf);
        }
    } else {
        throw new Exception("Fichier PDF non trouvé");
    }

} catch (Exception $e) {
    error_log("Erreur génération PDF: " . $e->getMessage());
    header('Location: dashboardStagiaire.php?tab=rapports&error=' . urlencode($e->getMessage()));
}
?>