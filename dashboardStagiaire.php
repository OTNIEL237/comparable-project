<?php
/**
 * Dashboard principal pour les stagiaires
 */
date_default_timezone_set('Africa/Douala');

session_start();

require_once 'classes/Database.php';
require_once 'classes/Message.php';
require_once 'classes/Rapport.php';
require_once 'classes/Tache.php';
require_once 'classes/Theme.php';
require_once 'classes/Evaluation.php';
require_once 'classes/Presence.php';

// Vérification de la session - Seuls les stagiaires peuvent accéder
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'stagiaire') {
    header('Location: login.php');
    exit();
}
$conn = Database::getConnection();
// Variables de session
$user_id = $_SESSION['user_id'];
$nom_complet = $_SESSION['prenom'] . ' ' . $_SESSION['nom'];

// Initialisation des classes 
$message = new Message($user_id);
$rapport = new Rapport($user_id);
$tache = new Tache();
$theme = new Theme();
$theme_stagiaire = $theme->getThemeByStagiaire($user_id);


// Statistiques pour le dashboard principal
$nb_messages_non_lus = $message->compterNonLus();
$rapports_recents = $rapport->getTousRapports('all', '');
$nb_rapports = $rapports_recents->num_rows;
// Statistiques
$nb_taches_en_cours_sql = "SELECT COUNT(*) as count FROM taches WHERE stagiaire_id = ? AND statut IN ('en_attente', 'en_retard')";
$stmt = $conn->prepare($nb_taches_en_cours_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$nb_taches_en_cours = $stmt->get_result()->fetch_assoc()['count'];


// Traitement des actions AJAX pour messagerie et rapports
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
   
    switch ($_POST['action']) {
        // Action : Envoyer un message avec pièce jointe
        case 'envoyer_message':
            $destinataire_id = $_POST['destinataire_id'];
            $sujet = $_POST['sujet'];
            $contenu = $_POST['contenu'];
            $fichier = isset($_FILES['fichier']) ? $_FILES['fichier'] : null;
           
            $resultat = $message->envoyer($destinataire_id, $sujet, $contenu, $fichier);
            echo json_encode(['success' => $resultat]);
            exit();
           
        // Action : Créer un nouveau rapport avec génération PDF
        case 'creer_rapport':
            $type = $_POST['type'];
            $titre = $_POST['titre'];
            $activites = $_POST['activites'];
            $difficultes = $_POST['difficultes'];
            $solutions = $_POST['solutions'];
           
            $resultat = $rapport->creer($type, $titre, $activites, $difficultes, $solutions);
            echo json_encode($resultat);
            exit();

            if ($resultat['success']) {
                $_SESSION['success'] = "Rapport envoyé à votre encadreur avec succès !";
            } else {
                $_SESSION['error'] = $resultat['message'];
            }
           
        // Action : Marquer un message comme lu
        case 'marquer_lu':
            $message_id = $_POST['message_id'];
            $resultat = $message->marquerCommeLu($message_id);
            echo json_encode(['success' => $resultat]);
            exit();

          case 'terminer_tache':
            $tache_id = $_POST['tache_id'];
            $resultat = $tache->marquerTerminee($tache_id, $user_id);
            echo json_encode(['success' => $resultat]);
            exit();

          case 'get_tache_details':
            if (isset($_POST['tache_id'])) {
                $tache_id = (int)$_POST['tache_id'];
                // La classe Tache est déjà instanciée au début du fichier
                $tache_details = $tache->getTacheById($tache_id);
                
                // Sécurité : Vérifier que la tâche appartient bien au stagiaire connecté
                if ($tache_details && $tache_details['stagiaire_id'] == $user_id) {
                    echo json_encode(['success' => true, 'data' => $tache_details]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Tâche non trouvée ou accès refusé.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de tâche manquant.']);
            }
            exit();


        case 'marquer_presence':
            $presence = new Presence();
            $resultat = $presence->marquerPresence(
                $user_id, 
                $_POST['presence_action'], 
                $_POST['localisation'] ?? null
            );
            echo json_encode(['success' => $resultat]);
            exit();

        case 'get_presence_events':
            $annee = intval($_POST['year']);
            $mois = intval($_POST['month']);
            $events = $presence->getPresencePourMois($user_id, $annee, $mois);
            echo json_encode($events);
            exit();

        case 'get_rapport_details':
            if (isset($_POST['rapport_id'])) {
                $rapport_id = (int)$_POST['rapport_id'];
                // Le rôle est 'stagiaire' sur cette page
                $rapport_details = Rapport::getRapportById($rapport_id, $user_id, 'stagiaire');
                
                if ($rapport_details) {
                    echo json_encode(['success' => true, 'data' => $rapport_details]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Rapport non trouvé ou accès refusé.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de rapport manquant.']);
            }
            exit();

        }
        
}


// Gestion des onglets et filtres
$onglet_actif = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$filtre = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$recherche = isset($_GET['search']) ? $_GET['search'] : '';
$filtre = isset($_GET['filter']) ? $_GET['filter'] : 'toutes';

// Chargement des données selon l'onglet sélectionné
switch ($onglet_actif) {
    case 'messagerie':
        // Récupération des messages avec filtres et recherche
        $messages = $message->getMessages($filtre, $recherche);
        $utilisateurs = $message->getUtilisateursDisponibles();
        break;
       
    case 'rapports':
        // Récupération des rapports avec filtres et recherche
        $rapports = $rapport->getTousRapports($filtre, $recherche);
        break;

    case 'taches':
        // On passe maintenant le terme de recherche à la méthode
        $taches = $tache->getTachesPourStagiaire($user_id, $filtre, $recherche);
        break;

    case 'presences':
        // NOUVEAU : Récupérer le statut de pointage pour désactiver les boutons
        $presence = new Presence();
        $statut_pointage = $presence->getStatutPointageAujourdhui($user_id);
        break;

    case 'profil':
        // Récupérer les informations détaillées du stagiaire
        $profil_sql = "SELECT u.*, s.filiere, s.niveau, s.date_debut, s.date_fin, 
                            enc.prenom AS encadreur_prenom, enc.nom AS encadreur_nom
                    FROM utilisateurs u 
                    JOIN stagiaire s ON u.id = s.id_utilisateur 
                    LEFT JOIN utilisateurs enc ON s.encadreur_id = enc.id 
                    WHERE u.id = ?";
        $profil_stmt = $conn->prepare($profil_sql);
        $profil_stmt->bind_param("i", $user_id);
        $profil_stmt->execute();
        $profil = $profil_stmt->get_result()->fetch_assoc();
    
    // Calculer les jours restants
    if ($profil['date_debut'] && $profil['date_fin']) {
        $now = new DateTime();
        $end = new DateTime($profil['date_fin']);
        $interval = $now->diff($end);
        $jours_restants = $interval->format('%a');
        
        // Calcul du pourcentage de progression
        $debut = new DateTime($profil['date_debut']);
        $total = $end->diff($debut)->format('%a');
        $ecoule = $now->diff($debut)->format('%a');
        $pourcentage = min(100, max(0, round(($ecoule / $total) * 100)));
    } else {
        $jours_restants = "Non défini";
        $pourcentage = 0;
    }
    break;

    case 'evaluation': // AJOUTER CE CASE
        $evaluation = new Evaluation();
        $evaluation_data = $evaluation->getEvaluationForStagiaire($user_id);
        $stagiaire_info = ['prenom' => $_SESSION['prenom'], 'nom' => $_SESSION['nom']];
        break;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Stagiaire - <?php echo htmlspecialchars($nom_complet); ?></title>
    <link rel="stylesheet" href="css/dashboardStagiaire.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/taches.css">
    <link rel="stylesheet" href="css/themes.css">
    <link rel="stylesheet" href="css/evaluation.css">
    <link rel="stylesheet" href="css/presence.css">
</head>
<body>
    <!-- Navigation latérale - Menu principal -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h3>Stagiaire</h3>
        </div>
       
        <!-- Menu de navigation avec badges de notification -->
        <ul class="sidebar-menu">
            <li class="<?php echo $onglet_actif === 'dashboard' ? 'active' : ''; ?>">
                <a href="?tab=dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            </li>
            <li class="<?php echo $onglet_actif === 'messagerie' ? 'active' : ''; ?>">
                <a href="?tab=messagerie">
                    <i class="fas fa-envelope"></i><span>Messagerie</span>
                    <?php if ($nb_messages_non_lus > 0): ?><span class="badge"><?php echo $nb_messages_non_lus; ?></span><?php endif; ?>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'rapports' ? 'active' : ''; ?>">
                <a href="?tab=rapports"><i class="fas fa-file-alt"></i><span>Rapports</span></a>
            </li>
            <li class="<?php echo $onglet_actif === 'taches' ? 'active' : ''; ?>">
                <a href="?tab=taches"><i class="fas fa-tasks"></i><span>Tâches</span></a>
            </li>
            <li class="<?php echo $onglet_actif === 'presences' ? 'active' : ''; ?>">
                <a href="?tab=presences"><i class="fas fa-calendar-check"></i><span>Ma Présence</span></a>
            </li>
            <li class="<?php echo $onglet_actif === 'evaluation' ? 'active' : ''; ?>">
                <a href="?tab=evaluation"><i class="fas fa-chart-line"></i><span>Évaluation</span></a>
            </li>
        </ul>

        <!-- Bouton de déconnexion -->
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="main-content">
        <!-- En-tête avec informations utilisateur -->
        <header class="main-header">
            <div class="header-left">
                <button class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1><?php echo ucfirst($onglet_actif); ?></h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span>Bienvenue, <?php echo htmlspecialchars($nom_complet); ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
        </header>

        <!-- Zone de contenu dynamique -->
        <div class="content-area">
            <?php if ($onglet_actif === 'dashboard'): ?>
                <!-- DASHBOARD PRINCIPAL - Statistiques et actions rapides -->
                <div class="dashboard-content">
          

                    
                    <?php if ($theme_stagiaire): ?>
                    <div class="theme-stagiaire-container">
                        <div class="theme-stagiaire-card" onclick="ouvrirModalVoirTheme()">
                            <div class="theme-stagiaire-header">
                                Mon Thème de Stage
                            </div>
                            <h2 class="theme-stagiaire-titre">
                                <?php echo htmlspecialchars($theme_stagiaire['titre']); ?>
                            </h2>
                            <div class="theme-stagiaire-footer">
                                <span class="theme-stagiaire-dates">
                                    <i class="fas fa-calendar-alt"></i> Du <?php echo date('d/m/Y', strtotime($theme_stagiaire['date_debut'])); ?> au <?php echo date('d/m/Y', strtotime($theme_stagiaire['date_fin'])); ?>
                                </span>
                                <span class="theme-stagiaire-action">
                                    Voir les détails <i class="fas fa-arrow-right"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $nb_messages_non_lus; ?></h3>
                                <p>Messages non lus</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $nb_rapports; ?></h3>
                                <p>Rapports soumis</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $nb_taches_en_cours; ?></h3>
                                <p>Tâches en cours</p>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>


            <?php elseif ($onglet_actif === 'messagerie'): ?>
                <!-- MESSAGERIE COMPLÈTE - Envoi, réception, pièces jointes -->
                <div class="messagerie-content">
                    <!-- Barre d'outils avec filtres et recherche -->
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <button class="btn btn-primary" onclick="ouvrirNouveauMessage()">
                                <i class="fas fa-plus"></i>
                                Nouveau message
                            </button>
                        </div>
                        <div class="toolbar-right">
                            <!-- Filtres pour les messages -->
                            <select class="filter-select" onchange="filtrerMessages(this.value)">
                                <option value="all" <?php echo $filtre === 'all' ? 'selected' : ''; ?>>Tous les messages</option>
                                <option value="unread" <?php echo $filtre === 'unread' ? 'selected' : ''; ?>>Non lus</option>
                                <option value="received" <?php echo $filtre === 'received' ? 'selected' : ''; ?>>Reçus</option>
                                <option value="sent" <?php echo $filtre === 'sent' ? 'selected' : ''; ?>>Envoyés</option>
                            </select>
                            <!-- Barre de recherche -->
                            <div class="search-box">
                                <input type="text" placeholder="Rechercher..." value="<?php echo htmlspecialchars($recherche); ?>"
                                       onkeyup="rechercherMessages(this.value)">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des messages avec indicateurs -->
                    <div class="messages-list">
                        <?php if (isset($messages) && $messages->num_rows > 0): ?>
                            <?php while ($msg = $messages->fetch_assoc()): ?>
                                <!-- Item de message avec statut lu/non lu -->
                                <div class="message-item <?php echo $msg['lu'] == 0 && $msg['destinataire_id'] == $user_id ? 'unread' : ''; ?>"
                                     onclick="ouvrirMessage(<?php echo $msg['id']; ?>)">
                                    <div class="message-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="sender">
                                                <?php
                                                // Affichage expéditeur/destinataire selon le contexte
                                                if ($msg['expediteur_id'] == $user_id) {
                                                    echo 'À: ' . htmlspecialchars($msg['dest_prenom'] . ' ' . $msg['dest_nom']);
                                                } else {
                                                    echo 'De: ' . htmlspecialchars($msg['exp_prenom'] . ' ' . $msg['exp_nom']);
                                                }
                                                ?>
                                            </span>
                                            <span class="date"><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></span>
                                        </div>
                                        <div class="message-subject">
                                            <?php echo htmlspecialchars($msg['sujet']); ?>
                                            <!-- Indicateur de pièce jointe -->
                                            <?php if ($msg['nb_pieces_jointes'] > 0): ?>
                                                <i class="fas fa-paperclip"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-preview">
                                            <?php echo htmlspecialchars(substr($msg['contenu'], 0, 100)) . '...'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <!-- État vide -->
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Aucun message trouvé</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($onglet_actif === 'rapports'): ?>
                <!-- RAPPORTS COMPLETS - Création, visualisation, PDF -->
                <div class="rapports-content">
                    <!-- Barre d'outils pour les rapports -->
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <button class="btn btn-primary" onclick="ouvrirNouveauRapport()">
                                <i class="fas fa-plus"></i>
                                Nouveau rapport
                            </button>
                        </div>
                        <div class="toolbar-right">
                            <!-- Filtres par type de rapport -->
                            <select class="filter-select" onchange="filtrerRapports(this.value)">
                                <option value="all" <?php echo $filtre === 'all' ? 'selected' : ''; ?>>Tous les rapports</option>
                                <option value="journalier" <?php echo $filtre === 'journalier' ? 'selected' : ''; ?>>Journaliers</option>
                                <option value="hebdomadaire" <?php echo $filtre === 'hebdomadaire' ? 'selected' : ''; ?>>Hebdomadaires</option>
                                <option value="mensuel" <?php echo $filtre === 'mensuel' ? 'selected' : ''; ?>>Mensuels</option>
                            </select>
                            <!-- Recherche dans les rapports -->
                            <div class="search-box">
                                <input type="text" placeholder="Rechercher..." value="<?php echo htmlspecialchars($recherche); ?>"
                                       onkeyup="rechercherRapports(this.value)">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des rapports avec actions -->
                    <div class="rapports-list">
                        <?php if (isset($rapports) && $rapports->num_rows > 0): ?>
                            <?php while ($rpt = $rapports->fetch_assoc()): ?>
                                <!-- Item de rapport avec type et statut -->
                                <div class="rapport-item">
                                    <div class="rapport-header">
                                        <div class="rapport-type">
                                            <!-- Badge de type de rapport -->
                                            <span class="type-badge type-<?php echo $rpt['type']; ?>">
                                                <?php echo ucfirst($rpt['type']); ?>
                                            </span>
                                        </div>
                                        <div class="rapport-date">
                                            <?php echo date('d/m/Y', strtotime($rpt['date_soumission'])); ?>
                                        </div>
                                    </div>
                                    <div class="rapport-content">
                                        <h3><?php echo htmlspecialchars($rpt['titre']); ?></h3>
                                        <p><?php echo htmlspecialchars(substr($rpt['activites'], 0, 150)) . '...'; ?></p>
                                    </div>
                                    <div class="rapport-actions">
                                        <!-- Actions sur les rapports -->
                                       <button class="btn btn-sm" onclick="voirRapport(<?php echo $rpt['id']; ?>)">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                        <button class="btn btn-sm" onclick="telechargerRapport(<?php echo $rpt['id']; ?>)">
                                            <i class="fas fa-download"></i>
                                            PDF
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <!-- État vide pour les rapports -->
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <p>Aucun rapport trouvé</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($onglet_actif === 'profil'): ?>
    <div class="profil-content">
        <div class="profil-header">
            <div class="profil-avatar">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="profil-titles">
                <h1><?php echo htmlspecialchars($nom_complet); ?></h1>
                <p class="role-badge">Stagiaire</p>
            </div>
        </div>

        <div class="profil-grid">
            <div class="profil-card">
                <div class="card-header">
                    <i class="fas fa-user"></i>
                    <h3>Informations personnelles</h3>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="info-label">Email :</span>
                        <span class="info-value"><?php echo htmlspecialchars($profil['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Téléphone :</span>
                        <span class="info-value"><?php echo htmlspecialchars($profil['telephone'] ?? 'Non renseigné'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Sexe :</span>
                        <span class="info-value"><?php echo $profil['sex'] === 'M' ? 'Masculin' : 'Féminin'; ?></span>
                    </div>
                </div>
            </div>

            <div class="profil-card">
                <div class="card-header">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Informations académiques</h3>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="info-label">Filière :</span>
                        <span class="info-value"><?php echo htmlspecialchars($profil['filiere']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Niveau :</span>
                        <span class="info-value"><?php echo htmlspecialchars($profil['niveau']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Encadreur :</span>
                        <span class="info-value">
                            <?php if($profil['encadreur_prenom']): ?>
                                <?php echo htmlspecialchars($profil['encadreur_prenom'] . ' ' . $profil['encadreur_nom']); ?>
                            <?php else: ?>
                                Non assigné
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="profil-card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Période de stage</h3>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="info-label">Début :</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($profil['date_debut'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fin :</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($profil['date_fin'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Jours restants :</span>
                        <span class="info-value">
                            <?php 
                            $now = new DateTime();
                            $end = new DateTime($profil['date_fin']);
                            $interval = $now->diff($end);
                            echo $interval->format('%a jours');
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="profil-card full-width">
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 65%;"></div>
                    </div>
                    <div class="progress-labels">
                        <span>Début</span>
                        <span>65% complété</span>
                        <span>Fin</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($onglet_actif === 'taches'): ?>
<div class="taches-content">
    <div class="toolbar">
    <div class="toolbar-left">
        <h2>Mes Tâches</h2>
    </div>
    <div class="toolbar-right">
        <select class="filter-select" onchange="filtrerTaches(this.value)">
            <option value="toutes" <?php echo $filtre === 'toutes' ? 'selected' : ''; ?>>Toutes les tâches</option>
            <option value="en_cours" <?php echo $filtre === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
            <option value="en_retard" <?php echo $filtre === 'en_retard' ? 'selected' : ''; ?>>En retard</option>
            <option value="terminees" <?php echo $filtre === 'terminees' ? 'selected' : ''; ?>>Terminées</option>
        </select>
        <!-- NOUVELLE BARRE DE RECHERCHE -->
        <div class="search-box">
            <input type="text" placeholder="Rechercher une tâche..." value="<?php echo htmlspecialchars($recherche); ?>" onkeyup="rechercherTaches(this.value)">
            <i class="fas fa-search"></i>
        </div>
    </div>
</div>
    
        

    <div class="taches-grid">
        <?php if (isset($taches) && $taches->num_rows > 0): ?>
            <?php while ($t = $taches->fetch_assoc()): ?>
    <?php
        // ... (votre code PHP pour calculer le statut et les jours restants est correct)
        $statut_reel = $t['statut'];
        $jours_restants = '';
        if ($statut_reel !== 'terminee') {
            $echeance = new DateTime($t['date_echeance']);
            $aujourdhui = new DateTime();
            $diff = $aujourdhui->diff($echeance);
            if ($aujourdhui > $echeance) {
                $statut_reel = 'en_retard';
                $jours_restants = "Échue depuis " . $diff->days . " jour(s)";
            } else {
                $jours_restants = $diff->days . ' jour(s) restant(s)';
            }
        }
    ?>
    <!-- STRUCTURE HTML CORRIGÉE -->
    <div class="tache-card status-card-<?php echo $statut_reel; ?>" onclick="voirTache(<?php echo $t['id']; ?>)" style="cursor: pointer;">
        <div class="tache-card-header">
            <h3><?php echo htmlspecialchars($t['titre']); ?></h3>
        </div>
        <div class="tache-card-body">
            <div class="tache-info">
                <div class="info-line">
                    <i class="fas fa-calendar-alt"></i>
                    Échéance : <span><?php echo date('d/m/Y', strtotime($t['date_echeance'])); ?></span>
                </div>
                <div class="info-line">
                    <i class="fas fa-hourglass-half"></i>
                    Délai : <span><?php echo $jours_restants; ?></span>
                </div>
                <div class="info-line">
                    <i class="fas fa-align-left"></i>
                    <!-- Utilisation de <p> pour une meilleure sémantique et contrôle du style -->
                    <p><?php echo htmlspecialchars(substr($t['description'], 0, 80)) . '...'; ?></p>
                </div>
                <?php if ($t['nom_fichier_original']): ?>
                    <div class="info-line">
                        <i class="fas fa-paperclip"></i>
                        Fichier joint disponible
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="tache-card-footer">
            <span class="status-badge status-<?php echo $statut_reel; ?>"><?php echo str_replace('_', ' ', $statut_reel); ?></span>
            <div class="tache-actions">
                <?php if ($statut_reel !== 'terminee'): ?>
                    <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); terminerTache(<?php echo $t['id']; ?>)">
                        <i class="fas fa-check-circle"></i> Terminer
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>Vous n'avez aucune tâche pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php elseif ($onglet_actif === 'presences'): ?>
    <?php
        // Récupérer la date de référence pour la navigation (aujourd'hui par défaut)
        $date_ref = isset($_GET['date']) ? $_GET['date'] : 'today';
        $ref_date_obj = new DateTime($date_ref);
        
        // Liens pour la navigation semaine précédente/suivante
        $semaine_prec = (clone $ref_date_obj)->modify('-7 days')->format('Y-m-d');
        $semaine_suiv = (clone $ref_date_obj)->modify('+7 days')->format('Y-m-d');
        
        // Récupérer les données de la semaine
        $presence = new Presence();
        $semaine_data = $presence->getPresencePourSemaine($user_id, $date_ref);
        $statut_pointage = $presence->getStatutPointageAujourdhui($user_id);
    ?>
    <div class="presence-content-liste">
        
        <!-- Boîte de pointage du jour -->
        <div class="pointage-box">
            <div class="pointage-header">
                <h3>Pointage du Jour</h3>
                <p id="current-date"><?php echo date('d/m/Y'); ?></p>
            </div>
            <div class="pointage-actions">
                <button class="btn" <?php echo $statut_pointage['heure_arrivee'] ? 'disabled' : ''; ?> onclick="marquerAction('arrivee')">
                    <i class="fas fa-sign-in-alt"></i> Arrivée
                </button>
                <button class="btn btn-secondary" <?php echo !$statut_pointage['heure_arrivee'] || $statut_pointage['heure_fin_pause'] || $statut_pointage['heure_depart'] ? 'disabled' : ''; ?> onclick="marquerAction('fin_pause')">
                    <i class="fas fa-utensils"></i> Fin de Pause
                </button>
                <button class="btn btn-danger" <?php echo !$statut_pointage['heure_arrivee'] || $statut_pointage['heure_depart'] ? 'disabled' : ''; ?> onclick="marquerAction('depart')">
                    <i class="fas fa-sign-out-alt"></i> Départ
                </button>
            </div>
            <div id="geo-info" class="geo-info">
                <i class="fas fa-map-marker-alt"></i>
                <span>Votre localisation sera demandée au pointage.</span>
            </div>
        </div>

        <!-- Vue de la semaine -->
        <div class="semaine-view">
            <div class="semaine-header">
                <a href="?tab=presences&date=<?php echo $semaine_prec; ?>" class="btn btn-secondary"><i class="fas fa-chevron-left"></i></a>
                <h2>Semaine du <?php echo date('d/m/Y', strtotime($semaine_data[0]['date'])); ?></h2>
                <a href="?tab=presences&date=<?php echo $semaine_suiv; ?>" class="btn btn-secondary"><i class="fas fa-chevron-right"></i></a>
            </div>

            <div class="semaine-grid">
                    <?php if (!empty($semaine_data)): ?>
                        <?php foreach ($semaine_data as $jour): ?>
                            <div class="jour-card status-<?php echo $jour['statut']; ?>">
                                <div class="jour-header">
                                    <h3><?php echo $jour['nom_jour']; ?></h3>
                                    <span><?php echo date('d/m', strtotime($jour['date'])); ?></span>
                                </div>
                                <div class="jour-body">
                                    <div class="jour-status-icon">
                                        <?php 
                                            switch($jour['statut']) {
                                                case 'present': echo '<i class="fas fa-check-circle"></i>'; break;
                                                case 'retard': echo '<i class="fas fa-clock"></i>'; break;
                                                case 'absent': echo '<i class="fas fa-times-circle"></i>'; break;
                                                default: echo '<i class="fas fa-calendar-day"></i>'; break;
                                            }
                                        ?>
                                    </div>
                                    <div class="jour-status-text">
                                        <?php echo ucfirst(str_replace('_', ' de ', $jour['statut'])); ?>
                                    </div>
                                    <?php if ($jour['details']): ?>
                                        <div class="jour-details">
                                            <div class="detail-item">
                                                <i class="fas fa-sign-in-alt"></i>
                                                <span><?php echo $jour['details']['arrivee']; ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-sign-out-alt"></i>
                                                <span><?php echo $jour['details']['depart'] ?? '-'; ?></span>
                                            </div>
                                            
                                            <!-- NOUVELLE VERSION POUR AFFICHER LA LOCALISATION EN TEXTE -->
                                            <?php if (!empty($jour['details']['localisation'])): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span title="<?php echo htmlspecialchars($jour['details']['localisation']); ?>">
                                                    <?php echo htmlspecialchars($jour['details']['localisation']); ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
             <a href="?tab=presences" class="btn btn-primary" style="margin-top: 1rem;">Semaine actuelle</a>
        </div>
    </div>



  


<?php elseif ($onglet_actif === 'evaluation'): ?>
    <?php include 'evaluation_view.php'; ?>
<?php endif; ?>
    <!-- MODALS - Fenêtres modales pour les actions -->
   
    <!-- Modal pour nouveau message avec pièce jointe -->
    <div id="modalNouveauMessage" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouveau message</h3>
                <button class="modal-close" onclick="fermerModal('modalNouveauMessage')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <!-- Formulaire d'envoi de message -->
            <form id="formNouveauMessage" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Sélection du destinataire -->
                    <div class="form-group">
                        <label>Destinataire</label>
                        <select name="destinataire_id" required>
                            <option value="">Sélectionner un destinataire</option>
                            <?php if (isset($utilisateurs)): ?>
                                <?php foreach ($utilisateurs as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom'] . ' (' . $user['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <!-- Sujet du message -->
                    <div class="form-group">
                        <label>Sujet</label>
                        <input type="text" name="sujet" required>
                    </div>
                    <!-- Contenu du message -->
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="contenu" rows="6" required></textarea>
                    </div>
                    <!-- Upload de pièce jointe -->
                    <div class="form-group">
                        <label>Pièce jointe (optionnel)</label>
                        <input type="file" name="fichier" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fermerModal('modalNouveauMessage')">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
   
    <!-- Modal pour afficher un message -->
    <div id="modalVoirMessage" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="message-subject"></h3>
                <button class="modal-close" onclick="fermerModal('modalVoirMessage')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="message-header">
                    <span id="message-from"></span>
                    <span id="message-date"></span>
                </div>
                <div id="message-content" class="message-content"></div>
                <div id="message-attachments"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirMessage')">Fermer</button>
                <!-- NOUVEAU BOUTON RÉPONDRE -->
                <button type="button" class="btn btn-primary" id="boutonRepondreMessage" onclick="repondreAuMessage()">
                    <i class="fas fa-reply"></i> Répondre
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pour nouveau rapport -->
    <div id="modalNouveauRapport" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouveau rapport</h3>
                <button class="modal-close" onclick="fermerModal('modalNouveauRapport')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formNouveauRapport">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Type de rapport</label>
                        <select name="type" required>
                            <option value="">Sélectionner un type</option>
                            <option value="journalier">Journalier</option>
                            <option value="hebdomadaire">Hebdomadaire</option>
                            <option value="mensuel">Mensuel</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="titre" required>
                    </div>
                    <div class="form-group">
                        <label>Activités réalisées</label>
                        <textarea name="activites" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Difficultés rencontrées</label>
                        <textarea name="difficultes" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Solutions apportées</label>
                        <textarea name="solutions" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fermerModal('modalNouveauRapport')">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Créer le rapport
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- ======================================================= -->
<!-- ============ NOUVELLE MODALE : VOIR TÂCHE ============= -->
<!-- ======================================================= -->
<div id="modalVoirTache" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="tacheModalTitle">Détails de la tâche</h3>
            <button class="modal-close" onclick="fermerModal('modalVoirTache')">
                <i class="fas fa-times"></i>
            </button>
    </div>
    <div class="modal-body" id="tacheModalBody">
<!-- Le contenu sera injecté ici par JavaScript -->
        <div class="loading-spinner"></div>
    </div>
    <div class="modal-footer">
    <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirTache')">Fermer</button>
        </div>
    </div>
</div>
<?php if ($theme_stagiaire): ?>
<div id="modalVoirTheme" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Détails de mon thème</h3>
            <button class="modal-close" onclick="fermerModal('modalVoirTheme')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <h4><?php echo htmlspecialchars($theme_stagiaire['titre']); ?></h4>
            <div class="theme-details-filiere">
                <strong>Filière :</strong> <?php echo htmlspecialchars($theme_stagiaire['filiere']); ?>
            </div>
            <hr>
            <p class="theme-details-description">
                <strong>Description :</strong><br>
                <?php echo nl2br(htmlspecialchars($theme_stagiaire['description'])); ?>
            </p>
            <hr>
            <div class="theme-details-dates">
                <span><strong>Début :</strong> <?php echo date('d/m/Y', strtotime($theme_stagiaire['date_debut'])); ?></span>
                <span><strong>Fin :</strong> <?php echo date('d/m/Y', strtotime($theme_stagiaire['date_fin'])); ?></span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirTheme')">Fermer</button>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- ======================================================= -->
<!-- ============= NOUVELLE MODALE : VOIR RAPPORT ============ -->
<!-- ======================================================= -->
<div id="modalVoirRapport" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3 id="rapportModalTitle">Détails du Rapport</h3>
            <button class="modal-close" onclick="fermerModal('modalVoirRapport')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="rapportModalBody">
            <!-- Le contenu détaillé du rapport sera injecté ici par JavaScript -->
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer" id="rapportModalFooter">
            <!-- Les boutons (Télécharger, Fermer) seront injectés ici -->
            <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirRapport')">Fermer</button>
        </div>
    </div>
</div>
    <!-- Zone de notifications -->
    <div id="notifications"></div>
   
    <!-- Variable JavaScript pour l'ID utilisateur -->
    <script>
        const currentUserId = <?php echo $user_id; ?>;
    </script>
    
    <!-- Script JavaScript pour les interactions -->
    <script src="js/dashboardStagiaire.js"></script>
</body>
</html>