<?php
session_start();
require_once 'classes/Database.php';

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard' . ucfirst($_SESSION['role']) . '.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $error_message = 'Tous les champs sont obligatoires.';
    } else {
        try {
            $conn = Database::getConnection();
            
            // Requête simplifiée sans jointure
            $sql = "SELECT * FROM utilisateurs WHERE email = ? AND role = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $email, $role);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur d'exécution: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Vérification mot de passe en texte clair
                if ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirection
                    header('Location: dashboard' . ucfirst($user['role']) . '.php');
                    exit();
                } else {
                    $error_message = 'Identifiants incorrects';
                }
            } else {
                $error_message = 'Identifiants incorrects';
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error_message = 'Erreur système. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion des Stagiaires</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="comparable.png" alt="">
                </div>
                <h1>Gestion des Stagiaires</h1>
                <p>Connectez-vous à votre espace</p>
            </div>
            <form method="POST" class="login-form">
                <?php if ($error_message): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Adresse email
                    </label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Mot de passe
                    </label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">
                        <i class="fas fa-user-tag"></i>
                        Type de compte
                    </label>
                    <select id="role" name="role" required>
                        <option value="">Sélectionnez votre rôle</option>
                        <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Administrateur</option>
                        <option value="encadreur" <?= (isset($_POST['role']) && $_POST['role'] === 'encadreur') ? 'selected' : '' ?>>Encadreur</option>
                        <option value="stagiaire" <?= (isset($_POST['role']) && $_POST['role'] === 'stagiaire') ? 'selected' : '' ?>>Stagiaire</option>
                    </select>
                </div>
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>
            <div class="login-footer">
                <p>&copy; 2024 Système de Gestion des Stagiaires</p>
            </div>
        </div>
    </div>
</body>
</html>