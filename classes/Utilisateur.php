<?php
// classes/Utilisateur.php

class Utilisateur {
    private $id;
    
    public function __construct($user_id) {
        $this->id = $user_id;
    }
    
    public function getProfileData() {
        $db = Database::getConnection();
        $role = $_SESSION['role'];
        
        $query = "SELECT u.*";
        
        if ($role === 'stagiaire') {
            $query .= ", s.filiere, s.niveau, s.date_debut, s.date_fin, 
                       e.prenom AS encadreur_prenom, e.nom AS encadreur_nom
                       FROM utilisateurs u
                       LEFT JOIN stagiaire s ON u.id = s.id_utilisateur
                       LEFT JOIN utilisateurs e ON s.encadreur_id = e.id
                       WHERE u.id = ?";
        } elseif ($role === 'encadreur') {
            $query .= ", enc.poste, enc.service, 
                       (SELECT COUNT(*) FROM stagiaire WHERE encadreur_id = u.id) AS nb_stagiaires
                       FROM utilisateurs u
                       LEFT JOIN encadreur enc ON u.id = enc.id_utilisateur
                       WHERE u.id = ?";
        } else { // Admin
            $query .= " FROM utilisateurs u WHERE u.id = ?";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    public function updateProfile($nom, $prenom, $email, $telephone) {
        $db = Database::getConnection();
        
        $query = "UPDATE utilisateurs 
                  SET nom = ?, prenom = ?, email = ?, telephone = ?
                  WHERE id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssssi", $nom, $prenom, $email, $telephone, $this->id);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de la mise à jour du profil");
        }
    }

    public function changePassword($current_password, $new_password) {
        $db = Database::getConnection();
        
        // Vérifier le mot de passe actuel
        $query = "SELECT password FROM utilisateurs WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception("Mot de passe actuel incorrect");
        }
        
