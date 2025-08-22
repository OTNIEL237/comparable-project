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
        // Gérer le fichier joint
        $fichier_joint = null;
        $nom_fichier_original = null;
        if (isset($file) && $file['error'] == 0) {
            $uploadDir = 'uploads/taches/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $nom_fichier_original = basename($file['name']);
            $extension = pathinfo($nom_fichier_original, PATHINFO_EXTENSION);
            $fichier_joint = uniqid('tache_', true) . '.' . $extension;
            move_uploaded_file($file['tmp_name'], $uploadDir . $fichier_joint);
        }

        $sql = "INSERT INTO taches (encadreur_id, stagiaire_id, titre, description, date_echeance, fichier_joint, nom_fichier_original, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')";
        
        $stmt = $this->conn->prepare($sql);
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
        
        return $stmt->execute();
    }

    /**
     * Met à jour une tâche existante
     */
    public function modifier($data, $file) {
        $tache_id = $data['tache_id'];
        
        // Gérer le nouveau fichier si fourni
        if (isset($file) && $file['error'] == 0) {
            // Supprimer l'ancien fichier
            $this->supprimerFichier($tache_id);
            
            $uploadDir = 'uploads/taches/';
            $nom_fichier_original = basename($file['name']);
            $extension = pathinfo($nom_fichier_original, PATHINFO_EXTENSION);
            $fichier_joint = uniqid('tache_', true) . '.' . $extension;
            move_uploaded_file($file['tmp_name'], $uploadDir . $fichier_joint);
            
            $sql = "UPDATE taches SET stagiaire_id=?, titre=?, description=?, date_echeance=?, fichier_joint=?, nom_fichier_original=? WHERE id=?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                "isssssi",
                $data['stagiaire_id'],
                $data['titre'],
                $data['description'],
                $data['date_echeance'],
                $fichier_joint,
                $nom_fichier_original,
                $tache_id
            );
        } else {
            // Mettre à jour sans changer le fichier
            $sql = "UPDATE taches SET stagiaire_id=?, titre=?, description=?, date_echeance=? WHERE id=?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                "isssi",
                $data['stagiaire_id'],
                $data['titre'],
                $data['description'],
                $data['date_echeance'],
                $tache_id
            );
        }
        
        return $stmt->execute();
    }

    /**
     * Supprime une tâche
     */
    public function supprimer($tache_id) {
        $this->supprimerFichier($tache_id);
        $sql = "DELETE FROM taches WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $tache_id);
        return $stmt->execute();
    }
    
    /**
     * Supprime le fichier associé à une tâche
     */
    private function supprimerFichier($tache_id) {
        $sql = "SELECT fichier_joint FROM taches WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $tache_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result && !empty($result['fichier_joint'])) {
            $filePath = 'uploads/taches/' . $result['fichier_joint'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }


    /**
     * Marque une tâche comme terminée
     */
    public function marquerTerminee($tache_id, $stagiaire_id) {
        // Vérifier que la tâche appartient bien au stagiaire
        $sql = "UPDATE taches SET statut = 'terminee', date_completion = NOW() WHERE id = ? AND stagiaire_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $tache_id, $stagiaire_id);
        return $stmt->execute();
    }

    /**
     * Récupère les tâches pour un encadreur, avec recherche
     */
     public function getTachesPourEncadreur($encadreur_id, $recherche = '') {
        // CORRECTION : Ajout des alias AS pour correspondre à la méthode de l'admin
    $sql = "SELECT t.*, u.prenom, u.nom 
        FROM taches t
        JOIN utilisateurs u ON t.stagiaire_id = u.id
        WHERE t.encadreur_id = ?";
        
        if (!empty($recherche)) {
            $sql .= " AND (t.titre LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ?)";
        }
        
        $sql .= " ORDER BY t.date_echeance DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($recherche)) {
            $searchTerm = "%" . $recherche . "%";
            $stmt->bind_param("isss", $encadreur_id, $searchTerm, $searchTerm, $searchTerm);
        } else {
            $stmt->bind_param("i", $encadreur_id);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Récupère une tâche par son ID
     */
    public function getTacheById($tache_id) {
        $sql = "SELECT * FROM taches WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $tache_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }


    /**
     * Récupère les tâches pour un stagiaire
     */
    public function getTachesPourStagiaire($stagiaire_id, $filtre = 'toutes', $recherche = '') {
        // La mise à jour des statuts en retard reste
        $this->updateStatutsEnRetard($stagiaire_id);
        
        $sql = "SELECT * FROM taches WHERE stagiaire_id = ?";
        $params = [$stagiaire_id];
        $types = "i";
        
        // Application du filtre de statut
        switch($filtre) {
            case 'en_cours':
                $sql .= " AND statut = 'en_attente' AND date_echeance >= CURDATE()";
                break;
            case 'terminees':
                $sql .= " AND statut = 'terminee'";
                break;
            case 'en_retard':
                $sql .= " AND statut = 'en_retard'";
                break;
        }

        // NOUVEAU : Application du filtre de recherche
        if (!empty($recherche)) {
            $sql .= " AND (titre LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm);
            $types .= "ss";
        }

        $sql .= " ORDER BY date_echeance ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
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
        $stmt->bind_param("i", $stagiaire_id);
        $stmt->execute();
    }

     public static function listerToutesLesTaches($recherche = '') {
        $conn = Database::getConnection();
        
        $sql = "SELECT t.*, 
                       us.prenom AS stagiaire_prenom, us.nom AS stagiaire_nom,
                       ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom
                FROM taches t
                JOIN utilisateurs us ON t.stagiaire_id = us.id
                LEFT JOIN utilisateurs ue ON t.encadreur_id = ue.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if (!empty($recherche)) {
            $sql .= " AND (t.titre LIKE ? OR us.prenom LIKE ? OR us.nom LIKE ? OR ue.prenom LIKE ? OR ue.nom LIKE ?)";
            $searchTerm = "%" . $recherche . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sssss";
        }
        
        $sql .= " ORDER BY t.date_echeance DESC";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($recherche)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }

    



}
?>