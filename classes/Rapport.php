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
    private $stagiaire_id; // Utilisé seulement pour le constructeur, peut être rendu statique ou passé comme paramètre

    /**
     * Constructeur
     * @param int $stagiaire_id ID du stagiaire
     */
    public function __construct($stagiaire_id = null) { // Rendre $stagiaire_id optionnel
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
     * @param int|null $tache_id ID de la tâche associée
     * @return array Résultat avec succès et message
     */
    public function creer($type, $titre, $activites, $difficultes, $solutions, $tache_id = null) {
        try {
            $this->conn->begin_transaction();
           
            $info_stagiaire = $this->getInfoStagiaire($this->stagiaire_id); // Passer l'ID du stagiaire
            if (!$info_stagiaire) {
                throw new Exception("Stagiaire non trouvé ou pas d'encadreur assigné");
            }
           
            $tache_id = empty($tache_id) ? null : (int)$tache_id;

            // Fetch task title if ID is provided
            $tache_titre = null;
            if ($tache_id) {
                $tache_sql = "SELECT titre FROM taches WHERE id = ?";
                $tache_stmt = $this->conn->prepare($tache_sql);
                if ($tache_stmt === false) { throw new Exception("Erreur de préparation getInfoStagiaire: " . $this->conn->error); }
                $tache_stmt->bind_param("i", $tache_id);
                $tache_stmt->execute();
                $tache_result = $tache_stmt->get_result();
                if ($tache_result->num_rows > 0) {
                    $tache_titre = $tache_result->fetch_assoc()['titre'];
                }
                $tache_result->free(); // Libérer le résultat
                $tache_stmt->close(); // Fermer le statement
            }

            $sql = "INSERT INTO rapports (stagiaire_id, tache_id, type, titre, activites, difficultes, solutions, date_soumission, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'en attente')";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erreur de préparation de l'insertion du rapport: " . $this->conn->error);
            }
            
            $stmt->bind_param("iisssss", $this->stagiaire_id, $tache_id, $type, $titre, $activites, $difficultes, $solutions);
           
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'insertion du rapport: " . $stmt->error);
            }
            $rapport_id = $this->conn->insert_id;
            $stmt->close(); // Fermer le statement
           
            $nom_fichier_pdf = null;
            if (TCPDF_AVAILABLE) {
                try {
                    // Pass task title to PDF generation
                    $nom_fichier_pdf = $this->genererPDF($rapport_id, $type, $titre, $activites, $difficultes, $solutions, $info_stagiaire, $tache_titre);
                    
                    $sql_update = "UPDATE rapports SET fichier_pdf = ? WHERE id = ?";
                    $stmt_update = $this->conn->prepare($sql_update);
                    if (!$stmt_update) {
                        throw new Exception("Erreur de préparation de la mise à jour du PDF: " . $this->conn->error);
                    }
                    $stmt_update->bind_param("si", $nom_fichier_pdf, $rapport_id);
                    $stmt_update->execute();
                    $stmt_update->close(); // Fermer le statement
                } catch (Exception $pdf_error) {
                    error_log("Erreur génération PDF: " . $pdf_error->getMessage());
                }
            }
           
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
    private function genererPDF($rapport_id, $type, $titre, $activites, $difficultes, $solutions, $info_stagiaire, $tache_titre = null) {
        if (!TCPDF_AVAILABLE) {
            throw new Exception("TCPDF n'est pas disponible");
        }
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
       
        $pdf->SetCreator('Système de Gestion des Stagiaires');
        $pdf->SetAuthor($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']);
        $pdf->SetTitle('Rapport de stage - ' . $titre);
        $pdf->SetSubject('Rapport ' . $type);
       
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
       
        $pdf->AddPage();
       
        $pdf->SetFont('helvetica', '', 11);
       
        // Pass task title to HTML generation
        $html = $this->genererContenuHTML($type, $titre, $activites, $difficultes, $solutions, $info_stagiaire, $tache_titre);
       
        $pdf->writeHTML($html, true, false, true, false, '');
       
        $nom_fichier = 'rapport_' . $this->stagiaire_id . '_' . $rapport_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $chemin_complet = __DIR__ . '/../uploads/rapports/' . $nom_fichier;
       
        $dossier_upload = __DIR__ . '/../uploads/rapports/';
        if (!is_dir($dossier_upload)) {
            mkdir($dossier_upload, 0755, true); // Utiliser 0755 pour les dossiers pour une meilleure sécurité en production
        }
       
        $pdf->Output($chemin_complet, 'F');
       
        return $nom_fichier;
    }

    /**
     * Générer le contenu HTML pour le PDF
     */
    private function genererContenuHTML($type, $titre, $activites, $difficultes, $solutions, $info_stagiaire, $tache_titre = null) {
        $date_actuelle = date('d/m/Y');
        $type_formate = ucfirst($type);
        $nom_stagiaire = htmlspecialchars($info_stagiaire['prenom'] . ' ' . $info_stagiaire['nom']);
        $nom_encadreur = htmlspecialchars(($info_stagiaire['enc_prenom'] ?? '') . ' ' . ($info_stagiaire['enc_nom'] ?? ''));

        $tache_html = '';
        if ($tache_titre) {
            $tache_html = '
            <tr>
                <td class="info-label">Tâche associée :</td>
                <td class="info-value">' . htmlspecialchars($tache_titre) . '</td>
            </tr>';
        }

        $html = '
        <style>
            body {
                font-family: dejavusans, sans-serif;
                color: #333;
                font-size: 10pt;
                line-height: 1.5;
            }
            .main-title {
                font-size: 18pt;
                font-weight: bold;
                color: #333;
                text-align: center;
                margin-bottom: 6px;
            }
            .subtitle {
                font-size: 12pt;
                color: #555;
                text-align: center;
                margin-bottom: 25px;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
            }
            .info-table td {
                padding: 8px 12px;
                border: 1px solid #dee2e6;
            }
            .info-label {
                font-weight: bold;
                width: 30%;
                background-color: #e9ecef;
            }
            .info-value {
                width: 70%;
            }
            .section {
                margin-bottom: 25px;
                /* Add a page break before if needed */
                page-break-inside: avoid;
            }
            .section-title {
                font-size: 13pt;
                font-weight: bold;
                color: #D67B7B;
                padding-bottom: 5px;
                margin-bottom: 10px;
                border-bottom: 1.5px solid #D67B7B;
            }
            .content {
                text-align: justify;
                color: #444;
            }
            .content p {
                margin: 0;
                padding: 0;
            }
            .footer {
                position: absolute;
                bottom: 20px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 8pt;
                color: #999;
            }
        </style>

    <h1 class="main-title">Rapport de Stage - ' . $type_formate . '</h1>
    <p class="subtitle">' . htmlspecialchars($titre) . '</p>

    <table class="info-table" cellpadding="5">
        <tr>
            <td class="info-label">Stagiaire :</td>
            <td class="info-value">' . $nom_stagiaire . '</td>
        </tr>
        <tr>
            <td class="info-label">Encadreur :</td>
            <td class="info-value">' . $nom_encadreur . '</td>
        </tr>
        <tr>
            <td class="info-label">Date de soumission :</td>
            <td class="info-value">' . $date_actuelle . '</td>
        </tr>
        ' . $tache_html . '
    </table>

    <div class="section">
        <h2 class="section-title">1. Activités Réalisées</h2>
        <div class="content">' . nl2br(htmlspecialchars($activites)) . '</div>
    </div>

    <div class="section">
        <h2 class="section-title">2. Difficultés Rencontrées</h2>
        <div class="content">' . nl2br(htmlspecialchars($difficultes)) . '</div>
    </div>

    <div class="section">
        <h2 class="section-title">3. Solutions Apportées</h2>
        <div class="content">' . nl2br(htmlspecialchars($solutions)) . '</div>
    </div>
    
    <div class="footer">
        Généré par le Système de Gestion des Stagiaires | ' . $date_actuelle . '
    </div>
    ';
   
        return $html;
    }

    /**
     * Récupérer les informations du stagiaire et de son encadreur
     * @param int $stagiaire_id ID du stagiaire
     * @return array|false Informations ou false si non trouvé
     */
    private function getInfoStagiaire($stagiaire_id) {
        $sql = "SELECT u.id, u.nom, u.prenom, u.email,
                    e.id_utilisateur AS enc_id,
                    ue.nom AS enc_nom, ue.prenom AS enc_prenom
                FROM utilisateurs u
                JOIN stagiaire s ON u.id = s.id_utilisateur
                LEFT JOIN encadreur e ON s.encadreur_id = e.id_utilisateur
                LEFT JOIN utilisateurs ue ON e.id_utilisateur = ue.id
                WHERE u.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getInfoStagiaire: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $stagiaire_id);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getInfoStagiaire: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
        $stmt->close();
       
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
     * Récupérer tous les rapports du stagiaire (utilisé par le stagiaire lui-même)
     * @param string $filtre Filtre par type (all, journalier, hebdomadaire, mensuel)
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de rapports par page
     * @return array Contenant les rapports et les infos de pagination
     */
    public function getTousRapports($filtre = 'all', $recherche = '', $page = 1, $limit = 20) { // Changed default limit to 20
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $where_clauses = ["r.stagiaire_id = ?"];
        $params = [$this->stagiaire_id];
        $types = "i";
       
        // Appliquer le filtre par type
        if ($filtre !== 'all' && in_array($filtre, ['journalier', 'hebdomadaire', 'mensuel'])) {
            $where_clauses[] = "r.type = ?";
            $params[] = $filtre;
            $types .= "s";
        }
       
        // Appliquer la recherche
        if (!empty($recherche)) {
            $where_clauses[] = "(r.titre LIKE ? OR r.activites LIKE ?)";
            $searchTerm = "%$recherche%";
            array_push($params, $searchTerm, $searchTerm);
            $types .= "ss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de rapports
        $count_sql = "SELECT COUNT(*) as total 
                      FROM rapports r
                      LEFT JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur
                      LEFT JOIN utilisateurs u ON s.encadreur_id = u.id
                      LEFT JOIN taches t ON r.tache_id = t.id
                      $where_full_clause";
        
        $stmt_count = $this->conn->prepare($count_sql);
        if (!$stmt_count) {
             error_log("Erreur préparation comptage rapports stagiaire: " . $this->conn->error);
             return ['rapports' => new mysqli_result($this->conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total_rapports = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les rapports paginés
        $sql = "SELECT r.*, u.nom AS enc_nom, u.prenom AS enc_prenom, t.titre AS tache_titre
                FROM rapports r
                LEFT JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur
                LEFT JOIN utilisateurs u ON s.encadreur_id = u.id
                LEFT JOIN taches t ON r.tache_id = t.id
                $where_full_clause
                ORDER BY r.date_soumission DESC
                LIMIT ? OFFSET ?";

        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";
       
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getTousRapports: " . $this->conn->error);
            return ['rapports' => new mysqli_result($this->conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        $stmt->bind_param($types_main_query, ...$params_main_query);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getTousRapports: " . $stmt->error);
            return ['rapports' => new mysqli_result($this->conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $rapports_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_rapports / $limit);

        return [
            'rapports' => $rapports_result_set,
            'total_rapports' => $total_rapports,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }

    /**
     * Récupérer un rapport par son ID
     * @param int $rapport_id ID du rapport
     * @param int $user_id ID de l'utilisateur connecté
     * @param string $role Rôle de l'utilisateur connecté
     * @return array|null Données du rapport ou null
     */
     public static function getRapportById($rapport_id, $user_id, $role) {
        $conn = Database::getConnection();
        
        $sql = "SELECT r.*,
                       us.nom AS stag_nom, us.prenom AS stag_prenom,
                       ue.nom AS enc_nom, ue.prenom AS enc_prenom,
                       t.titre AS tache_titre 
                FROM rapports r
                JOIN utilisateurs us ON r.stagiaire_id = us.id
                LEFT JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur
                LEFT JOIN utilisateurs ue ON s.encadreur_id = ue.id
                LEFT JOIN taches t ON r.tache_id = t.id
                WHERE r.id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             error_log("Erreur préparation getRapportById: " . $conn->error);
             return null;
        }
        $stmt->bind_param("i", $rapport_id);
        $stmt->execute();
        $rapport = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$rapport) {
            return null; // Le rapport n'existe pas
        }

        // Vérification des permissions
        if ($role === 'stagiaire') {
            if ($rapport['stagiaire_id'] != $user_id) {
                return null; // Stagiaire ne peut voir que ses propres rapports
            }
        } elseif ($role === 'encadreur') {
            // Un encadreur ne peut voir que les rapports de ses stagiaires
            $sql_check = "SELECT COUNT(*) FROM stagiaire WHERE id_utilisateur = ? AND encadreur_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            if (!$stmt_check) {
                error_log("Erreur préparation check encadreur rapport: " . $conn->error);
                return null;
            }
            $stmt_check->bind_param("ii", $rapport['stagiaire_id'], $user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->fetch_row()[0] == 0) {
                 // Si l'utilisateur n'est pas l'encadreur du stagiaire, refuser l'accès.
                 $stmt_check->close();
                 return null;
            }
            $stmt_check->close();
        }
        // Pour l'admin, aucune restriction, il peut tout voir.
        
        return $rapport;
    }
    /**
     * Méthodes statiques pour l'encadreur et l'admin
     */

    /**
     * Récupérer tous les rapports des stagiaires d'un encadreur
     * @param int $encadreur_id ID de l'encadreur
     * @param string $filtre Filtre par statut
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de rapports par page
     * @return array Contenant les rapports et les infos de pagination
     */
    public static function getRapportsEncadreur($encadreur_id, $filtre = 'all', $recherche = '', $page = 1, $limit = 20) { // Changed default limit to 20
        $conn = Database::getConnection();
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $where_clauses = ["s.encadreur_id = ?"];
        $params = [$encadreur_id];
        $types = "i";
       
        // Appliquer le filtre par statut
        if ($filtre !== 'all' && in_array($filtre, ['en_attente', 'validé', 'rejeté'])) {
            $where_clauses[] = "r.statut = ?";
            $params[] = str_replace('_', ' ', $filtre);
            $types .= "s";
        }
       
        // Appliquer la recherche
        if (!empty($recherche)) {
            $where_clauses[] = "(r.titre LIKE ? OR us.nom LIKE ? OR us.prenom LIKE ? OR t.titre LIKE ?)";
            $searchTerm = "%$recherche%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "ssss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de rapports
        $count_sql = "SELECT COUNT(*) as total 
                      FROM rapports r
                      JOIN utilisateurs us ON r.stagiaire_id = us.id
                      JOIN stagiaire s ON us.id = s.id_utilisateur
                      LEFT JOIN taches t ON r.tache_id = t.id
                      $where_full_clause";
        
        $stmt_count = $conn->prepare($count_sql);
        if (!$stmt_count) {
             error_log("Erreur préparation comptage rapports encadreur: " . $conn->error);
             return ['rapports' => new mysqli_result($conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total_rapports = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les rapports paginés
        $sql = "SELECT r.*, 
                   us.nom AS stag_nom, us.prenom AS stag_prenom,
                   t.titre AS tache_titre
            FROM rapports r
            JOIN utilisateurs us ON r.stagiaire_id = us.id
            JOIN stagiaire s ON us.id = s.id_utilisateur
            LEFT JOIN taches t ON r.tache_id = t.id
            $where_full_clause
            ORDER BY r.date_soumission DESC
            LIMIT ? OFFSET ?";
       
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getRapportsEncadreur: " . $conn->error);
            return ['rapports' => new mysqli_result($conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        $stmt->bind_param($types_main_query, ...$params_main_query);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getRapportsEncadreur: " . $stmt->error);
            return ['rapports' => new mysqli_result($conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $rapports_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_rapports / $limit);

        return [
            'rapports' => $rapports_result_set,
            'total_rapports' => $total_rapports,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
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
       
        $sql = "UPDATE rapports SET statut = ?, commentaire_encadreur = ?, date_validation = NOW() WHERE id = ?";
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
        $stmt->close();
        return true;
    }

    /**
     * Lister tous les rapports du système (utilisé par l'administrateur)
     * @param string $filtre Filtre par statut
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de rapports par page
     * @return array Contenant les rapports et les infos de pagination
     */
    public static function listerTousLesRapports($filtre = 'all', $recherche = '', $page = 1, $limit = 20) { // Changed default limit to 20
        $conn = Database::getConnection();
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $where_clauses = ["1=1"]; // Commence par une condition toujours vraie
        $params = [];
        $types = "";
       
        // Appliquer le filtre par statut
        if ($filtre !== 'all' && in_array($filtre, ['en_attente', 'validé', 'rejeté'])) {
            $where_clauses[] = "r.statut = ?";
            $params[] = str_replace('_', ' ', $filtre);
            $types .= "s";
        }
       
        // Appliquer la recherche (inclut le nom du stagiaire, de l'encadreur et le titre du rapport)
        if (!empty($recherche)) {
            $where_clauses[] = "(r.titre LIKE ? OR us.nom LIKE ? OR us.prenom LIKE ? OR ue.nom LIKE ? OR ue.prenom LIKE ? OR t.titre LIKE ?)";
            $searchTerm = "%$recherche%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "ssssss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de rapports
        $count_sql = "SELECT COUNT(*) as total 
                      FROM rapports r
                      JOIN utilisateurs us ON r.stagiaire_id = us.id
                      LEFT JOIN stagiaire s ON us.id = s.id_utilisateur
                      LEFT JOIN utilisateurs ue ON s.encadreur_id = ue.id
                      LEFT JOIN taches t ON r.tache_id = t.id
                      $where_full_clause";
        
        $stmt_count = $conn->prepare($count_sql);
        if (!$stmt_count) {
             error_log("Erreur préparation comptage tous rapports: " . $conn->error);
             return ['rapports' => new mysqli_result($conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $total_rapports = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les rapports paginés
        $sql = "SELECT r.*, 
                   us.nom AS stag_nom, us.prenom AS stag_prenom,
                   ue.nom AS enc_nom, ue.prenom AS enc_prenom,
                   t.titre AS tache_titre
            FROM rapports r
            JOIN utilisateurs us ON r.stagiaire_id = us.id
            LEFT JOIN stagiaire s ON us.id = s.id_utilisateur
            LEFT JOIN utilisateurs ue ON s.encadreur_id = ue.id
            LEFT JOIN taches t ON r.tache_id = t.id
            $where_full_clause
            ORDER BY r.date_soumission DESC
            LIMIT ? OFFSET ?";
       
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête listerTousLesRapports: " . $conn->error);
            return ['rapports' => new mysqli_result($conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        $stmt->bind_param($types_main_query, ...$params_main_query);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête listerTousLesRapports: " . $stmt->error);
            return ['rapports' => new mysqli_result($conn), 'total_rapports' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $rapports_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_rapports / $limit);

        return [
            'rapports' => $rapports_result_set,
            'total_rapports' => $total_rapports,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }
}