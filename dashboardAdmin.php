<?php
/**
 * Dashboard principal pour les Administrateurs
 */
session_start();
require_once 'classes/Database.php';
require_once 'classes/Message.php'; // Pour les stats de messages
require_once 'classes/Rapport.php';
require_once 'classes/Tache.php';
require_once 'classes/Utilisateur.php';

// 1. CONTRÔLE D'ACCÈS STRICT POUR L'ADMINISTRATEUR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Variables de session
$user_id = $_SESSION['user_id'];
$nom_complet = $_SESSION['prenom'] . ' ' . $_SESSION['nom'];
$role = $_SESSION['role'];

// 2. TRAITEMENT DES ACTIONS AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'creer_utilisateur':
            $resultat = Utilisateur::creer($_POST);
            echo json_encode(['success' => $resultat]);
            exit();
        
        case 'changer_statut':
            $resultat = Utilisateur::changerStatut($_POST['user_id'], $_POST['statut']);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'affecter_encadreur':
            $resultat = Utilisateur::affecterEncadreur($_POST['stagiaire_id'], $_POST['encadreur_id']);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'get_utilisateur_details':
            $details = Utilisateur::getById((int)$_POST['user_id']);
            echo json_encode(['success' => !!$details, 'data' => $details]);
            exit();

        case 'modifier_utilisateur':
            $resultat = Utilisateur::modifier($_POST);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'supprimer_utilisateur':
            $resultat = Utilisateur::supprimer((int)$_POST['user_id']);
            echo json_encode(['success' => $resultat]);
            exit();
    }
}

// 3. LOGIQUE D'AFFICHAGE DE LA PAGE
$message = new Message($user_id);
$conn = Database::getConnection();

// Statistiques globales
$stats = [
    'nb_stagiaires' => $conn->query("SELECT COUNT(*) FROM stagiaire")->fetch_row()[0] ?? 0,
    'nb_encadreurs' => $conn->query("SELECT COUNT(*) FROM encadreur")->fetch_row()[0] ?? 0,
    'nb_rapports_attente' => $conn->query("SELECT COUNT(*) FROM rapports WHERE statut = 'en_attente'")->fetch_row()[0] ?? 0,
];
$nb_messages_non_lus = $message->compterNonLus();

