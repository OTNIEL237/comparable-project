<?php
session_start();
require_once 'classes/Database.php';

// Si l'utilisateur est déjà connecté, on le redirige vers son tableau de bord.
if (isset($_SESSION['user_id'])) {
    // ucfirst() met la première lettre du rôle en majuscule (Stagiaire, Encadreur, Admin)
    header('Location: dashboard' . ucfirst($_SESSION['role']) . '.php');
    exit();
}

$error_message = '';

// Le formulaire a-t-il été soumis ?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // 1. Validation simple des champs
    if (empty($email) || empty($password) || empty($role)) {
        $error_message = 'Tous les champs sont obligatoires.';
    } else {
        try {
            $conn = Database::getConnection();
            
            // 2. Recherche de l'utilisateur par email ET par rôle
            $sql = "SELECT id, nom, prenom, email, password, role, statut FROM utilisateurs WHERE email = ? AND role = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // 3. VÉRIFICATION DU STATUT (ACTIF / BLOQUE)
                if (isset($user['statut']) && $user['statut'] === 'bloque') {
                    // Message d'erreur spécifique si le compte est bloqué
                    $error_message = 'Votre compte a été bloqué. Veuillez contacter un administrateur.';
                }
                // 4. VÉRIFICATION DU MOT DE PASSE (si le compte n'est pas bloqué)
                elseif ($password === $user['password']) {
                    // Le mot de passe est correct, la connexion réussit
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirection dynamique vers le bon dashboard
                    header('Location: dashboard' . ucfirst($user['role']) . '.php');
                    exit();
                } else {
                    // Le mot de passe est incorrect
                    $error_message = 'L\'adresse email ou le mot de passe est incorrect.';
                }
            } else {
                // Aucun utilisateur trouvé avec cet email et ce rôle
                $error_message = 'L\'adresse email ou le mot de passe est incorrect.';
            }
        } catch (Exception $e) {
            // En cas d'erreur de base de données, on affiche un message générique
            error_log($e->getMessage()); // Pour le débogage
            $error_message = 'Une erreur système est survenue. Veuillez réessayer plus tard.';
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
    <!-- Ajout de Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="comparable.png" alt="Logo">
                </div>
                <h1>Gestion des Stagiaires</h1>
                <p>Connectez-vous à votre espace</p>
            </div>
            <form method="POST" class="login-form" action="login.php">
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
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                        <option value="encadreur" <?php echo (isset($_POST['role']) && $_POST['role'] === 'encadreur') ? 'selected' : ''; ?>>Encadreur</option>
                        <option value="stagiaire" <?php echo (isset($_POST['role']) && $_POST['role'] === 'stagiaire') ? 'selected' : ''; ?>>Stagiaire</option>
                    </select>
                </div>
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>
            <div class="login-footer">
                <a href="index.php" class="retour">retour a la page d'accueil</a>
            
                <p>&copy; <?php echo date('Y'); ?> Système de Gestion des Stagiaires</p>
            </div>
        </div>
    </div>
</body>
</html>