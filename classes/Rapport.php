<?php
/**
 * Classe Rapport - Gestion complète des rapports avec génération PDF
 * Fichier à placer dans : comparable-project/classes/Rapport.php
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Message.php';

// Vérifier si TCPDF est disponible
if (file_exists(__DIR__ . '/../TCPDF/tcpdf.php')) {
    require_once __DIR__ . '/../TCPDF/tcpdf.php';
    define('TCPDF_AVAILABLE', true);
} else {
    define('TCPDF_AVAILABLE', false);
}

class Rapport {
    private $conn;
    private $stagiaire_id;

    /**
     * Constructeur
     * @param int $stagiaire_id ID du stagiaire
     */
    public function __construct($stagiaire_id) {
        $this->conn = Database::getConnection();
        $this->stagiaire_id = $stagiaire_id;
    }

    /**
     * Créer un nouveau rapport avec génération PDF et notification
     * @param string $type Type de rapport (journalier, hebdomadaire, mensuel)
     * @param string $titre Titre du rapport
     * @param string $activites Activités réalisées
     * @param string $difficultes Difficultés rencontrées
     * @param string $solutions Solutions apportées
     * @return array Résultat avec succès et message
     */
    public function creer($type, $titre, $activites, $difficultes, $solutions) {
        try {
            $this->conn->begin_transaction();
           
            // 1. Récupérer les informations du stagiaire et de son encadreur
            $info_stagiaire = $this->getInfoStagiaire();
            if (!$info_stagiaire) {
                throw new Exception("Stagiaire non trouvé ou pas d'encadreur assigné");
            }
           
            // 2. Insérer le rapport en base de données avec statut 'en attente'
            $sql = "INSERT INTO rapports (stagiaire_id, type, titre, activites, difficultes, solutions, date_soumission, statut)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'en attente')";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation de la requête: " . $this->conn->error);
            }
            
            $stmt->bind_param("isssss", $this->stagiaire_id, $type, $titre, $activites, $difficultes, $solutions);
           
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'insertion du rapport: " . $stmt->error);
            }
           
            $rapport_id = $this->conn->insert_id;
           
            // 3. Générer le PDF si TCPDF est disponible
            $nom_fichier_pdf = null;
            if (TCPDF_AVAILABLE) {
                try {
                    $nom_fichier_pdf = $this->genererPDF($rapport_id, $type, $titre, $activites, $difficultes, $solutions, $info_stagiaire);
                    
                    // 4. Mettre à jour le rapport avec le chemin du PDF
                    $sql_update = "UPDATE rapports SET fichier_pdf = ? WHERE id = ?";
                    $stmt_update = $this->conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $nom_fichier_pdf, $rapport_id);
                    $stmt_update->execute();
                } catch (Exception $pdf_error) {
                    error_log("Erreur génération PDF: " . $pdf_error->getMessage());
                    // On continue sans PDF
                }
            }
           
            // 5. Envoyer une notification à l'encadreur via message
            $this->envoyerNotificationEncadreur($info_stagiaire, $titre, $nom_fichier_pdf);
           
            $this->conn->commit();
           
            return [
                'success' => true,
                'message' => 'Rapport créé et envoyé avec succès à votre encadreur.',
                'rapport_id' => $rapport_id,
                'fichier_pdf' => $nom_fichier_pdf
            ];
           
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Erreur création rapport: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Générer le PDF du rapport (seulement si TCPDF est disponible)
     */
    private function genererPDF($rapport_id, $type, $titre, $activites, $difficultes, $solutions, $info_stagiaire) {
        if (!TCPDF_AVAILABLE) {
            throw new Exception("TCPDF n'est pas disponible");
        }
        
        // Créer une nouvelle instance TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
       
        // Informations du document
        $pdf->SetCreator('Système de Gestion des Stagiaires');
        $pdf->SetAuthor($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']);
        $pdf->SetTitle('Rapport de stage - ' . $titre);
        $pdf->SetSubject('Rapport ' . $type);
       
        // Supprimer header et footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
       
        // Ajouter une page
        $pdf->AddPage();
       
        // Définir la police
        $pdf->SetFont('helvetica', '', 12);
       
        // Contenu HTML du PDF
        $html = $this->genererContenuHTML($type, $titre, $activites, $difficultes, $solutions, $info_stagiaire);
       
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
       
        // Générer le nom du fichier
        $nom_fichier = 'rapport_' . $this->stagiaire_id . '_' . $rapport_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $chemin_complet = __DIR__ . '/../uploads/rapports/' . $nom_fichier;
       
        // Créer le dossier s'il n'existe pas
        $dossier_upload = __DIR__ . '/../uploads/rapports/';
        if (!is_dir($dossier_upload)) {
            mkdir($dossier_upload, 0777, true);
        }
       
        // Sauvegarder le PDF
        $pdf->Output($chemin_complet, 'F');
       
        return $nom_fichier;
    }

    /**
     * Générer le contenu HTML pour le PDF
     */
    private function genererContenuHTML($type, $titre, $activites, $difficultes, $solutions, $info_stagiaire) {
        $date_actuelle = date('d/m/Y');
        $type_formate = ucfirst($type);
       
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
            <div class="subtitle">' . htmlspecialchars($titre) . '</div>
        </div>
       
        <div class="info-box">
            <strong>Stagiaire :</strong> ' . htmlspecialchars($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']) . '<br>
            <strong>Encadreur :</strong> ' . htmlspecialchars(($info_stagiaire['enc_prenom'] ?? '') . ' ' . ($info_stagiaire['enc_nom'] ?? '')) . '<br>
            <strong>Date de soumission :</strong> ' . $date_actuelle . '<br>
            <strong>Type de rapport :</strong> ' . $type_formate . '
        </div>
       
        <div class="section">
            <div class="section-title">ACTIVITÉS RÉALISÉES</div>
            <div class="content">' . nl2br(htmlspecialchars($activites)) . '</div>
        </div>
       
        <div class="section">
            <div class="section-title">DIFFICULTÉS RENCONTRÉES</div>
            <div class="content">' . nl2br(htmlspecialchars($difficultes)) . '</div>
        </div>
       
        <div class="section">
            <div class="section-title">SOLUTIONS APPORTÉES</div>
            <div class="content">' . nl2br(htmlspecialchars($solutions)) . '</div>
        </div>
       
        <div style="margin-top: 40px; text-align: right;">
            <p><strong>Signature du stagiaire</strong></p>
            <p style="margin-top: 20px;">_________________________</p>
            <p>' . htmlspecialchars($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']) . '</p>
        </div>';
       
        return $html;
    }

    /**
     * Récupérer les informations du stagiaire et de son encadreur
     * @return array|false Informations ou false si non trouvé
     */
    private function getInfoStagiaire() {
    $sql = "SELECT u.id, u.nom, u.prenom, u.email,
                   e.id_utilisateur AS enc_id,
                   ue.nom AS enc_nom, ue.prenom AS enc_prenom
            FROM utilisateurs u
            JOIN stagiaire s ON u.id = s.id_utilisateur
            JOIN encadreur e ON s.encadreur_id = e.id_utilisateur
            JOIN utilisateurs ue ON e.id_utilisateur = ue.id
            WHERE u.id = ?";
    
    $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getInfoStagiaire: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $this->stagiaire_id);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getInfoStagiaire: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
       
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
       
        return false;
    }

    /**
     * Envoyer une notification à l'encadreur
     */

