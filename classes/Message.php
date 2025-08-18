<?php
/**
 * Classe Message - Gestion complète de la messagerie
 * Fichier à placer dans : comparable-project/classes/Message.php
 */
require_once __DIR__ . '/Database.php';

class Message {
    private $conn;
    private $user_id;
    private $db;

    /**
     * Constructeur
     * @param int $user_id ID de l'utilisateur connecté
     */
    public function __construct($user_id) {
        $this->conn = Database::getConnection();
        $this->user_id = $user_id;
         $this->db = Database::getInstance();
    }

    /**
     * Envoyer un message avec gestion des pièces jointes
     * @param int $destinataire_id ID du destinataire
     * @param string $sujet Sujet du message
     * @param string $contenu Contenu du message
     * @param array|null $fichier_upload Fichier uploadé ($_FILES['fichier'])
     * @return bool Succès de l'envoi
     */

    public function envoyer($destinataire_id, $sujet, $contenu, $fichier_upload = null) {
        try {
            $this->conn->begin_transaction();
            
            // Insérer le message principal
            $sql = "INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu, date_envoi) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiss", $this->user_id, $destinataire_id, $sujet, $contenu);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'insertion du message");
            }
            
            $message_id = $this->conn->insert_id;
            
            // Gérer le fichier joint si présent
            if ($fichier_upload && isset($fichier_upload['error']) && $fichier_upload['error'] === UPLOAD_ERR_OK) {
                $chemin_fichier = $this->uploadFichier($fichier_upload);
                if ($chemin_fichier) {
                    $this->ajouterPieceJointe($message_id, $fichier_upload['name'], $chemin_fichier);
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Erreur envoi message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Uploader un fichier joint
     * @param array $fichier_upload Fichier uploadé
     * @return string|false Chemin du fichier ou false en cas d'erreur
     */
    private function uploadFichier($fichier_upload) {
        $upload_dir = __DIR__ . "/../uploads/messages/";
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($fichier_upload['name'], PATHINFO_EXTENSION);
        $nom_fichier = time() . '_' . uniqid() . '.' . $extension;
        $chemin_complet = $upload_dir . $nom_fichier;
        
        // Vérifier la taille du fichier (max 10MB)
        if ($fichier_upload['size'] > 10 * 1024 * 1024) {
            return false;
        }
        
        // Extensions autorisées
        $extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (!in_array(strtolower($extension), $extensions_autorisees)) {
            return false;
        }
        
        if (move_uploaded_file($fichier_upload['tmp_name'], $chemin_complet)) {
            return $nom_fichier;
        }
        
        return false;
    }

    /**
     * Ajouter une pièce jointe à un message
     * @param int $message_id ID du message
     * @param string $nom_fichier Nom original du fichier
     * @param string $chemin Chemin du fichier
     * @return bool Succès de l'ajout
     */
    private function ajouterPieceJointe($message_id, $nom_fichier, $chemin) {
        $sql = "INSERT INTO message_pieces_jointes (message_id, nom_fichier, chemin) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iss", $message_id, $nom_fichier, $chemin);
        return $stmt->execute();
    }

    /**
     * Récupérer les messages de l'utilisateur avec filtres
     * @param string $filter Filtre (all, unread, sent)
     * @param string $search Terme de recherche
     * @return mysqli_result Résultats de la requête
     */
    public function getMessages($filter = 'all', $search = '') {
        $sql = "SELECT m.*, 
                       u_exp.nom AS exp_nom, u_exp.prenom AS exp_prenom,
                       u_dest.nom AS dest_nom, u_dest.prenom AS dest_prenom,
                       (SELECT COUNT(*) FROM message_pieces_jointes WHERE message_id = m.id) as nb_pieces_jointes
                FROM messages m
                JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
                JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
                WHERE (m.expediteur_id = ? OR m.destinataire_id = ?)";
        
        $params = [$this->user_id, $this->user_id];
        $types = "ii";

        // Appliquer les filtres
        if ($filter === 'unread') {
            $sql .= " AND m.lu = 0 AND m.destinataire_id = ?";
            $params[] = $this->user_id;
            $types .= "i";
        } elseif ($filter === 'sent') {
            $sql .= " AND m.expediteur_id = ?";
            $params[] = $this->user_id;
            $types .= "i";
        }

        // Appliquer la recherche
        if (!empty($search)) {
            $sql .= " AND (m.sujet LIKE ? OR m.contenu LIKE ? OR u_exp.nom LIKE ? OR u_exp.prenom LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= "ssss";
        }

        $sql .= " ORDER BY m.date_envoi DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    }

    /**
     * Récupérer un message par son ID avec ses pièces jointes
     * @param int $message_id ID du message
     * @return array|null Données du message ou null
     */
public function getMessageById($message_id) {
    $sql = "SELECT m.*,
                   u_exp.nom AS exp_nom, u_exp.prenom AS exp_prenom,
                   u_dest.nom AS dest_nom, u_dest.prenom AS dest_prenom
            FROM messages m
            JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
            JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
            WHERE m.id = ?";
    
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $message = $result->fetch_assoc();
    
    // Récupérer les pièces jointes
    $sql_pj = "SELECT * FROM message_pieces_jointes WHERE message_id = ?";
    $stmt_pj = $this->conn->prepare($sql_pj);
    $stmt_pj->bind_param("i", $message_id);
    $stmt_pj->execute();
    $pieces_jointes = $stmt_pj->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $message['pieces_jointes'] = $pieces_jointes;
    
    return $message;
}

    /**
     * Marquer un message comme lu
     * @param int $message_id ID du message
     * @return bool Succès de l'opération
     */
    public function marquerCommeLu($message_id) {
        $sql = "UPDATE messages SET lu = 1 WHERE id = ? AND destinataire_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $message_id, $this->user_id);
        return $stmt->execute();
    }

    /**
     * Compter les messages non lus
     * @return int Nombre de messages non lus
     */
    public function compterNonLus() {
        $sql = "SELECT COUNT(*) as count FROM messages WHERE destinataire_id = ? AND lu = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['count'];
    }

    /**
     * Supprimer un message (soft delete - marquer comme supprimé)
     * @param int $message_id ID du message
     * @return bool Succès de la suppression
     */
    public function supprimerMessage($message_id) {
        // Vérifier que l'utilisateur a le droit de supprimer ce message
        $sql_check = "SELECT id FROM messages WHERE id = ? AND (expediteur_id = ? OR destinataire_id = ?)";
        $stmt_check = $this->conn->prepare($sql_check);
        $stmt_check->bind_param("iii", $message_id, $this->user_id, $this->user_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows === 0) {
            return false;
        }
        
        // Pour cette version, on peut ajouter une colonne 'supprime' ou vraiment supprimer
        // Ici, on supprime vraiment le message et ses pièces jointes
        try {
            $this->conn->begin_transaction();
            
            // Supprimer les pièces jointes de la base
            $sql_del_pj = "DELETE FROM message_pieces_jointes WHERE message_id = ?";
            $stmt_del_pj = $this->conn->prepare($sql_del_pj);
            $stmt_del_pj->bind_param("i", $message_id);
            $stmt_del_pj->execute();
            
            // Supprimer le message
            $sql_del_msg = "DELETE FROM messages WHERE id = ?";
            $stmt_del_msg = $this->conn->prepare($sql_del_msg);
            $stmt_del_msg->bind_param("i", $message_id);
            $stmt_del_msg->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    /**
     * Obtenir la liste des utilisateurs pour sélection destinataire
     * @return array Liste des utilisateurs
     */
    public function getUtilisateursDisponibles() {
        $sql = "SELECT id, nom, prenom, role FROM utilisateurs WHERE id != ? ORDER BY nom, prenom";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}