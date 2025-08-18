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
        } else {
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
}
?>