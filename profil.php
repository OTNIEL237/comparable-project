<?php
/**
 * Page de profil utilisateur
 */
session_start();
require_once 'classes/Database.php';
require_once 'classes/Utilisateur.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$db = Database::getConnection();

// Récupérer les informations utilisateur
$utilisateur = new Utilisateur($user_id);
$user_data = $utilisateur->getProfileData();

// Traitement du formulaire de mise à jour
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Mise à jour des informations de base
        $utilisateur->updateProfile($nom, $prenom, $email, $telephone);

        // Mise à jour du mot de passe si fourni
        if (!empty($current_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception("Les nouveaux mots de passe ne correspondent pas");
            }
            $utilisateur->changePassword($current_password, $new_password);
        }

        // Mettre à jour les données de session
        $_SESSION['nom'] = $nom;
        $_SESSION['prenom'] = $prenom;
        $_SESSION['email'] = $email;

        $success_message = "Profil mis à jour avec succès!";
        
        // Recharger les données utilisateur
        $user_data = $utilisateur->getProfileData();
    } catch (Exception $e) {
        $error_message = "Erreur: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Utilisateur</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0066cc;
            --secondary-color: #f8f9fa;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --text-color: #333;
            --border-color: #dee2e6;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--text-color);
        }
        
        .profile-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .profile-header {
            background: var(--primary-color);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: var(--primary-color);
        }
        
        .profile-name {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .profile-role {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .profile-body {
            padding: 30px;
        }
        
        .section-title {
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .message.success {
            background-color: #d4edda;
            color: var(--success-color);
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: var(--danger-color);
            border: 1px solid #f5c6cb;
        }
        
        .info-card {
            background: var(--secondary-color);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-card h3 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .info-item {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 150px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .profile-container {
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h1 class="profile-name"><?php echo htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']); ?></h1>
            <div class="profile-role"><?php echo ucfirst($role); ?></div>
        </div>
        
        <div class="profile-body">
            <?php if ($success_message): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <h2 class="section-title">Informations Personnelles</h2>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($user_data['nom']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" 
                               value="<?php echo htmlspecialchars($user_data['prenom']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" 
                               value="<?php echo htmlspecialchars($user_data['telephone']); ?>">
                    </div>
                </div>
                
                <?php if ($role === 'stagiaire'): ?>
                    <div class="info-card">
                        <h3>Informations de Stage</h3>
                        <div class="info-item">
                            <span class="info-label">Filière:</span>
                            <span><?php echo htmlspecialchars($user_data['filiere'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Niveau:</span>
                            <span><?php echo htmlspecialchars($user_data['niveau'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Période:</span>
                            <span>
                                <?php echo htmlspecialchars(
                                    ($user_data['date_debut'] ? date('d/m/Y', strtotime($user_data['date_debut'])) : '?') . 
                                    ' - ' . 
                                    ($user_data['date_fin'] ? date('d/m/Y', strtotime($user_data['date_fin'])) : '?')
                                ); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Encadreur:</span>
                            <span>
                                <?php echo htmlspecialchars(
                                    ($user_data['encadreur_prenom'] ?? '') . ' ' . 
                                    ($user_data['encadreur_nom'] ?? 'Non attribué')
                                ); ?>
                            </span>
                        </div>
                    </div>
                <?php elseif ($role === 'encadreur'): ?>
                    <div class="info-card">
                        <h3>Informations Professionnelles</h3>
                        <div class="info-item">
                            <span class="info-label">Poste:</span>
                            <span><?php echo htmlspecialchars($user_data['poste'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Service:</span>
                            <span><?php echo htmlspecialchars($user_data['service'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Stagiaires encadrés:</span>
                            <span><?php echo $user_data['nb_stagiaires'] ?? 0; ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <h2 class="section-title">Changer le mot de passe</h2>
                
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input type="password" id="current_password" name="current_password">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-block">Mettre à jour le profil</button>
            </form>
        </div>
    </div>
</body>
</html>