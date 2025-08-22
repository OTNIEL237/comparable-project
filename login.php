<?php
session_start();
require_once 'classes/Database.php';

// Si l'utilisateur est déjà connecté, on le redirige.
if (isset($_SESSION['user_id'])) {
    $dashboardFile = 'dashboard' . ucfirst($_SESSION['role']) . '.php';
    // Sécurité : vérifier que le fichier dashboard existe avant de rediriger
    if (file_exists($dashboardFile)) {
        header('Location: ' . $dashboardFile);
    } else {
        // Fallback au cas où le fichier n'existe pas
        header('Location: index.php');
    }
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
            $sql = "SELECT id, nom, prenom, email, password, role, statut FROM utilisateurs WHERE email = ? AND role = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if ($user['statut'] === 'bloque') {
                    $error_message = 'Votre compte a été bloqué. Veuillez contacter un administrateur.';
                }
                // NOTE : La vérification du mot de passe en clair est conservée comme demandé.
                elseif ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    $dashboardFile = 'dashboard' . ucfirst($user['role']) . '.php';
                    header('Location: ' . (file_exists($dashboardFile) ? $dashboardFile : 'index.php'));
                    exit();
                } else {
                    $error_message = 'L\'adresse email, le mot de passe ou le rôle est incorrect.';
                }
            } else {
                $error_message = 'L\'adresse email, le mot de passe ou le rôle est incorrect.';
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="login-container">

        <!-- Colonne de Gauche : Branding -->
        <div class="login-branding">
            <div class="logo">
                <img src="comparable.png" alt="Logo">
            </div>
            <h1>Gestion des Stagiaires</h1>
            <p>Votre plateforme centralisée pour un suivi efficace et une collaboration simplifiée.</p>
        </div>

        <!-- Colonne de Droite : Formulaire -->
        <div class="login-form-wrapper">
            <h2>Bienvenue !</h2>
            <p class="subtitle">Connectez-vous pour accéder à votre espace.</p>
            
            <form method="POST" action="login.php">
                <?php if ($error_message): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="email" id="email" name="email" required placeholder="Adresse email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <i class="icon fas fa-envelope"></i>
                </div>

                <div class="form-group">
                    <input type="password" id="password" name="password" required placeholder="Mot de passe">
                    <i class="icon fas fa-lock"></i>
                </div>

                <div class="form-group">
                    <select id="role" name="role" required>
                        <option value="" disabled selected>Sélectionnez votre rôle</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                        <option value="encadreur" <?php echo (isset($_POST['role']) && $_POST['role'] === 'encadreur') ? 'selected' : ''; ?>>Encadreur</option>
                        <option value="stagiaire" <?php echo (isset($_POST['role']) && $_POST['role'] === 'stagiaire') ? 'selected' : ''; ?>>Stagiaire</option>
                    </select>
                    <i class="icon fas fa-user-tag"></i>
                </div>

                <button type="submit" class="login-btn">Se connecter</button>

                <div class="form-footer">
                    <a href="index.php" class="btn-retour-index">
                        <i class="fas fa-arrow-left"></i> Retour à l'accueil
                    </a>
                </div>
            </form>
        </div>

    </div>

</body>
    <script>
        // Simulation d'erreur pour la démonstration
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const errorDiv = document.querySelector('.error-message');
            
            // Afficher un message d'erreur au submit pour la démo
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Simulation d'erreur
                errorDiv.style.display = 'flex';
                errorDiv.querySelector('span').textContent = 'Adresse email ou mot de passe incorrect';
                
                // Cacher après 5 secondes
                setTimeout(() => {
                    errorDiv.style.display = 'none';
                }, 5000);
            });
            
            // Animation des éléments au chargement
            setTimeout(() => {
                document.querySelectorAll('.form-group').forEach(el => {
                    el.style.opacity = "1";
                    el.style.transform = "translateY(0)";
                });
            }, 300);
        });
    </script>
</html>

