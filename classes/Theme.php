<?php
require_once 'Database.php';

class Theme {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Crée un nouveau thème.
     */
    public function creer($data) {
        $sql = "INSERT INTO themes (encadreur_id, titre, description, filiere, date_debut, date_fin) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "isssss",
            $data['encadreur_id'],
            $data['titre'],
            $data['description'],
            $data['filiere'],
            $data['date_debut'],
            $data['date_fin']
        );
        return $stmt->execute();
    }

    /**
     * Met à jour un thème existant.
     */
    public function modifier($data) {
        $sql = "UPDATE themes SET titre = ?, description = ?, filiere = ?, date_debut = ?, date_fin = ? 
                WHERE id = ? AND encadreur_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "sssssii",
            $data['titre'],
            $data['description'],
            $data['filiere'],
            $data['date_debut'],
            $data['date_fin'],
            $data['theme_id'],
            $data['encadreur_id']
        );
        return $stmt->execute();
    }

    /**
     * Supprime un thème.
     */
    public function supprimer($theme_id, $encadreur_id) {
        $sql = "DELETE FROM themes WHERE id = ? AND encadreur_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $theme_id, $encadreur_id);
        return $stmt->execute();
    }

    /**
     * Attribue un thème à un stagiaire.
     */
    public function attribuer($theme_id, $stagiaire_id, $encadreur_id) {
        $sql = "UPDATE themes SET stagiaire_id = ? WHERE id = ? AND encadreur_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $stagiaire_id, $theme_id, $encadreur_id);
        return $stmt->execute();
    }

    /**
     * Récupère un thème par son ID.
     */
    public function getThemeById($theme_id) {
        $sql = "SELECT * FROM themes WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $theme_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Récupère tous les thèmes créés par un encadreur.
     */
        // Dans classes/Theme.php
    public function getThemesByEncadreur($encadreur_id, $recherche = '') {
        $sql = "SELECT t.*, u.prenom AS stagiaire_prenom, u.nom AS stagiaire_nom 
                FROM themes t
                LEFT JOIN utilisateurs u ON t.stagiaire_id = u.id
                WHERE t.encadreur_id = ?";
        
        $params = [$encadreur_id];
        $types = "i";

        if (!empty($recherche)) {
            $sql .= " AND (t.titre LIKE ? OR t.description LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm);
            $types .= "ss";
        }
        $sql .= " ORDER BY t.id DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    }

    /**
     * Récupère le thème assigné à un stagiaire.
     */
    public function getThemeByStagiaire($stagiaire_id) {
        $sql = "SELECT * FROM themes WHERE stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stagiaire_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public static function listerTousLesThemes($recherche = '') {
        $conn = Database::getConnection();
        
        $sql = "SELECT 
                    th.*, 
                    ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
                    us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom
                FROM themes th
                JOIN utilisateurs ue ON th.encadreur_id = ue.id
                LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if (!empty($recherche)) {
            // La recherche se fait sur le titre, la filière, ou le nom de l'encadreur
            $sql .= " AND (th.titre LIKE ? OR th.filiere LIKE ? OR ue.prenom LIKE ? OR ue.nom LIKE ?)";
            $searchTerm = "%" . $recherche . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "ssss";
        }
        
        $sql .= " ORDER BY th.titre ASC";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($recherche)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
}