<?php
/**
 * Classe Tache - Gestion des tâches
 * Fichier à placer dans : classes/Tache.php
 */
require_once 'Database.php';

class Tache {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Crée une nouvelle tâche
     */
    public function creer($data, $file) {
        $this->conn->begin_transaction();
        $fichier_joint = null;

        try {
            // 1. Gérer le fichier joint
            $nom_fichier_original = null;
            if (isset($file) && $file['error'] == 0) {
                $uploadDir = __DIR__ . '/../uploads/taches/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) { // Utiliser 0755 pour la sécurité
                        throw new Exception("Échec de la création du répertoire d'upload.");
                    }
                }
                $nom_fichier_original = basename($file['name']);
                $extension = pathinfo($nom_fichier_original, PATHINFO_EXTENSION);
                $fichier_joint = uniqid('tache_', true) . '.' . $extension;
                
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fichier_joint)) {
                    throw new Exception("Échec du téléversement du fichier.");
                }
            }

            // 2. Insérer en base de données
            $sql = "INSERT INTO taches (encadreur_id, stagiaire_id, titre, description, date_echeance, fichier_joint, nom_fichier_original, statut) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erreur de préparation de la requête : " . $this->conn->error);
            }

            $stmt->bind_param(
                "iisssss",
                $data['encadreur_id'],
                $data['stagiaire_id'],
                $data['titre'],
                $data['description'],
                $data['date_echeance'],
                $fichier_joint,
                $nom_fichier_original
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'enregistrement de la tâche : " . $stmt->error);
            }

            // 3. Si tout va bien, valider la transaction
            $this->conn->commit();
            return ['success' => true, 'message' => 'Tâche créée avec succès.'];

        } catch (Exception $e) {
            // 4. En cas d'erreur, annuler la transaction
            $this->conn->rollback();

            // Et supprimer le fichier si il a été téléversé
            if ($fichier_joint && file_exists(__DIR__ . '/../uploads/taches/' . $fichier_joint)) {
                unlink(__DIR__ . '/../uploads/taches/' . $fichier_joint);
            }
            
            // Log l'erreur pour le debug
            error_log("Erreur création tâche: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Met à jour une tâche existante
     */
    public function modifier($data, $file) {
        $tache_id = $data['tache_id'];
        $fichier_joint = null;
        $nom_fichier_original = null;
    
        try {
            $this->conn->begin_transaction();
    
            // Gérer le nouveau fichier si fourni
            if (isset($file) && $file['error'] == 0) {
                // Supprimer l'ancien fichier
                $this->supprimerFichier($tache_id); // Supprime le fichier physique si existant
                
                $uploadDir = __DIR__ . '/../uploads/taches/'; // Chemin absolu
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception("Échec de la création du répertoire d'upload pour modification.");
                    }
                }
                $nom_fichier_original = basename($file['name']);
                $extension = pathinfo($nom_fichier_original, PATHINFO_EXTENSION);
                $fichier_joint = uniqid('tache_', true) . '.' . $extension;
                
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fichier_joint)) {
                    throw new Exception("Échec du téléversement du nouveau fichier pour modification.");
                }
            } else {
                // Si pas de nouveau fichier, récupérer l'ancien nom de fichier stocké pour le réutiliser
                $sql_get_old_file = "SELECT fichier_joint, nom_fichier_original FROM taches WHERE id = ?";
                $stmt_get_old_file = $this->conn->prepare($sql_get_old_file);
                if ($stmt_get_old_file === false) { throw new Exception("Erreur de préparation SELECT old file: " . $this->conn->error); }
                $stmt_get_old_file->bind_param("i", $tache_id);
                $stmt_get_old_file->execute();
                $old_file_data = $stmt_get_old_file->get_result()->fetch_assoc();
                $stmt_get_old_file->close();
                
                if ($old_file_data) {
                    $fichier_joint = $old_file_data['fichier_joint'];
                    $nom_fichier_original = $old_file_data['nom_fichier_original'];
                }
            }
    
            // Mettre à jour la base de données
            $sql = "UPDATE taches SET stagiaire_id=?, titre=?, description=?, date_echeance=?, fichier_joint=?, nom_fichier_original=? WHERE id=?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Erreur de préparation de la requête de modification : " . $this->conn->error);
            }
    
            $stmt->bind_param(
                "isssssi",
                $data['stagiaire_id'],
                $data['titre'],
                $data['description'],
                $data['date_echeance'],
                $fichier_joint, // Peut être null ou le nouveau/ancien nom
                $nom_fichier_original, // Peut être null ou le nouveau/ancien nom
                $tache_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'enregistrement de la tâche : " . $stmt->error);
            }
            $stmt->close();
    
            $this->conn->commit();
            return ['success' => true, 'message' => 'Tâche mise à jour avec succès.'];
    
        } catch (Exception $e) {
            $this->conn->rollback();
    
            // Supprimer le nouveau fichier si l'insertion DB a échoué après un upload réussi
            if ($fichier_joint && file_exists(__DIR__ . '/../uploads/taches/' . $fichier_joint)) {
                unlink(__DIR__ . '/../uploads/taches/' . $fichier_joint);
            }
            
            error_log("Erreur modification tâche: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    

    /**
     * Supprime une tâche
     * @return array Résultat avec succès et message
     */
    public function supprimer($tache_id) {
        $this->conn->begin_transaction();
        try {
            // Supprimer le fichier associé
            $this->supprimerFichier($tache_id); // Supprime le fichier physique

            // Supprimer l'entrée de la tâche de la base de données
            $sql = "DELETE FROM taches WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) { throw new Exception("Erreur de préparation DELETE tâche: " . $this->conn->error); }
            $stmt->bind_param("i", $tache_id);
            if (!$stmt->execute()) { throw new Exception("Erreur d'exécution DELETE tâche: " . $stmt->error); }
            $stmt->close();
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Tâche supprimée avec succès.'];

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Erreur suppression tâche: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Supprime le fichier physique associé à une tâche après récupération de son chemin.
     * @param int $tache_id ID de la tâche
     * @return bool Vrai si le fichier a été supprimé ou n'existait pas, faux si erreur.
     */
    private function supprimerFichier($tache_id) {
        $sql = "SELECT fichier_joint FROM taches WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Erreur de préparation SELECT fichier_joint pour suppression: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $tache_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $filePathInDb = $result->fetch_assoc()['fichier_joint'] ?? null;
        $result->free();
        $stmt->close();
    
        if ($filePathInDb && !empty($filePathInDb)) {
            $fullPath = __DIR__ . '/../uploads/taches/' . $filePathInDb;
            if (file_exists($fullPath) && is_file($fullPath)) {
                return unlink($fullPath);
            }
        }
        return true; // Retourne vrai si pas de fichier ou si déjà inexistant
    }


    /**
     * Marque une tâche comme terminée
     */
    public function marquerTerminee($tache_id, $stagiaire_id) {
        // Vérifier que la tâche appartient bien au stagiaire
        $sql = "UPDATE taches SET statut = 'terminee', date_completion = NOW() WHERE id = ? AND stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur préparation marquerTerminee: " . $this->conn->error); return false; }
        $stmt->bind_param("ii", $tache_id, $stagiaire_id);
        $success = $stmt->execute();
        $stmt->close();
        if (!$success) { error_log("Erreur exécution marquerTerminee: " . $stmt->error); }
        return ['success' => $success]; // Retourne un tableau pour la cohérence AJAX
    }

    /**
     * Récupère les tâches pour un encadreur, avec recherche et pagination
     * @param int $encadreur_id ID de l'encadreur
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de tâches par page
     * @return array Contenant les tâches et les infos de pagination
     */
    public function getTachesPourEncadreur($encadreur_id, $recherche = '', $page = 1, $limit = 20) { // Limit 20
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $where_clauses = ["t.encadreur_id = ?"];
        $params = [$encadreur_id];
        $types = "i";
        
        if (!empty($recherche)) {
            $where_clauses[] = "(t.titre LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ?)";
            $searchTerm = "%" . $recherche . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de tâches
        $count_sql = "SELECT COUNT(*) as total 
                      FROM taches t
                      JOIN utilisateurs u ON t.stagiaire_id = u.id
                      $where_full_clause";
        
        $stmt_count = $this->conn->prepare($count_sql);
        if (!$stmt_count) {
             error_log("Erreur préparation comptage taches encadreur: " . $this->conn->error);
             return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total_tasks = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les tâches paginées
        $sql = "SELECT t.*, u.prenom AS stagiaire_prenom, u.nom AS stagiaire_nom 
                FROM taches t
                JOIN utilisateurs u ON t.stagiaire_id = u.id
                $where_full_clause
                ORDER BY t.date_echeance DESC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getTachesPourEncadreur: " . $this->conn->error);
            return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        $stmt->bind_param($types_main_query, ...$params_main_query);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête getTachesPourEncadreur: " . $stmt->error);
            return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $tasks_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_tasks / $limit);

        return [
            'tasks' => $tasks_result_set,
            'total_tasks' => $total_tasks,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }
    
    /**
     * Récupère une tâche par son ID
     * @return array|null Données de la tâche ou null
     */
     public function getTacheById($tache_id) {
        $sql = "SELECT 
                    t.*,
                    us.prenom AS stagiaire_prenom,
                    us.nom AS stagiaire_nom,
                    ue.prenom AS encadreur_prenom,
                    ue.nom AS encadreur_nom
                FROM taches t
                LEFT JOIN utilisateurs us ON t.stagiaire_id = us.id
                LEFT JOIN utilisateurs ue ON t.encadreur_id = ue.id
                WHERE t.id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur préparation getTacheById: " . $this->conn->error); return null; }
        $stmt->bind_param("i", $tache_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }


    /**
     * Récupère les tâches pour un stagiaire avec pagination
     * @param int $stagiaire_id ID du stagiaire
     * @param string $filtre Filtre (toutes, en_cours, terminees, en_retard)
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de tâches par page
     * @return array Contenant les tâches et les infos de pagination
     */
    public function getTachesPourStagiaire($stagiaire_id, $filtre = 'toutes', $recherche = '', $page = 1, $limit = 20) { // Limit 20
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        // La mise à jour des statuts en retard reste, mais ne retourne rien
        $this->updateStatutsEnRetard($stagiaire_id);
        
        $where_clauses = ["stagiaire_id = ?"];
        $params = [$stagiaire_id];
        $types = "i";
        
        // Application du filtre de statut
        switch($filtre) {
            case 'en_cours':
                $where_clauses[] = "statut = 'en_attente' AND date_echeance >= CURDATE()";
                break;
            case 'terminees':
                $where_clauses[] = "statut = 'terminee'";
                break;
            case 'en_retard':
                $where_clauses[] = "statut = 'en_retard'";
                break;
        }

        // NOUVEAU : Application du filtre de recherche
        if (!empty($recherche)) {
            $where_clauses[] = "(titre LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm);
            $types .= "ss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de tâches
        $count_sql = "SELECT COUNT(*) as total 
                      FROM taches 
                      $where_full_clause";
        
        $stmt_count = $this->conn->prepare($count_sql);
        if (!$stmt_count) {
             error_log("Erreur préparation comptage taches stagiaire: " . $this->conn->error);
             return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total_tasks = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les tâches paginées
        $sql = "SELECT * FROM taches 
                $where_full_clause
                ORDER BY date_echeance ASC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête getTachesPourStagiaire: " . $this->conn->error);
            return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt->bind_param($types_main_query, ...$params_main_query);
        $stmt->execute();
        $tasks_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_tasks / $limit);

        return [
            'tasks' => $tasks_result_set,
            'total_tasks' => $total_tasks,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }
    
    /**
     * Met à jour le statut des tâches non terminées dont la date d'échéance est passée
     */
    private function updateStatutsEnRetard($stagiaire_id) {
        $sql = "UPDATE taches 
                SET statut = 'en_retard' 
                WHERE stagiaire_id = ? 
                AND statut = 'en_attente' 
                AND date_echeance < CURDATE()";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { error_log("Erreur préparation updateStatutsEnRetard: " . $this->conn->error); return false; }
        $stmt->bind_param("i", $stagiaire_id);
        $success = $stmt->execute();
        $stmt->close();
        if (!$success) { error_log("Erreur exécution updateStatutsEnRetard: " . $stmt->error); }
        return $success;
    }

    /**
     * Liste toutes les tâches du système (utilisé par l'administrateur) avec pagination
     * @param string $recherche Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de tâches par page
     * @return array Contenant les tâches et les infos de pagination
     */
     public static function listerToutesLesTaches($recherche = '', $page = 1, $limit = 20) { // Limit 20
        $conn = Database::getConnection();
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";

        if (!empty($recherche)) {
            $where_clauses[] = "(t.titre LIKE ? OR us.prenom LIKE ? OR us.nom LIKE ? OR ue.prenom LIKE ? OR ue.nom LIKE ?)";
            $searchTerm = "%" . $recherche . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sssss";
        }
        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de tâches
        $count_sql = "SELECT COUNT(*) as total 
                      FROM taches t
                      JOIN utilisateurs us ON t.stagiaire_id = us.id
                      LEFT JOIN utilisateurs ue ON t.encadreur_id = ue.id
                      $where_full_clause";
        
        $stmt_count = $conn->prepare($count_sql);
        if (!$stmt_count) {
             error_log("Erreur préparation comptage toutes taches: " . $conn->error);
             return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $total_tasks = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les tâches paginées
        $sql = "SELECT t.*, 
                       us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom,
                       ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom
                FROM taches t
                JOIN utilisateurs us ON t.stagiaire_id = us.id
                LEFT JOIN utilisateurs ue ON t.encadreur_id = ue.id
                $where_full_clause
                ORDER BY t.date_echeance DESC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $limit;
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur préparation requête listerToutesLesTaches: " . $conn->error);
            return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        $stmt->bind_param($types_main_query, ...$params_main_query);
        
        if (!$stmt->execute()) {
            error_log("Erreur exécution requête listerToutesLesTaches: " . $stmt->error);
            return ['tasks' => [], 'total_tasks' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $tasks_result_set = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_tasks / $limit);

        return [
            'tasks' => $tasks_result_set,
            'total_tasks' => $total_tasks,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }
}