        // Mettre à jour avec le nouveau mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE utilisateurs SET password = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("si", $hashed_password, $this->id);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de la mise à jour du mot de passe");
        }
    }

     public static function listerTous($recherche = '')
    {
        $conn = Database::getConnection();
        $sql = "SELECT 
                    u.id, u.nom, u.prenom, u.email, u.role, u.statut,
                    s.encadreur_id, 
                    enc_u.prenom as enc_prenom, 
                    enc_u.nom as enc_nom
                FROM utilisateurs u
                LEFT JOIN stagiaire s ON u.id = s.id_utilisateur
                LEFT JOIN utilisateurs enc_u ON s.encadreur_id = enc_u.id";
        
        $params = [];
        $types = "";

        if (!empty($recherche)) {
            $sql .= " WHERE u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sss";
        }
        $sql .= " ORDER BY u.role, u.nom, u.prenom";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Crée un nouvel utilisateur (stagiaire ou encadreur) avec une transaction.
     * @param array $data Données du formulaire.
     * @return bool Succès de la création.
     */
    public static function creer($data)
    {
        $conn = Database::getConnection();
        $conn->begin_transaction();
        try {
            // Hacher le mot de passe avant de l'insérer
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $sql_user = "INSERT INTO utilisateurs (nom, prenom, email, password, role, sex, telephone) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param(
                "sssssss", 
                $data['nom'], 
                $data['prenom'], 
                $data['email'], 
                $hashed_password, // Utiliser le mot de passe haché ici
                $data['role'], 
                $data['sex'], 
                $data['telephone']
            );
            
            if (!$stmt_user->execute()) {
                throw new Exception("Impossible de créer l'utilisateur. L'email existe peut-être déjà ou autre erreur de BDD.");
            }
            $user_id = $conn->insert_id;
            
            if ($data['role'] === 'stagiaire') {
                $sql_role = "INSERT INTO stagiaire (id_utilisateur, filiere, niveau, date_debut, date_fin) 
                             VALUES (?, ?, ?, ?, ?)";
                $stmt_role = $conn->prepare($sql_role);
                $stmt_role->bind_param("issss", $user_id, $data['filiere'], $data['niveau'], $data['date_debut'], $data['date_fin']);
                $stmt_role->execute();
            } elseif ($data['role'] === 'encadreur') {
                $sql_role = "INSERT INTO encadreur (id_utilisateur, poste, service) VALUES (?, ?, ?)";
                $stmt_role = $conn->prepare($sql_role);
                $stmt_role->bind_param("iss", $user_id, $data['poste'], $data['service']);
                $stmt_role->execute();
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Erreur création utilisateur: ' . $e->getMessage());
            // Retourner un message plus spécifique si c'est une erreur de duplicata d'email
            if ($e->getCode() === 1062) { // Code d'erreur MySQL pour duplicata (peut varier)
                return ['success' => false, 'message' => 'L\'adresse email existe déjà.'];
            }
            return ['success' => false, 'message' => 'Une erreur est survenue lors de la création de l\'utilisateur.'];
        }
    }
    
    /**
     * Change le statut d'un utilisateur (actif/bloque).
     */
    public static function changerStatut($user_id, $statut)
    {
        $conn = Database::getConnection();
        $sql = "UPDATE utilisateurs SET statut = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $statut, $user_id);
        return $stmt->execute();
    }
    
    /**
     * Affecte un stagiaire à un encadreur.
     */
    public static function affecterEncadreur($stagiaire_id, $encadreur_id)
    {
        $conn = Database::getConnection();
        // Utiliser NULL si $encadreur_id est vide pour désaffecter
        $enc_id = empty($encadreur_id) ? null : (int)$encadreur_id;
        
        $sql = "UPDATE stagiaire SET encadreur_id = ? WHERE id_utilisateur = ?";
        $stmt = $conn->prepare($sql);
        
        if ($enc_id === null) { // Pour bind_param, null doit être géré avec 's'
            $stmt->bind_param("si", $enc_id, $stagiaire_id);
        } else {
            $stmt->bind_param("ii", $enc_id, $stagiaire_id);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Récupère la liste de tous les encadreurs actifs.
     */
    public static function listerEncadreurs()
    {
        $conn = Database::getConnection();
        $sql = "SELECT id, prenom, nom FROM utilisateurs WHERE role = 'encadreur' AND statut = 'actif' ORDER BY nom, prenom";
        return $conn->query($sql);
    }

        /**
     * Récupère toutes les informations d'un utilisateur pour la modification.
     */
    public static function getById($user_id) {
        $conn = Database::getConnection();
        $sql = "SELECT 
                    u.*,
                    s.filiere, s.niveau, s.date_debut, s.date_fin,
                    e.poste, e.service
                FROM utilisateurs u
                LEFT JOIN stagiaire s ON u.id = s.id_utilisateur
                LEFT JOIN encadreur e ON u.id = e.id_utilisateur
                WHERE u.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Met à jour les informations d'un utilisateur.
     */
    public static function modifier($data) {
        $conn = Database::getConnection();
        $conn->begin_transaction();
        try {
            // Mise à jour de la table 'utilisateurs'
            $sql_user = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, sex = ?, telephone = ?";
            $params = [$data['nom'], $data['prenom'], $data['email'], $data['sex'], $data['telephone']];
            $types = "sssss";

            // Si un nouveau mot de passe est fourni, on le hache et on le met à jour
            if (!empty($data['password'])) {
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $sql_user .= ", password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }
            
            $sql_user .= " WHERE id = ?";
            $params[] = $data['user_id'];
            $types .= "i";

            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param($types, ...$params);
            $stmt_user->execute();
            
            // Mise à jour de la table de rôle spécifique
            if ($data['role'] === 'stagiaire') {
                $sql_role = "UPDATE stagiaire SET filiere = ?, niveau = ?, date_debut = ?, date_fin = ? WHERE id_utilisateur = ?";
                $stmt_role = $conn->prepare($sql_role);
                $stmt_role->bind_param("ssssi", $data['filiere'], $data['niveau'], $data['date_debut'], $data['date_fin'], $data['user_id']);
                $stmt_role->execute();
            } elseif ($data['role'] === 'encadreur') {
                $sql_role = "UPDATE encadreur SET poste = ?, service = ? WHERE id_utilisateur = ?";
                $stmt_role = $conn->prepare($sql_role);
                $stmt_role->bind_param("ssi", $data['poste'], $data['service'], $data['user_id']);
                $stmt_role->execute();
            }
            
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Erreur modification utilisateur: ' . $e->getMessage());
            // Retourner un message plus spécifique si c'est une erreur de duplicata d'email
            if ($e->getCode() === 1062) { // Code d'erreur MySQL pour duplicata (peut varier)
                return ['success' => false, 'message' => 'L\'adresse email existe déjà.'];
            }
            return ['success' => false, 'message' => 'Une erreur est survenue lors de la modification de l\'utilisateur.'];
        }
    }

    /**
     * Supprime un utilisateur.
     */
    public static function supprimer($user_id) {
        $conn = Database::getConnection();
        // La suppression en cascade (ON DELETE CASCADE) dans la BDD s'occupera des tables stagiaire/encadreur.
        $sql = "DELETE FROM utilisateurs WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }
}