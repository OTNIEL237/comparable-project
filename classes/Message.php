<?php
/**
 * Classe Message - Gestion complète de la messagerie
 * Fichier à placer dans : comparable-project/classes/Message.php
 */
require_once __DIR__ . '/Database.php';

class Message {
    private $conn;
    private $user_id;
    // private $db; // Cette propriété n'est pas utilisée dans les méthodes fournies, elle peut être retirée si inutile ailleurs.

    /**
     * Constructeur
     * @param int $user_id ID de l'utilisateur connecté
     */
    public function __construct($user_id) {
        $this->conn = Database::getConnection();
        $this->user_id = $user_id;
        // $this->db = Database::getInstance(); // Peut être retirée
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
            if ($stmt === false) {
                throw new Exception("Erreur de préparation de l'insertion du message: " . $this->conn->error);
            }
            $stmt->bind_param("iiss", $this->user_id, $destinataire_id, $sujet, $contenu);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'insertion du message: " . $stmt->error);
            }
            
            $message_id = $this->conn->insert_id;
            $stmt->close(); // Fermer le statement

            // Gérer le fichier joint si présent
            if ($fichier_upload && isset($fichier_upload['error']) && $fichier_upload['error'] === UPLOAD_ERR_OK) {
                $chemin_fichier = $this->uploadFichier($fichier_upload);
                if ($chemin_fichier) {
                    $this->ajouterPieceJointe($message_id, $fichier_upload['name'], $chemin_fichier);
                } else {
                    error_log("Échec de l'upload du fichier pour le message ID: " . $message_id);
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
            // Utiliser 0755 pour les dossiers pour une meilleure sécurité en production
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("Échec de la création du répertoire d'upload: " . $upload_dir);
                return false;
            }
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($fichier_upload['name'], PATHINFO_EXTENSION); // Correction ici, utiliser PATHINFO_EXTENSION
        // Assurer que l'extension est sûre pour éviter les problèmes de parcours de chemin
        $safe_extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        if (empty($safe_extension)) $safe_extension = 'bin'; // Par défaut 'bin' si aucune extension valide

        $nom_fichier = time() . '_' . uniqid() . '.' . $safe_extension;
        $chemin_complet = $upload_dir . $nom_fichier;
        
        // Vérifier la taille du fichier (max 10MB)
        if ($fichier_upload['size'] > 10 * 1024 * 1024) {
            error_log("La taille du fichier dépasse la limite de 10MB: " . $fichier_upload['name']);
            return false;
        }
        
        // Extensions autorisées
        $extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (!in_array(strtolower($safe_extension), $extensions_autorisees)) {
            error_log("Extension de fichier non supportée: " . $safe_extension);
            return false;
        }
        
        if (move_uploaded_file($fichier_upload['tmp_name'], $chemin_complet)) {
            return $nom_fichier;
        }
        
        error_log("Échec du déplacement du fichier uploadé: " . $fichier_upload['name'] . " vers " . $chemin_complet);
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
        if ($stmt === false) {
            error_log("Erreur de préparation de l'ajout de pièce jointe: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("iss", $message_id, $nom_fichier, $chemin);
        $success = $stmt->execute();
        $stmt->close(); // Fermer le statement
        if (!$success) {
            error_log("Échec de l'ajout de pièce jointe à la BDD: " . $this->conn->error);
        }
        return $success;
    }

    /**
     * Récupérer les messages de l'utilisateur avec filtres et pagination
     * @param string $filter Filtre (all, unread, sent)
     * @param string $search Terme de recherche
     * @param int $page Page actuelle
     * @param int $limit Nombre de messages par page (défaut 20)
     * @return array Contenant les messages, le nombre total et les infos de pagination
     */
    public function getMessages($filter = 'all', $search = '', $page = 1, $limit = 20) {
        // Assurer que $page et $limit sont des entiers positifs
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        // Clauses WHERE initiales et paramètres
        $where_clauses = ["(m.expediteur_id = ? OR m.destinataire_id = ?)"];
        $params = [$this->user_id, $this->user_id];
        $types = "ii";

        if ($filter === 'unread') {
            $where_clauses[] = "m.lu = 0 AND m.destinataire_id = ?";
            $params[] = $this->user_id;
            $types .= "i";
        } elseif ($filter === 'sent') {
            $where_clauses[] = "m.expediteur_id = ?";
            $params[] = $this->user_id;
            $types .= "i";
        }

        if (!empty($search)) {
            $where_clauses[] = "(m.sujet LIKE ? OR m.contenu LIKE ? OR u_exp.nom LIKE ? OR u_exp.prenom LIKE ? OR u_dest.nom LIKE ? OR u_dest.prenom LIKE ?)";
            $searchTerm = "%$search%";
            // Ajouter le terme de recherche plusieurs fois pour chaque condition LIKE
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "ssssss";
        }

        $where_full_clause = "WHERE " . implode(" AND ", $where_clauses);

        // 1. Compter le nombre total de messages
        $count_sql = "SELECT COUNT(*) as total 
                      FROM messages m
                      JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
                      JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
                      $where_full_clause";
        
        $stmt_count = $this->conn->prepare($count_sql);
        if ($stmt_count === false) {
             error_log("Échec de la préparation du statement de comptage: " . $this->conn->error);
             return ['messages' => new mysqli_result(new mysqli()), 'total_messages' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total_messages = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        // 2. Récupérer les messages paginés
        $sql = "SELECT m.*, 
                       u_exp.nom AS exp_nom, u_exp.prenom AS exp_prenom,
                       u_dest.nom AS dest_nom, u_dest.prenom AS dest_prenom,
                       (SELECT COUNT(*) FROM message_pieces_jointes WHERE message_id = m.id) as nb_pieces_jointes
                FROM messages m
                JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
                JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
                $where_full_clause
                ORDER BY m.date_envoi DESC
                LIMIT ? OFFSET ?";

        $offset = ($page - 1) * $limit;

        // Combiner les paramètres pour la requête principale : paramètres de recherche/filtre, puis limit, puis offset
        $params_main_query = array_merge($params, [$limit, $offset]);
        $types_main_query = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             error_log("Échec de la préparation du statement principal des messages: " . $this->conn->error);
             return ['messages' => new mysqli_result(new mysqli()), 'total_messages' => 0, 'current_page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        $stmt->bind_param($types_main_query, ...$params_main_query);
        $stmt->execute();
        $messages_result = $stmt->get_result();
        $stmt->close();

        $total_pages = ceil($total_messages / $limit);
        // Ajuster la page actuelle si elle dépasse le nombre total de pages (peut arriver si des messages sont supprimés)
        if ($total_messages > 0 && $page > $total_pages) {
            $page = $total_pages;
        } elseif ($total_messages === 0) {
            $page = 1; // Si aucun message, la page est 1
        }


        return [
            'messages' => $messages_result, // Ceci est un objet mysqli_result, à parcourir dans le script appelant.
            'total_messages' => $total_messages,
            'current_page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages
        ];
    }

    /**
     * Récupérer un message par son ID avec ses pièces jointes
     * SÉCURITÉ: Vérifie que le message appartient ou est destiné à l'utilisateur connecté.
     * @param int $message_id ID du message
     * @return array|null Données du message ou null
     */
    public function getMessageById($message_id) {
        // Vérification de sécurité : S'assurer que le message appartient ou est destiné à l'utilisateur actuel
        $sql = "SELECT m.*,
                       u_exp.nom AS exp_nom, u_exp.prenom AS exp_prenom,
                       u_dest.nom AS dest_nom, u_dest.prenom AS dest_prenom
                FROM messages m
                JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
                JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
                WHERE m.id = ? AND (m.expediteur_id = ? OR m.destinataire_id = ?)";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             error_log("Échec de la préparation du statement getMessageById: " . $this->conn->error);
             return null;
        }
        $stmt->bind_param("iii", $message_id, $this->user_id, $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null; // Message non trouvé ou accès non autorisé
        }
        
        $message = $result->fetch_assoc();
        $stmt->close(); // Fermer le statement
        
        // Récupérer les pièces jointes
        $sql_pj = "SELECT * FROM message_pieces_jointes WHERE message_id = ?";
        $stmt_pj = $this->conn->prepare($sql_pj);
        if ($stmt_pj === false) {
             error_log("Échec de la préparation du statement des pièces jointes de getMessageById: " . $this->conn->error);
             $message['pieces_jointes'] = []; // Retourner quand même le message, mais sans pièces jointes
             return $message;
        }
        $stmt_pj->bind_param("i", $message_id);
        $stmt_pj->execute();
        $pieces_jointes = $stmt_pj->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_pj->close(); // Fermer le statement
        
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
        if ($stmt === false) {
             error_log("Échec de la préparation du statement marquerCommeLu: " . $this->conn->error);
             return false;
        }
        $stmt->bind_param("ii", $message_id, $this->user_id);
        $success = $stmt->execute();
        $stmt->close();
        if (!$success) {
            error_log("Échec de l'exécution de marquerCommeLu: " . $this->conn->error);
        }
        return $success;
    }

    /**
     * Compter les messages non lus
     * @return int Nombre de messages non lus
     */
    public function compterNonLus() {
        $sql = "SELECT COUNT(*) as count FROM messages WHERE destinataire_id = ? AND lu = 0";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             error_log("Échec de la préparation du statement compterNonLus: " . $this->conn->error);
             return 0;
        }
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$result['count'];
    }

    /**
     * Supprimer un message (suppression définitive)
     * SÉCURITÉ: Seul l'administrateur peut supprimer n'importe quel message.
     *           L'expéditeur ou le destinataire NE PEUVENT PLUS supprimer via cette méthode.
     * @param int $message_id ID du message
     * @param string $user_role Le rôle de l'utilisateur connecté (stagiaire, encadreur, admin)
     * @return bool Succès de la suppression
     */
        public function supprimerMessage($message_id, $user_role) {
        // Seul l'administrateur est autorisé à supprimer un message
        if ($user_role !== 'admin') {
            error_log("Tentative non autorisée de supprimer le message ID: " . $message_id . " par l'utilisateur ID: " . $this->user_id . " (Rôle: " . $user_role . ")");
            return ['success' => false, 'message' => 'Accès non autorisé pour supprimer ce message.'];
        }
        
        try {
            $this->conn->begin_transaction();
            
            // Étape 1: Récupérer les chemins des fichiers joints pour supprimer les fichiers physiques
            $sql_get_files = "SELECT chemin FROM message_pieces_jointes WHERE message_id = ?";
            $stmt_get_files = $this->conn->prepare($sql_get_files);
            if ($stmt_get_files === false) {
                 throw new Exception("Échec de la préparation du statement de récupération des chemins de pièces jointes: " . $this->conn->error);
            }
            $stmt_get_files->bind_param("i", $message_id);
            $stmt_get_files->execute();
            $file_paths = $stmt_get_files->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_get_files->close();

            // Étape 2: Supprimer les pièces jointes de la base de données
            $sql_del_pj = "DELETE FROM message_pieces_jointes WHERE message_id = ?";
            $stmt_del_pj = $this->conn->prepare($sql_del_pj);
             if ($stmt_del_pj === false) {
                 throw new Exception("Échec de la préparation du statement de suppression des pièces jointes: " . $this->conn->error);
            }
            $stmt_del_pj->bind_param("i", $message_id);
            $stmt_del_pj->execute();
            $stmt_del_pj->close();
            
            // Étape 3: Supprimer le message principal
            $sql_del_msg = "DELETE FROM messages WHERE id = ?";
            $stmt_del_msg = $this->conn->prepare($sql_del_msg);
             if ($stmt_del_msg === false) {
                 throw new Exception("Échec de la préparation du statement de suppression du message: " . $this->conn->error);
            }
            $stmt_del_msg->bind_param("i", $message_id);
            $stmt_del_msg->execute();
            $stmt_del_msg->close();
            
            $this->conn->commit();

            // Étape 4: Supprimer les fichiers physiques après une transaction BDD réussie
            $upload_dir = __DIR__ . "/../uploads/messages/";
            foreach ($file_paths as $file_info) {
                $file_to_delete = $upload_dir . $file_info['chemin'];
                if (file_exists($file_to_delete) && is_file($file_to_delete)) {
                    unlink($file_to_delete);
                } else {
                    error_log("Tentative de supprimer un fichier inexistant ou un répertoire: " . $file_to_delete);
                }
            }

            return ['success' => true, 'message' => 'Message supprimé avec succès.'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Erreur lors de la suppression du message (ID: " . $message_id . "): " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression du message.'];
        }
    }

    /**
     * Obtenir la liste des utilisateurs pour sélection destinataire
     * @return array Liste des utilisateurs
     */
    public function getUtilisateursDisponibles() {
        $sql = "SELECT id, nom, prenom, role FROM utilisateurs WHERE id != ? ORDER BY nom, prenom";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             error_log("Échec de la préparation du statement getUtilisateursDisponibles: " . $this->conn->error);
             return [];
        }
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}