private function envoyerNotificationEncadreur($info_stagiaire, $titre_rapport, $nom_fichier_pdf) {
    if (!isset($info_stagiaire['enc_id']) || !$info_stagiaire['enc_id']) {
        error_log("Pas d'encadreur assigné pour le stagiaire ID: " . $this->stagiaire_id);
        return;
    }
    
    try {
        $message = new Message($this->stagiaire_id);
        $sujet = "Nouveau rapport soumis : " . $titre_rapport;
        $contenu = "Bonjour,\n\n";
        $contenu .= "Le stagiaire " . $info_stagiaire['prenom'] . " " . $info_stagiaire['nom'];
        $contenu .= " a soumis un nouveau rapport intitulé : \"" . $titre_rapport . "\".\n\n";
        $contenu .= "Le rapport est maintenant disponible dans votre onglet 'Rapports' en attente de validation.\n\n";
        $contenu .= "Cordialement,\nSystème de Gestion des Stagiaires";
        
        // Envoyer à l'ID utilisateur de l'encadreur
        $message->envoyer($info_stagiaire['enc_id'], $sujet, $contenu);
    } catch (Exception $e) {
        error_log("Erreur envoi notification encadreur: " . $e->getMessage());
    }
}
    /**
     * Récupérer tous les rapports du stagiaire
     * @param string $filtre Filtre par type (all, journalier, hebdomadaire, mensuel)
     * @param string $recherche Terme de recherche
     * @return mysqli_result Résultats de la requête
     */
    public function getTousRapports($filtre = 'all', $recherche = '') {
        $sql = "SELECT r.*, u.nom AS enc_nom, u.prenom AS enc_prenom
                FROM rapports r
                LEFT JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur
                LEFT JOIN utilisateurs u ON s.encadreur_id = u.id
                WHERE r.stagiaire_id = ?";
       
        $params = [$this->stagiaire_id];
        $types = "i";
       
        // Appliquer le filtre par type
        if ($filtre !== 'all' && in_array($filtre, ['journalier', 'hebdomadaire', 'mensuel'])) {
            $sql .= " AND r.type = ?";
            $params[] = $filtre;
            $types .= "s";
        }
       
        // Appliquer la recherche
        if (!empty($recherche)) {
            $sql .= " AND (r.titre LIKE ? OR r.activites LIKE ?)";
            $searchTerm = "%$recherche%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }
       
        $sql .= " ORDER BY r.date_soumission DESC";
       
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getTousRapports: " . $this->conn->error);
            return new mysqli_result($this->conn);
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getTousRapports: " . $stmt->error);
            return new mysqli_result($this->conn);
        }
        
        return $stmt->get_result();
    }

    /**
     * Récupérer un rapport par son ID
     * @param int $rapport_id ID du rapport
     * @return array|null Données du rapport ou null
     */
    public function getRapportById($rapport_id) {
        $sql = "SELECT r.*,
                       us.nom AS stag_nom, us.prenom AS stag_prenom,
                       ue.nom AS enc_nom, ue.prenom AS enc_prenom
                FROM rapports r
                JOIN utilisateurs us ON r.stagiaire_id = us.id
                LEFT JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur
                LEFT JOIN utilisateurs ue ON s.encadreur_id = ue.id
                WHERE r.id = ? AND r.stagiaire_id = ?";
       
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getRapportById: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param("ii", $rapport_id, $this->stagiaire_id);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getRapportById: " . $stmt->error);
            return null;
        }
        
        $result = $stmt->get_result();
       
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
       
        return null;
    }

    /**
     * Méthodes statiques pour l'encadreur
     */

    /**
     * Récupérer tous les rapports des stagiaires d'un encadreur
     * @param int $encadreur_id ID de l'encadreur
     * @param string $filtre Filtre par statut
     * @param string $recherche Terme de recherche
     * @return mysqli_result Résultats de la requête
     */
    public static function getRapportsEncadreur($encadreur_id, $filtre = 'all', $recherche = '') {
        $conn = Database::getConnection();
       
$sql = "SELECT r.*, 
                   us.nom AS stag_nom, us.prenom AS stag_prenom
            FROM rapports r
            JOIN utilisateurs us ON r.stagiaire_id = us.id
            JOIN stagiaire s ON us.id = s.id_utilisateur
            WHERE s.encadreur_id = ?";
       
        $params = [$encadreur_id];
        $types = "i";
       
        // Appliquer le filtre par statut
        if ($filtre !== 'all' && in_array($filtre, ['en_attente', 'validé', 'rejeté'])) {
            $sql .= " AND r.statut = ?";
            $params[] = str_replace('_', ' ', $filtre);
            $types .= "s";
        }
       
        // Appliquer la recherche
        if (!empty($recherche)) {
            $sql .= " AND (r.titre LIKE ? OR us.nom LIKE ? OR us.prenom LIKE ?)";
            $searchTerm = "%$recherche%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }
       
        $sql .= " ORDER BY r.date_soumission DESC";
       
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getRapportsEncadreur: " . $conn->error);
            return new mysqli_result($conn);
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getRapportsEncadreur: " . $stmt->error);
            return new mysqli_result($conn);
        }
        
        return $stmt->get_result();
    }

    /**
     * Valider ou rejeter un rapport
     * @param int $rapport_id ID du rapport
     * @param string $statut Nouveau statut (validé, rejeté)
     * @param string $commentaire Commentaire de l'encadreur
     * @return bool Succès de l'opération
     */
    public static function changerStatutRapport($rapport_id, $statut, $commentaire = '') {
        $conn = Database::getConnection();
       
        $sql = "UPDATE rapports SET statut = ?, commentaire_encadreur = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Erreur préparation requête changerStatutRapport: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("ssi", $statut, $commentaire, $rapport_id);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête changerStatutRapport: " . $stmt->error);
            return false;
        }
        
        return true;
    }

        /**
     * NOUVELLE FONCTION ADMIN : Récupère tous les rapports du système.
     */
    public static function getTousLesRapports($filtre = 'all', $recherche = '') {
        $conn = Database::getConnection();
        $sql = "SELECT r.*, us.nom AS stag_nom, us.prenom AS stag_prenom
                FROM rapports r
                JOIN utilisateurs us ON r.stagiaire_id = us.id";
        
        $params = [];
        $types = "";
        $where_clauses = [];

        if ($filtre !== 'all' && in_array($filtre, ['en_attente', 'validé', 'rejeté'])) {
            $where_clauses[] = "r.statut = ?";
            $params[] = str_replace('_', ' ', $filtre);
            $types .= "s";
        }
        if (!empty($recherche)) {
            $where_clauses[] = "(r.titre LIKE ? OR us.nom LIKE ? OR us.prenom LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sss";
        }

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        $sql .= " ORDER BY r.date_soumission DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
}