// Récupération des données pour l'onglet actif
$onglet_actif = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$recherche = isset($_GET['search']) ? $_GET['search'] : '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrateur</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboardEncadreur.css"> <!-- On réutilise le style de base -->
    <link rel="stylesheet" href="css/gestion_utilisateurs.css"> <!-- CSS dédié -->
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo"><i class="fas fa-user-shield"></i></div>
            <h3>Administrateur</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li class="<?php echo $onglet_actif === 'dashboard' ? 'active' : ''; ?>"><a href="?tab=dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="<?php echo $onglet_actif === 'gestion-utilisateurs' ? 'active' : ''; ?>"><a href="?tab=gestion-utilisateurs"><i class="fas fa-users-cog"></i><span>Utilisateurs</span></a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Déconnexion</span></a>
        </div>
    </nav>

    <main class="main-content">
        <header class="main-header">
             <div class="header-left">
                <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1><?php echo ucfirst(str_replace('-', ' ', $onglet_actif)); ?></h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span>Bienvenue, <?php echo htmlspecialchars($nom_complet); ?></span>
                    <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                </div>
            </div>
        </header>

        <div class="content-area">
            <?php if ($onglet_actif === 'dashboard'): ?>
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $stats['nb_stagiaires']; ?></h3><p>Stagiaires au total</p></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-tie"></i></div><div class="stat-info"><h3><?php echo $stats['nb_encadreurs']; ?></h3><p>Encadreurs enregistrés</p></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?php echo $stats['nb_rapports_attente']; ?></h3><p>Rapports en attente</p></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-envelope"></i></div><div class="stat-info"><h3><?php echo $nb_messages_non_lus; ?></h3><p>Messages non lus</p></div></div>
                </div>
            
            <?php elseif ($onglet_actif === 'gestion-utilisateurs'): 
                $liste_utilisateurs = Utilisateur::listerTous($recherche);
                $liste_encadreurs = Utilisateur::listerEncadreurs();
            ?>
                <div class="gestion-utilisateurs-content">
                    <div class="toolbar">
                       <button class="btn btn-primary" onclick="ouvrirModalCreerUtilisateur()">
                            <i class="fas fa-user-plus"></i> Ajouter un utilisateur
                       </button>
                        <form method="GET" class="search-form">
                            <input type="hidden" name="tab" value="gestion-utilisateurs">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Rechercher par nom, email..." value="<?php echo htmlspecialchars($recherche); ?>">
                            </div>
                        </form>
                    </div>

                    <div class="users-list">
                        <table>
                            <thead><tr><th>Nom Complet</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Encadreur Assigné</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php while($user_item = $liste_utilisateurs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user_item['prenom'] . ' ' . $user_item['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                    <td><span class="role-badge role-<?php echo $user_item['role']; ?>"><?php echo ucfirst($user_item['role']); ?></span></td>
                                    <td><span class="status-badge status-<?php echo $user_item['statut']; ?>"><?php echo ucfirst($user_item['statut']); ?></span></td>
                                    <td>
                                        <?php if($user_item['role'] === 'stagiaire') {
                                            echo $user_item['enc_nom'] ? htmlspecialchars($user_item['enc_prenom'] . ' ' . $user_item['enc_nom']) : '<i>Non assigné</i>';
                                        } else { echo 'N/A'; } ?>
                                    </td>
                                    <td class="actions-cell">
                                        <?php if($user_item['role'] === 'stagiaire'): ?>
                                            <button class="btn btn-sm btn-info" title="Affecter un encadreur" onclick="ouvrirModalAffecter(<?php echo $user_item['id']; ?>, '<?php echo $user_item['encadreur_id']; ?>')"><i class="fas fa-user-tie"></i></button>
                                        <?php endif; ?>
                                        
                                        <!-- NOUVEAU BOUTON MODIFIER -->
                                        <button class="btn btn-sm btn-secondary" title="Modifier" onclick="ouvrirModalModifierUtilisateur(<?php echo $user_item['id']; ?>)"><i class="fas fa-edit"></i></button>
                                        
                                        <?php if($user_item['statut'] === 'actif'): ?>
                                            <button class="btn btn-sm btn-warning" title="Bloquer" onclick="changerStatut(<?php echo $user_item['id']; ?>, 'bloque')"><i class="fas fa-lock"></i></button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-success" title="Débloquer" onclick="changerStatut(<?php echo $user_item['id']; ?>, 'actif')"><i class="fas fa-unlock"></i></button>
                                        <?php endif; ?>
                                        
                                        <!-- NOUVEAU BOUTON SUPPRIMER -->
                                        <button class="btn btn-sm btn-danger" title="Supprimer" onclick="supprimerUtilisateur(<?php echo $user_item['id']; ?>)"><i class="fas fa-trash"></i></button>
                                    </td>
                                    
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- MODALS -->
        <!-- ======================================================= -->
    <!-- ============ MODALE UTILISATEUR (CRÉER/MODIFIER) ======== -->
    <!-- ======================================================= -->
    <div id="modalUtilisateur" class="modal">
        <div class="modal-content large">
            <form id="formUtilisateur">
                <div class="modal-header">
                    <h3 id="modalUtilisateurTitle">Créer un nouvel utilisateur</h3>
                    <button type="button" class="modal-close" onclick="fermerModal('modalUtilisateur')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="user_action" name="action">
                    <input type="hidden" id="user_id" name="user_id">

                    <h4>Informations Communes</h4>
                    <div class="form-grid">
                        <div class="form-group"><label for="prenom">Prénom *</label><input type="text" name="prenom" required></div>
                        <div class="form-group"><label for="nom">Nom *</label><input type="text" name="nom" required></div>
                        <div class="form-group"><label for="email">Email *</label><input type="email" name="email" required></div>
                        <div class="form-group"><label for="telephone">Téléphone</label><input type="tel" name="telephone"></div>
                        <div class="form-group"><label for="sex">Sexe *</label><select name="sex" required><option value="M">Masculin</option><option value="F">Féminin</option></select></div>
                        <div class="form-group"><label for="password">Mot de passe *</label><input type="password" name="password" required></div>
                    </div>

                    <hr>

                    <h4>Rôle et Informations Spécifiques</h4>
                    <div class="form-group">
                        <label for="role">Rôle de l'utilisateur *</label>
                        <select name="role" id="roleSelect" onchange="toggleRoleFields()" required>
                            <option value="">-- Sélectionner un rôle --</option>
                            <option value="stagiaire">Stagiaire</option>
                            <option value="encadreur">Encadreur</option>
                        </select>
                    </div>

                    <!-- Champs spécifiques au Stagiaire -->
                    <div id="stagiaireFields" class="role-fields" style="display: none;">
                        <h5>Détails du Stagiaire</h5>
                        <div class="form-grid">
                            <div class="form-group"><label>Filière *</label><input type="text" name="filiere"></div>
                            <div class="form-group"><label>Niveau d'études *</label><input type="text" name="niveau"></div>
                            <div class="form-group"><label>Date de début *</label><input type="date" name="date_debut"></div>
                            <div class="form-group"><label>Date de fin *</label><input type="date" name="date_fin"></div>
                        </div>
                    </div>

                    <!-- Champs spécifiques à l'Encadreur -->
                    <div id="encadreurFields" class="role-fields" style="display: none;">
                        <h5>Détails de l'Encadreur</h5>
                        <div class="form-grid">
                            <div class="form-group"><label>Poste *</label><input type="text" name="poste"></div>
                            <div class="form-group"><label>Service / Département *</label><input type="text" name="service"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fermerModal('modalUtilisateur')">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="modalUtilisateurSubmitBtn">Créer l'utilisateur</button>
                </div>
            </form>
        </div>
    </div>
    <div id="modalAffecterEncadreur" class="modal">
        <div class="modal-content">
            <form id="formAffecterEncadreur">
                <div class="modal-header"><h3>Affecter un Encadreur</h3><button type="button" class="modal-close" onclick="fermerModal('modalAffecterEncadreur')">&times;</button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="affecter_encadreur">
                    <input type="hidden" id="affecter_stagiaire_id" name="stagiaire_id">
                    <div class="form-group">
                        <label for="affecter_encadreur_id">Choisir un encadreur</label>
                        <select id="affecter_encadreur_id" name="encadreur_id" class="filter-select">
                            <option value="">-- Aucun --</option>
                            <?php 
                                $liste_encadreurs->data_seek(0);
                                while($enc = $liste_encadreurs->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $enc['id']; ?>"><?php echo htmlspecialchars($enc['prenom'] . ' ' . $enc['nom']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="fermerModal('modalAffecterEncadreur')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
    
    <div id="notifications"></div>
    <script src="js/dashboardAdmin.js"></script>
</body>
</html>