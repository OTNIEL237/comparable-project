<?php
require_once 'Database.php';

class Theme {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Crée un nouveau thème.
     * @param array $data Données du formulaire.
     * @return array Résultat avec succès et message
     */
    public function creer($data) {
        $sql = "INSERT INTO themes (encadreur_id, titre, description, filiere, date_debut, date_fin) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Erreur de préparation de la requête creer theme: " . $this->conn->error);
            return ['success' => false, 'message' => 'Erreur système lors de la préparation.'];
        }
        $stmt->bind_param(
            "isssss",
            $data['encadreur_id'],
            $data['titre'],
            $data['description'],
            $data['filiere'],
            $data['date_debut'],
            $data['date_fin']
        );
        $success = $stmt->execute();
        $stmt->close();
        if (!$success) {
            error_log("Erreur lors de l'exécution de creer theme: " . $stmt->error);
            return ['success' => false, 'message' => 'Erreur lors de la création du thème.'];
        }
        return ['success' => true, 'message' => 'Thème créé avec succès.'];
    }

    /**
     * Met à jour un thème existant.
     * @param array $data Données du formulaire.
     * @return array Résultat avec succès et message
     */
    public static function modifier($data) {
        $conn = Database::getConnection();
        $theme_id = $data['theme_id'];
        $user_id = $data['encadreur_id']; // ID de l'utilisateur qui effectue la modification

        $stmt_role = $conn->prepare("SELECT role FROM utilisateurs WHERE id = ?");
        if ($stmt_role === false) { error_log("Erreur de préparation SELECT role (modifier theme): " . $conn->error); return ['success' => false, 'message' => 'Erreur système.']; }
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role_data = $stmt_role->get_result()->fetch_assoc();
        $stmt_role->close();
        $role = $role_data['role'] ?? null;
        
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
        
        // Seul l'admin peut modifier un thème d'un autre encadreur ou un thème attribué.
        // Un encadreur ne peut modifier que ses propres thèmes qui ne sont pas encore attribués.
        if ($role !== 'admin') {
            $sql .= " AND encadreur_id = ? AND stagiaire_id IS NULL"; // Empêche l'encadreur de modifier un thème attribué
            $params[] = $user_id;
            $types .= "i";
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur de préparation UPDATE theme: " . $conn->error); return ['success' => false, 'message' => 'Erreur système lors de la préparation.']; }
        $stmt->bind_param($types, ...$params);
        
        $success = $stmt->execute();
        $stmt->close();

        if (!$success || $stmt->affected_rows === 0) {
            error_log("Erreur lors de l'exécution de modifier theme ou aucune ligne affectée: " . ($stmt->error ?? ''));
            return ['success' => false, 'message' => 'Erreur lors de la modification du thème ou accès non autorisé.'];
        }
        return ['success' => true, 'message' => 'Thème mis à jour avec succès.'];
    }

    /**
     * Supprime un thème.
     * @param int $theme_id ID du thème à supprimer.
     * @param int $user_id ID de l'utilisateur qui tente de supprimer.
     * @return array Résultat avec succès et message
     */
    public static function supprimer($theme_id, $user_id) {
        $conn = Database::getConnection();
        
        $stmt_role = $conn->prepare("SELECT role FROM utilisateurs WHERE id = ?");
        if ($stmt_role === false) { error_log("Erreur de préparation SELECT role (supprimer theme): " . $conn->error); return ['success' => false, 'message' => 'Erreur système.']; }
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role_data = $stmt_role->get_result()->fetch_assoc();
        $stmt_role->close();
        $role = $role_data['role'] ?? null;

        $sql = "DELETE FROM themes WHERE id = ?";
        $params = [$theme_id];
        $types = "i";

        // Seul l'admin peut supprimer n'importe quel thème.
        // Un encadreur ne peut supprimer que ses propres thèmes non attribués.
        if ($role !== 'admin') {
            $sql .= " AND encadreur_id = ? AND stagiaire_id IS NULL"; // Empêche l'encadreur de supprimer un thème attribué
            $params[] = $user_id;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur de préparation DELETE theme: " . $conn->error); return ['success' => false, 'message' => 'Erreur système lors de la préparation.']; }
        $stmt->bind_param($types, ...$params);
        
        $success = $stmt->execute();
        $stmt->close();

        if (!$success || $stmt->affected_rows === 0) {
            error_log("Erreur lors de l'exécution de supprimer theme ou aucune ligne affectée: " . ($stmt->error ?? ''));
            return ['success' => false, 'message' => 'Erreur lors de la suppression du thème ou accès non autorisé.'];
        }
        return ['success' => true, 'message' => 'Thème supprimé avec succès.'];
    }


    /**
     * Attribue un thème à un stagiaire.
     * @param int $theme_id ID du thème.
     * @param int $stagiaire_id ID du stagiaire.
     * @param int $user_id ID de l'utilisateur qui effectue l'attribution.
     * @return array Résultat avec succès et message
     */
    public static function attribuer($theme_id, $stagiaire_id, $user_id) {
        $conn = Database::getConnection();

        $stmt_role = $conn->prepare("SELECT role FROM utilisateurs WHERE id = ?");
        if ($stmt_role === false) { error_log("Erreur de préparation SELECT role (attribuer theme): " . $conn->error); return ['success' => false, 'message' => 'Erreur système.']; }
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role_data = $stmt_role->get_result()->fetch_assoc();
        $stmt_role->close();
        $role = $role_data['role'] ?? null;

        // On vérifie d'abord si le thème est déjà attribué ou si l'encadreur a le droit
        $sql_check_theme = "SELECT encadreur_id, stagiaire_id FROM themes WHERE id = ?";
        $stmt_check_theme = $conn->prepare($sql_check_theme);
        if ($stmt_check_theme === false) { error_log("Erreur de préparation SELECT check theme: " . $conn->error); return ['success' => false, 'message' => 'Erreur système lors de la vérification.']; }
        $stmt_check_theme->bind_param("i", $theme_id);
        $stmt_check_theme->execute();
        $theme_info = $stmt_check_theme->get_result()->fetch_assoc();
        $stmt_check_theme->close();

        if (!$theme_info) {
            return ['success' => false, 'message' => 'Thème non trouvé.'];
        }
        if ($theme_info['stagiaire_id'] !== null) {
            return ['success' => false, 'message' => 'Ce thème est déjà attribué.'];
        }
        if ($role !== 'admin' && $theme_info['encadreur_id'] != $user_id) {
            return ['success' => false, 'message' => 'Accès non autorisé : Vous ne pouvez attribuer que vos propres thèmes.'];
        }
        
        $sql = "UPDATE themes SET stagiaire_id = ? WHERE id = ?";
        $params = [$stagiaire_id, $theme_id];
        $types = "ii";
        
        // La vérification des droits est déjà faite ci-dessus, on peut simplifier la clause WHERE ici.
        // On s'assure juste que le thème n'est pas déjà attribué (stagiaire_id IS NULL).
        $sql .= " AND stagiaire_id IS NULL"; 
        
        if ($role !== 'admin') {
            $sql .= " AND encadreur_id = ?";
            $params[] = $user_id;
            $types .= "i";
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur de préparation UPDATE attribuer theme: " . $conn->error); return ['success' => false, 'message' => 'Erreur système lors de la préparation.']; }
        $stmt->bind_param($types, ...$params);

        $success = $stmt->execute();
        $stmt->close();

        if (!$success || $stmt->affected_rows === 0) {
            error_log("Erreur lors de l'exécution de attribuer theme ou aucune ligne affectée: " . ($stmt->error ?? ''));
            return ['success' => false, 'message' => 'Erreur lors de l\'attribution du thème ou thème déjà attribué/accès non autorisé.'];
        }

        // Dissocier l'ancien thème si le stagiaire en avait déjà un
        self::dissocierAncienTheme($stagiaire_id, $theme_id, $conn);

        return ['success' => true, 'message' => 'Thème attribué avec succès.'];
    }

    /**
     * Dissocie l'ancien thème d'un stagiaire quand un nouveau thème lui est attribué.
     * @param int $stagiaire_id ID du stagiaire.
     * @param int $new_theme_id ID du nouveau thème attribué.
     * @param mysqli $conn Connexion à la base de données.
     */
    private static function dissocierAncienTheme($stagiaire_id, $new_theme_id, $conn) {
        $sql = "UPDATE themes SET stagiaire_id = NULL WHERE stagiaire_id = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur de préparation dissocierAncienTheme: " . $conn->error); return; }
        $stmt->bind_param("ii", $stagiaire_id, $new_theme_id);
        $stmt->execute();
        $stmt->close();
    }


    /**
     * Récupère un thème par son ID.
     * @param int $theme_id ID du thème
     * @return array|null Données du thème ou null
     */
    public function getThemeById($theme_id) {
        $sql = "SELECT 
                    th.*, 
                    ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
                    us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom
                FROM themes th
                JOIN utilisateurs ue ON th.encadreur_id = ue.id
                LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                WHERE th.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur de préparation getThemeById: " . $this->conn->error); return null; }
        $stmt->bind_param("i", $theme_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Récupère tous les thèmes créés par un encadreur, avec pagination.
     * @param int $encadreur_id ID de l'encadreur
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de thèmes par page
     * @return array Contenant les thèmes et les infos de pagination
     */
    public function getThemesByEncadreur($encadreur_id, $recherche = '', $page = 1, $limit = 20) { // Limit 20
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        // Correction: Utiliser 'th' alias pour la table themes, 'ue' pour l'encadreur et 'us' pour le stagiaire
        $where_clauses = ["th.encadreur_id = ?"];
        $params = [$encadreur_id];
        $types = "i";

        if (!empty($recherche)) {
            $where_clauses[] = "(th.titre LIKE ? OR th.description LIKE ? OR th.filiere LIKE ? OR us.prenom LIKE ? OR us.nom LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sssss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de thèmes
        $count_sql = "SELECT COUNT(*) as total 
                      FROM themes th
                      LEFT JOIN utilisateurs ue ON th.encadreur_id = ue.id
                      LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                      $where_full_clause";
        
        $stmt_count = $this->conn->prepare($count_sql);
        if ($stmt_count === false) { error_log("Erreur préparation comptage themes encadreur: " . $this->conn->error); return ['themes' => [], 'total_themes' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0]; }
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total_themes = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les thèmes paginés
        $sql = "SELECT 
                    th.*, 
                    ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
                    us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom 
                FROM themes th
                LEFT JOIN utilisateurs ue ON th.encadreur_id = ue.id
                LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                $where_full_clause
                ORDER BY th.id DESC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur préparation requête getThemesByEncadreur: " . $this->conn->error); return ['themes' => [], 'total_themes' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0]; }
        $stmt->bind_param($types_main_query, ...$params_main_query);
        $stmt->execute();
        $themes_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_themes / $limit);

        return [
            'themes' => $themes_result_set,
            'total_themes' => $total_themes,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }

    /**
     * Récupère le thème assigné à un stagiaire.
     * @param int $stagiaire_id ID du stagiaire.
     * @return array|null Données du thème ou null
     */
    public function getThemeByStagiaire($stagiaire_id) {
        $sql = "SELECT 
                    th.*, 
                    ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
                    us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom 
                FROM themes th
                LEFT JOIN utilisateurs ue ON th.encadreur_id = ue.id
                LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                WHERE th.stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur préparation getThemeByStagiaire: " . $this->conn->error); return null; }
        $stmt->bind_param("i", $stagiaire_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Liste tous les thèmes du système (utilisé par l'administrateur) avec pagination.
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de thèmes par page
     * @return array Contenant les thèmes et les infos de pagination
     */
    public static function listerTousLesThemes($recherche = '', $page = 1, $limit = 20) { // Limit 20
        $conn = Database::getConnection();
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";

        if (!empty($recherche)) {
            // La recherche se fait sur le titre, la filière, ou le nom de l'encadreur
            $where_clauses[] = "(th.titre LIKE ? OR th.filiere LIKE ? OR ue.prenom LIKE ? OR ue.nom LIKE ?)";
            $searchTerm = "%" . $recherche . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "ssss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de thèmes
        $count_sql = "SELECT COUNT(*) as total 
                      FROM themes th
                      LEFT JOIN utilisateurs ue ON th.encadreur_id = ue.id
                      LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                      $where_full_clause";
        
        $stmt_count = $conn->prepare($count_sql);
        if ($stmt_count === false) { error_log("Erreur préparation comptage tous themes: " . $conn->error); return ['themes' => [], 'total_themes' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0]; }
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $total_themes = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les thèmes paginés
        $sql = "SELECT 
                    th.*, 
                    ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
                    us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom
                FROM themes th
                LEFT JOIN utilisateurs ue ON th.encadreur_id = ue.id
                LEFT JOIN utilisateurs us ON th.stagiaire_id = us.id
                $where_full_clause
                ORDER BY th.titre ASC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur préparation requête listerTousLesThemes: " . $conn->error); return ['themes' => [], 'total_themes' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0]; }
        
        $stmt->bind_param($types_main_query, ...$params_main_query);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête listerTousLesThemes: " . $stmt->error);
            return ['themes' => [], 'total_themes' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $themes_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_themes / $limit);

        return [
            'themes' => $themes_result_set,
            'total_themes' => $total_themes,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }
}