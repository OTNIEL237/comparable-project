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
     public static function modifier($data) { // Ajout de 'static'
        $conn = Database::getConnection();

        $theme_id = $data['theme_id'];
        $user_id = $data['encadreur_id'];

        $stmt_role = $conn->prepare("SELECT role FROM utilisateurs WHERE id = ?");
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role = $stmt_role->get_result()->fetch_assoc()['role'];
        
        $sql = "UPDATE themes SET titre=?, description=?, filiere=?, date_debut=?, date_fin=? WHERE id = ?";
        $params = [
            $data['titre'],
            $data['description'],
            $data['filiere'],
            $data['date_debut'],
            $data['date_fin'],
            $theme_id
        ];
        $types = "sssssi";
        
        if ($role !== 'admin') {
            $sql .= " AND encadreur_id = ?";
            $params[] = $user_id;
            $types .= "i";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    /**
     * Supprime un thème.
     */
  public static function supprimer($theme_id, $user_id) { // Ajout de 'static'
        $conn = Database::getConnection();
        
        $stmt_role = $conn->prepare("SELECT role FROM utilisateurs WHERE id = ?");
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role = $stmt_role->get_result()->fetch_assoc()['role'];

        $sql = "DELETE FROM themes WHERE id = ?";
        $params = [$theme_id];
        $types = "i";

        if ($role !== 'admin') {
            $sql .= " AND encadreur_id = ? AND stagiaire_id IS NULL";
            $params[] = $user_id;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }


    /**
     * Attribue un thème à un stagiaire.
     */
   public static function attribuer($theme_id, $stagiaire_id, $user_id) { // Ajout de 'static'
        $conn = Database::getConnection();

        $stmt_role = $conn->prepare("SELECT role FROM utilisateurs WHERE id = ?");
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role = $stmt_role->get_result()->fetch_assoc()['role'];

        $sql = "UPDATE themes SET stagiaire_id = ? WHERE id = ? AND stagiaire_id IS NULL";
        $params = [$stagiaire_id, $theme_id];
        $types = "ii";
        
        if ($role !== 'admin') {
            $sql .= " AND encadreur_id = ?";
            $params[] = $user_id;
            $types .= "i";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Récupère un thème par son ID.
     */
    public function getThemeById($theme_id) {
        // CORRECTION : Ajout des jointures pour récupérer toutes les informations
        $sql = "SELECT 
                    th.*, 
                    ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
                    us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom
                FROM themes th
                JOIN utilisateurs ue ON th.encadreur_id = ue.id
                LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                WHERE th.id = ?";
        
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