<?php
/**
 * Dashboard principal pour les encadreurs et administrateurs
 */
session_start();
require_once 'classes/Database.php';
require_once 'classes/Message.php';
require_once 'classes/Rapport.php';
require_once 'classes/Tache.php';
require_once 'classes/Theme.php';
require_once 'classes/Evaluation.php';
require_once 'classes/Theme.php';
require_once 'classes/Presence.php';
require_once 'classes/Utilisateur.php';

// Vérifier si l'utilisateur est connecté et est un encadreur ou admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['encadreur', 'admin'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$nom_complet = $_SESSION['prenom'] . ' ' . $_SESSION['nom'];
$role = $_SESSION['role'];

// Initialiser les classes
$message = new Message($user_id);
$tache = new Tache();
// Récupérer les statistiques pour le dashboard
$nb_messages_non_lus = $message->compterNonLus();

// Statistiques spécifiques aux encadreurs
$conn = Database::getConnection();
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM stagiaire WHERE encadreur_id = ?) as nb_stagiaires,
    (SELECT COUNT(*) FROM rapports r JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur WHERE s.encadreur_id = ?) as nb_rapports_total,
    (SELECT COUNT(*) FROM rapports r JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur WHERE s.encadreur_id = ? AND r.statut = 'en_attente') as nb_rapports_attente";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats = [
    'nb_stagiaires' => $conn->query("SELECT COUNT(*) FROM stagiaire")->fetch_row()[0] ?? 0,
    'nb_encadreurs' => $conn->query("SELECT COUNT(*) FROM encadreur")->fetch_row()[0] ?? 0,
    'nb_rapports_attente' => $conn->query("SELECT COUNT(*) FROM rapports WHERE statut = 'en_attente'")->fetch_row()[0] ?? 0,
];

// Traitement des actions AJAX


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {

        case 'envoyer_message':
            $destinataire_id = $_POST['destinataire_id'];
            $sujet = $_POST['sujet'];
            $contenu = $_POST['contenu'];
            $fichier = isset($_FILES['fichier']) ? $_FILES['fichier'] : null;
            
            $resultat = $message->envoyer($destinataire_id, $sujet, $contenu, $fichier);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'supprimer_message':
            if (isset($_POST['message_id'])) {
                $message_id = (int)$_POST['message_id'];
                $resultat = $message->supprimerMessage($message_id);
                echo json_encode(['success' => $resultat]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de message manquant.']);
            }
            exit();
            
        case 'valider_rapport':
            $rapport_id = $_POST['rapport_id'];
            $statut = $_POST['statut'];
            $commentaire = $_POST['commentaire'] ?? '';
            
            $resultat = Rapport::changerStatutRapport($rapport_id, $statut, $commentaire);
            echo json_encode(['success' => $resultat]);
            exit();
            
        case 'marquer_lu':
            $message_id = $_POST['message_id'];
            $resultat = $message->marquerCommeLu($message_id);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'creer_tache':
            $_POST['encadreur_id'] = $user_id;
            $fichier = isset($_FILES['fichier_joint']) ? $_FILES['fichier_joint'] : null;
            $resultat = $tache->creer($_POST, $fichier);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'get_tache':
            $tache_id = $_POST['tache_id'];
            $data = $tache->getTacheById($tache_id);
            echo json_encode($data);
            exit();

        case 'modifier_tache':
            $fichier = isset($_FILES['fichier_joint']) ? $_FILES['fichier_joint'] : null;
            $resultat = $tache->modifier($_POST, $fichier);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'supprimer_tache':
            $tache_id = $_POST['tache_id'];
            $resultat = $tache->supprimer($tache_id);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'creer_theme':
            $_POST['encadreur_id'] = $user_id;
            $theme = new Theme();
            $resultat = $theme->creer($_POST);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'modifier_theme':
            $_POST['encadreur_id'] = $user_id;
            $theme = new Theme();
            $resultat = $theme->modifier($_POST);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'get_theme_details':
            $theme_id = $_POST['theme_id'];
            $theme = new Theme();
            $data = $theme->getThemeById($theme_id);
            echo json_encode($data);
            exit();

        case 'supprimer_theme':
            $theme_id = $_POST['theme_id'];
            $theme = new Theme();
            $resultat = $theme->supprimer($theme_id, $user_id);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'attribuer_theme':
            $theme_id = $_POST['theme_id'];
            $stagiaire_id = $_POST['stagiaire_id'];
            $theme = new Theme();
            $resultat = $theme->attribuer($theme_id, $stagiaire_id, $user_id);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'get_stagiaire_details':
            header('Content-Type: application/json');
            $stagiaire_id = $_POST['stagiaire_id'];
            
            // Correction : requête différente selon le rôle
            if ($role === 'admin') {
                $sql_stagiaire = "SELECT u.*, s.filiere, s.niveau, s.date_debut, s.date_fin
                                  FROM utilisateurs u
                                  JOIN stagiaire s ON u.id = s.id_utilisateur
                                  WHERE u.id = ?";
                $stmt_stagiaire = $conn->prepare($sql_stagiaire);
                $stmt_stagiaire->bind_param("i", $stagiaire_id);
            } else {
                $sql_stagiaire = "SELECT u.*, s.filiere, s.niveau, s.date_debut, s.date_fin
                                  FROM utilisateurs u
                                  JOIN stagiaire s ON u.id = s.id_utilisateur
                                  WHERE u.id = ? AND s.encadreur_id = ?";
                $stmt_stagiaire = $conn->prepare($sql_stagiaire);
                $stmt_stagiaire->bind_param("ii", $stagiaire_id, $user_id);
            }
            $stmt_stagiaire->execute();
            $stagiaire_details = $stmt_stagiaire->get_result()->fetch_assoc();

            // Récupérer les thèmes disponibles (créés par l'encadreur et non attribués)
            $theme = new Theme();
            $themes_result = $theme->getThemesByEncadreur($user_id);
            $available_themes = [];
            while ($th = $themes_result->fetch_assoc()) {
                if ($th['stagiaire_id'] === null) {
                    $available_themes[] = $th;
                }
            }
            
            echo json_encode([
                'success' => $stagiaire_details ? true : false,
                'stagiaireDetails' => $stagiaire_details,
                'availableThemes' => $available_themes
            ]);
            exit();

        case 'get_rapport_details':
            // L'ID du rapport est passé en POST
            if (isset($_POST['rapport_id'])) {
                $rapport_id = (int)$_POST['rapport_id'];
                
                // On utilise la méthode statique qui n'a pas besoin d'instance
                $rapport_details = Rapport::getRapportById($rapport_id, $user_id, $role);
                
                if ($rapport_details) {
                    echo json_encode(['success' => true, 'data' => $rapport_details]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Rapport non trouvé ou accès refusé.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de rapport manquant.']);
            }
            exit();

        case 'supprimer_rapport':
            if ($role !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                exit();
            }
            if (isset($_POST['rapport_id'])) {
                $rapport_id = (int)$_POST['rapport_id'];
                $conn = Database::getConnection();
                
                // On commence une transaction pour s'assurer que tout se passe bien
                $conn->begin_transaction();

                try {
                    // 1. Récupérer le nom du fichier PDF avant de supprimer l'enregistrement
                    $stmt_select = $conn->prepare("SELECT fichier_pdf FROM rapports WHERE id = ?");
                    $stmt_select->bind_param("i", $rapport_id);
                    $stmt_select->execute();
                    $result = $stmt_select->get_result();
                    $row = $result->fetch_assoc();
                    $stmt_select->close();

                    // 2. Supprimer l'enregistrement de la base de données
                    $stmt_delete = $conn->prepare("DELETE FROM rapports WHERE id = ?");
                    $stmt_delete->bind_param("i", $rapport_id);
                    $stmt_delete->execute();

                    if ($stmt_delete->affected_rows > 0) {
                        // 3. Si la suppression en BDD a réussi, on supprime le fichier physique
                        if ($row && !empty($row['fichier_pdf'])) {
                            // Utiliser un chemin absolu pour plus de fiabilité
                            $file_path = __DIR__ . '/uploads/rapports/' . $row['fichier_pdf'];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                        // Valider la transaction
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'Rapport supprimé avec succès.']);
                    } else {
                        // Annuler si le rapport n'a pas été trouvé
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Le rapport n\'a pas été trouvé ou a déjà été supprimé.']);
                    }
                    $stmt_delete->close();

                } catch (Exception $e) {
                    // Annuler la transaction en cas d'erreur
                    $conn->rollback();
                    // Log de l'erreur pour le débogage
                    error_log('Erreur de suppression de rapport: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Une erreur de base de données est survenue lors de la suppression.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de rapport manquant.']);
            }
            exit();

        case 'sauvegarder_evaluation':
            $evaluation = new Evaluation();
            // On appelle la nouvelle méthode "sauvegarder"
            $resultat = $evaluation->sauvegarder($_POST);
            echo json_encode(['success' => $resultat]);
            exit();

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




// Récupérer les données selon l'onglet actif
$onglet_actif = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$filtre = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$recherche = isset($_GET['search']) ? $_GET['search'] : '';

switch ($onglet_actif) {

    case 'gestion-utilisateurs':
        $liste_utilisateurs = Utilisateur::listerTous($recherche);
        $liste_encadreurs = Utilisateur::listerEncadreurs();
        break;

    case 'messagerie':
        $messages = $message->getMessages($filtre, $recherche);
        $utilisateurs = $message->getUtilisateursDisponibles();
        break;
    case 'rapports':
        if ($role === 'admin') {
            // L'admin voit TOUS les rapports du système
            $rapports = Rapport::listerTousLesRapports($filtre, $recherche);
        } else {
            // L'encadreur ne voit que les rapports de ses stagiaires
            $rapports = Rapport::getRapportsEncadreur($user_id, $filtre, $recherche);
        }
        break;
     case 'gestion-stagiaires':
        // La requête de base sélectionne tous les stagiaires et leur encadreur
        $sql = "SELECT u.id, u.nom, u.prenom, u.email, s.date_debut, s.date_fin,
                       ue.prenom as encadreur_prenom, ue.nom as encadreur_nom
                FROM utilisateurs u 
                JOIN stagiaire s ON u.id = s.id_utilisateur
                LEFT JOIN utilisateurs ue ON s.encadreur_id = ue.id";
        
        $params = [];
        $types = "";

        // Si l'utilisateur n'est PAS un admin, on ajoute le filtre par encadreur
        if ($role !== 'admin') {
            $sql .= " WHERE s.encadreur_id = ?";
            $params[] = $user_id;
            $types .= "i";
        }

        if (!empty($recherche)) {
            // Ajoute 'AND' si un 'WHERE' existe déjà, sinon 'WHERE'
            $sql .= (strpos($sql, 'WHERE') === false) ? " WHERE" : " AND";
            $sql .= " (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR ue.nom LIKE ? OR ue.prenom LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sssss";
        }
        $sql .= " ORDER BY u.nom, u.prenom";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $stagiaires = $stmt->get_result();
        break;

     case 'taches':
        // Récupérer les stagiaires pour le modal de création
        if ($role === 'admin') {
            // L'admin voit tous les stagiaires
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
        } else {
            // L'encadreur ne voit que ses stagiaires
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur WHERE s.encadreur_id = ? ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            $stagiaires_stmt->bind_param("i", $user_id);
        }
        $stagiaires_stmt->execute();
        $stagiaires_encadreur = $stagiaires_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Récupérer les tâches en fonction du rôle
        if ($role === 'admin') {
            $taches_result = Tache::listerToutesLesTaches($recherche);
        } else {
            $taches_result = $tache->getTachesPourEncadreur($user_id, $recherche);
        }

        // Organiser les tâches par jour pour l'affichage
        $taches_par_jour = [];
        while($t = $taches_result->fetch_assoc()) {
            $echeance = $t['date_echeance'];
            if (!isset($taches_par_jour[$echeance])) {
                $taches_par_jour[$echeance] = [];
            }
            $taches_par_jour[$echeance][] = $t;
        }
        ksort($taches_par_jour); // Trier par date
        break;

    case 'presences':
        // Charger la liste des stagiaires pour le sélecteur
        if ($role === 'admin') {
             $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur ORDER BY u.nom, u.prenom";
             $stmt = $conn->prepare($stagiaires_sql);
        } else {
             $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur WHERE s.encadreur_id = ? ORDER BY u.nom, u.prenom";
             $stmt = $conn->prepare($stagiaires_sql);
             $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $stagiaires_encadreur = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;

   case 'profil':
        // On va calculer le nombre de stagiaires spécifique à ce profil
        $profil_nb_stagiaires = 0;

        if ($role === 'admin') {
            // Pour l'admin, on récupère les infos de base depuis la table utilisateurs
            $profil_sql = "SELECT * FROM utilisateurs WHERE id = ?";
            $profil_stmt = $conn->prepare($profil_sql);
            $profil_stmt->bind_param("i", $user_id);
            $profil_stmt->execute();
            $profil = $profil_stmt->get_result()->fetch_assoc();

            // On ajoute manuellement les champs qui n'existent pas pour un admin
            // pour éviter les erreurs dans l'affichage.
            $profil['poste'] = 'Administrateur Système';
            $profil['service'] = 'Administration';
            
            // L'admin supervise tous les stagiaires du système
            $profil_nb_stagiaires = $conn->query("SELECT COUNT(*) FROM stagiaire")->fetch_row()[0] ?? 0;

        } else { // Si c'est un encadreur
            // On utilise la requête existante qui joint la table encadreur
            $profil_sql = "SELECT u.*, e.poste, e.service 
                           FROM utilisateurs u 
                           JOIN encadreur e ON u.id = e.id_utilisateur 
                           WHERE u.id = ?";
            $profil_stmt = $conn->prepare($profil_sql);
            $profil_stmt->bind_param("i", $user_id);
            $profil_stmt->execute();
            $profil = $profil_stmt->get_result()->fetch_assoc();

            // L'encadreur ne supervise que ses propres stagiaires
            $stmt_stag = $conn->prepare("SELECT COUNT(*) FROM stagiaire WHERE encadreur_id = ?");
            $stmt_stag->bind_param("i", $user_id);
            $stmt_stag->execute();
            $profil_nb_stagiaires = $stmt_stag->get_result()->fetch_row()[0] ?? 0;
        }
        break;

    case 'gestion-theme':
        $theme = new Theme();
        
        // Logique de récupération des thèmes en fonction du rôle
        if ($role === 'admin') {
            $themes = Theme::listerTousLesThemes($recherche);
        } else {
            $themes = $theme->getThemesByEncadreur($user_id, $recherche);
        }
        
        // Logique pour peupler la liste des stagiaires dans les modaux d'attribution
        if ($role === 'admin') {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom 
                               FROM utilisateurs u 
                               JOIN stagiaire s ON u.id = s.id_utilisateur ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
        } else {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom 
                               FROM utilisateurs u 
                               JOIN stagiaire s ON u.id = s.id_utilisateur 
                               WHERE s.encadreur_id = ? ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            $stagiaires_stmt->bind_param("i", $user_id);
        }
        
        $stagiaires_stmt->execute();
        $stagiaires_encadreur = $stagiaires_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;

    

    case 'evaluation':
        // --- Récupération de la liste des stagiaires pour l'affichage ---
        if ($role === 'admin') {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom 
                               FROM utilisateurs u 
                               JOIN stagiaire s ON u.id = s.id_utilisateur";
            $params = [];
            $types = "";
        } else {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom 
                               FROM utilisateurs u 
                               JOIN stagiaire s ON u.id = s.id_utilisateur 
                               WHERE s.encadreur_id = ?";
            $params = [$user_id];
            $types = "i";
        }
        
        if (!empty($recherche)) {
            $stagiaires_sql .= (strpos($stagiaires_sql, 'WHERE') === false) ? " WHERE" : " AND";
            $stagiaires_sql .= " (u.nom LIKE ? OR u.prenom LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm);
            $types .= "ss";
        }
        
        $stagiaires_stmt = $conn->prepare($stagiaires_sql);
        if(!empty($params)){
            $stagiaires_stmt->bind_param($types, ...$params);
        }
        $stagiaires_stmt->execute();
        $stagiaires_encadreur = $stagiaires_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // --- Logique pour afficher les détails d'un stagiaire sélectionné ---
        if (isset($_GET['stagiaire_id'])) {
            $stagiaire_id_eval = intval($_GET['stagiaire_id']);
            
            // La vérification de sécurité fonctionne pour l'admin car sa liste $stagiaires_encadreur est complète
            $is_my_stagiaire = false;
            foreach ($stagiaires_encadreur as $stag) {
                if ($stag['id'] == $stagiaire_id_eval) {
                    $is_my_stagiaire = true;
                    $stagiaire_info = $stag;
                    break;
                }
            }

            if ($is_my_stagiaire) {
                $evaluation = new Evaluation();
                $evaluation_data = $evaluation->getEvaluationForStagiaire($stagiaire_id_eval);
            }
        }
        break;
    
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?php echo ucfirst($role); ?> - <?php echo htmlspecialchars($nom_complet); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
  
    </style>
    <link rel="stylesheet" href="css/taches.css">
    <link rel="stylesheet" href="css/themes.css">
    <link rel="stylesheet" href="css/evaluation.css">
    <link rel="stylesheet" href="css/presence.css">
    <link rel="stylesheet" href="css/dashboardEncadreur.css">
    <link rel="stylesheet" href="css/form.css">
    <link rel="stylesheet" href="css/gestion_utilisateurs.css">
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-user-tie"></i>
            </div>
            <h3><?php echo ucfirst($role); ?></h3>
        </div>
        
        <ul class="sidebar-menu">
            <li class="<?php echo $onglet_actif === 'dashboard' ? 'active' : ''; ?>"><a href="?tab=dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="<?php echo $onglet_actif === 'messagerie' ? 'active' : ''; ?>">
                <a href="?tab=messagerie">
                    <i class="fas fa-envelope"></i>
                    <span>Messagerie</span>
                    <?php if ($nb_messages_non_lus > 0): ?>
                        <span class="badge"><?php echo $nb_messages_non_lus; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'rapports' ? 'active' : ''; ?>">
                <a href="?tab=rapports">
                    <i class="fas fa-file-alt"></i>
                    <span>Rapports</span>
                    <?php if ($stats['nb_rapports_attente'] > 0): ?>
                        <span class="badge"><?php echo $stats['nb_rapports_attente']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'taches' ? 'active' : ''; ?>">
                <a href="?tab=taches">
                    <i class="fas fa-tasks"></i>
                    <span>Tâches</span>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'presences' ? 'active' : ''; ?>">
                <a href="?tab=presences">
                    <i class="fas fa-calendar-check"></i>
                    <span>Présences Stagiaires</span>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'profil' ? 'active' : ''; ?>">
                <a href="?tab=profil">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'gestion-theme' ? 'active' : ''; ?>">
                <a href="?tab=gestion-theme">
                    <i class="fas fa-lightbulb"></i> <!-- Icône changée pour être plus pertinente -->
                    <span>Gestion Thèmes</span>
                </a>
            </li>
            <li class="<?php echo $onglet_actif === 'gestion-stagiaires' ? 'active' : ''; ?>">
                <a href="?tab=gestion-stagiaires">
                    <i class="fas fa-users"></i>
                    <span>Gestion Stagiaires</span>
                </a>
            </li>

        <li class="<?php echo $onglet_actif === 'evaluation' ? 'active' : ''; ?>">
            <a href="?tab=evaluation">
                <i class="fas fa-chart-bar"></i>
                <span>Évaluations</span>
            </a>
        </li>
        
        <?php if ($role === 'admin'): ?>
            <li class="<?php echo $onglet_actif === 'gestion-utilisateurs' ? 'active' : ''; ?>">
                <a href="?tab=gestion-utilisateurs">
                    <i class="fas fa-users-cog"></i><span>Gestion Utilisateurs</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>

    <main class="main-content">
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
                        <i class="fas fa-user-tie"></i>
                    </div>
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

            <?php elseif ($onglet_actif === 'gestion-utilisateurs'): ?>
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
                                         <button class="btn btn-sm btn-info" title="Consulter" onclick="consulterUtilisateur(<?php echo $user_item['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if($user_item['role'] === 'stagiaire'): ?>
                                            <button class="btn btn-sm btn-primary" title="Affecter un encadreur" onclick="ouvrirModalAffecter(<?php echo $user_item['id']; ?>, '<?php echo $user_item['encadreur_id']; ?>')">
                                                <i class="fas fa-user-tie"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-secondary" title="Modifier" onclick="ouvrirModalModifierUtilisateur(<?php echo $user_item['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if($user_item['statut'] === 'actif'): ?>
                                            <button class="btn btn-sm btn-warning" title="Bloquer" onclick="changerStatut(<?php echo $user_item['id']; ?>, 'bloque')">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-success" title="Débloquer" onclick="changerStatut(<?php echo $user_item['id']; ?>, 'actif')">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-danger" title="Supprimer" onclick="supprimerUtilisateur(<?php echo $user_item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            
            <?php elseif ($onglet_actif === 'messagerie'): ?>
                <div class="messagerie-content">
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <button class="btn btn-primary" onclick="ouvrirNouveauMessage()">
                                <i class="fas fa-plus"></i>
                                Nouveau message
                            </button>
                        </div>
                        <div class="toolbar-right">
                            <select class="filter-select" onchange="filtrerMessages(this.value)">
                                <option value="all" <?php echo $filtre === 'all' ? 'selected' : ''; ?>>Tous les messages</option>
                                <option value="unread" <?php echo $filtre === 'unread' ? 'selected' : ''; ?>>Non lus</option>
                                <option value="sent" <?php echo $filtre === 'sent' ? 'selected' : ''; ?>>Envoyés</option>
                            </select>
                            <div class="search-box">
                                <input type="text" placeholder="Rechercher..." value="<?php echo htmlspecialchars($recherche); ?>"
                                      onkeyup="rechercherMessages(this.value)">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>

                    <div class="messages-list">
    <?php if (isset($messages) && $messages->num_rows > 0): ?>
        <?php while ($msg = $messages->fetch_assoc()): ?>
            <div class="message-item <?php echo $msg['lu'] == 0 && $msg['destinataire_id'] == $user_id ? 'unread' : ''; ?>"
                 onclick="ouvrirMessage(<?php echo $msg['id']; ?>)">
                <div class="message-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="sender">
                            <?php
                            if ($msg['expediteur_id'] == $user_id) {
                                echo 'À: ' . htmlspecialchars($msg['dest_prenom'] . ' ' . $msg['dest_nom']);
                            } else {
                                echo 'De: ' . htmlspecialchars($msg['exp_prenom'] . ' ' . $msg['exp_nom']);
                            }
                            ?>
                        </span>
                        <div class="message-meta"> <!-- Conteneur pour la date et le bouton -->
                            <span class="date"><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></span>
                            <!-- BOUTON DE SUPPRESSION AJOUTÉ ICI -->
                            <button class="btn-delete-message" title="Supprimer ce message" onclick="supprimerMessage(event, <?php echo $msg['id']; ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="message-subject">
                        <?php echo htmlspecialchars($msg['sujet']); ?>
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
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Aucun message trouvé</p>
        </div>
    <?php endif; ?>
    </div>



            <?php elseif ($onglet_actif === 'rapports'): ?>
                <div class="rapports-content">
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <h2>Rapports des stagiaires</h2>
                        </div>
                        <div class="toolbar-right">
                            <select class="filter-select" onchange="filtrerRapports(this.value)">
                                <option value="all" <?php echo $filtre === 'all' ? 'selected' : ''; ?>>Tous les rapports</option>
                                <option value="en_attente" <?php echo $filtre === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="validé" <?php echo $filtre === 'validé' ? 'selected' : ''; ?>>Validés</option>
                                <option value="rejeté" <?php echo $filtre === 'rejeté' ? 'selected' : ''; ?>>Rejetés</option>
                            </select>
                            <div class="search-box">
                                <input type="text" placeholder="Rechercher..." value="<?php echo htmlspecialchars($recherche); ?>" 
                                       onkeyup="rechercherRapports(this.value)">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>

                    <div class="rapports-list">
    <?php if (isset($rapports) && $rapports->num_rows > 0): ?>
        <?php while ($rpt = $rapports->fetch_assoc()): ?>
            <div class="rapport-item">
                <div class="rapport-header">
                    <div class="rapport-info">
                        <span class="type-badge type-<?php echo $rpt['type']; ?>">
                            <?php echo ucfirst($rpt['type']); ?>
                        </span>
                        <span class="stagiaire-name">
                            <?php echo htmlspecialchars($rpt['stag_prenom'] . ' ' . $rpt['stag_nom']); ?>
                            
                            <!-- AJOUT : AFFICHER LE NOM DE L'ENCADREUR POUR L'ADMIN -->
                            <?php if ($role === 'admin' && !empty($rpt['enc_prenom'])): ?>
                                <small class="encadreur-info">
                                    (Encadré par <?php echo htmlspecialchars($rpt['enc_prenom'] . ' ' . $rpt['enc_nom']); ?>)
                                </small>
                            <?php elseif ($role === 'admin'): ?>
                                 <small class="encadreur-info">(Encadreur non assigné)</small>
                            <?php endif; ?>
                            
                        </span>
                    </div>
                    <div class="rapport-status">
                        <span class="status-badge status-<?php echo str_replace(' ', '_', $rpt['statut']); ?>">
                            <?php echo ucfirst($rpt['statut']); ?>
                        </span>
                        <span class="rapport-date">
                            <?php echo date('d/m/Y', strtotime($rpt['date_soumission'])); ?>
                        </span>
                    </div>
                </div>
                <div class="rapport-content">
                    <h3><?php echo htmlspecialchars($rpt['titre']); ?></h3>
                     <?php if (!empty($rpt['tache_titre'])): ?>
                                        <div class="linked-task">
                                            <i class="fas fa-link"></i>
                                            <strong>Tâche associée :</strong> <?php echo htmlspecialchars($rpt['tache_titre']); ?>
                                        </div>
                                    <?php endif; ?>
                    <p><?php echo htmlspecialchars(substr($rpt['activites'], 0, 150)) . '...'; ?></p>
                </div>
                <div class="rapport-actions">
                        <button class="btn btn-sm" onclick="voirRapport(<?php echo $rpt['id']; ?>)">
                            <i class="fas fa-eye"></i> Voir
                        </button>
                        <a href="telecharger_rapport.php?id=<?= $rpt['id'] ?>" class="btn btn-sm btn-download">
                            <i class="fas fa-download"></i> PDF
                        </a>
                                    <?php if ($rpt['statut'] === 'en attente'): ?>
                        <button class="btn btn-sm btn-success" onclick="validerRapport(<?php echo $rpt['id']; ?>, 'validé')">
                            <i class="fas fa-check"></i>
                            Valider
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="validerRapport(<?php echo $rpt['id']; ?>, 'rejeté')">
                            <i class="fas fa-times"></i>
                            Rejeter
                        </button>
                    <?php endif; ?>
                    <!-- AJOUT DU BOUTON SUPPRIMER POUR L'ADMIN -->
                    <?php if ($role === 'admin'): ?>
                        <button class="btn btn-sm btn-danger" title="Supprimer le rapport" onclick="supprimerRapport(event, <?php echo $rpt['id']; ?>, this)">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>Aucun rapport trouvé</p>
        </div>
    <?php endif; ?>
</div>

                    



    <?php elseif ($onglet_actif === 'taches'): ?>
<div class="taches-content">
    <div class="toolbar">
        <div class="toolbar-left">
            <button class="btn btn-primary" onclick="ouvrirModal('modalNouvelleTache')">
                <i class="fas fa-plus"></i> Nouvelle tâche
            </button>
        </div>
        <div class="toolbar-right">
            <div class="search-box">
                <input type="text" placeholder="Rechercher par titre, stagiaire..." value="<?php echo htmlspecialchars($recherche); ?>" 
                       onkeyup="rechercherTaches(this.value)">
                <i class="fas fa-search"></i>
            </div>
        </div>
    </div>

    <!-- Vue Calendrier/Liste -->
    <div class="taches-calendar-view">
        <?php if (!empty($taches_par_jour)): ?>
            <?php foreach ($taches_par_jour as $jour => $taches_du_jour): ?>
                <div class="calendar-day">
                    <h3 class="calendar-day-header">
                        <i class="fas fa-calendar-day"></i>
                        Échéance : <?php echo date('d/m/Y', strtotime($jour)); ?>
                    </h3>
                    <div class="taches-grid">

                    <?php foreach ($taches_du_jour as $t): ?>
                            <?php
                                // Calculer le statut réel
                                $statut_reel = $t['statut'];
                                if ($statut_reel == 'en_attente' && strtotime($t['date_echeance']) < time()) {
                                    $statut_reel = 'en_retard';
                                }
                            ?>
                            <div class="tache-card status-card-<?php echo $statut_reel; ?>">
                                <div class="tache-card-header">
                                    <h3><?php echo htmlspecialchars($t['titre']); ?></h3>
                                </div>
                                <div class="tache-card-body">
                                    <div class="tache-info">
                                        <div class="info-line">
                                            <i class="fas fa-user-graduate"></i>
                                            <!-- ======================================================== -->
                                            <!-- ==          C'EST CETTE LIGNE QUI EST CORRIGÉE          == -->
                                            <!-- ======================================================== -->
                                            <span><?php echo htmlspecialchars($t['stagiaire_prenom'] . ' ' . $t['stagiaire_nom']); ?></span>
                                        </div>

                                        <!-- Ce bloc pour l'admin est déjà correct -->
                                        <?php if ($role === 'admin' && isset($t['encadreur_prenom'])): ?>
                                            <div class="info-line assigned-by">
                                                <i class="fas fa-user-tie"></i>
                                                <span>Assignée par: <?php echo htmlspecialchars($t['encadreur_prenom'] . ' ' . $t['encadreur_nom']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="info-line">
                                            <i class="fas fa-align-left"></i>
                                            <p><?php echo nl2br(htmlspecialchars(substr($t['description'], 0, 100))) . '...'; ?></p>
                                        </div>
                                        <?php if ($t['nom_fichier_original']): ?>
                                            <div class="info-line">
                                                <i class="fas fa-paperclip"></i>
                                                <a href="uploads/taches/<?php echo $t['fichier_joint']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($t['nom_fichier_original']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="tache-card-footer">
                                    <span class="status-badge status-<?php echo $statut_reel; ?>"><?php echo str_replace('_', ' ', $statut_reel); ?></span>
                                    <div class="tache-actions">
                                        <button class="btn btn-sm btn-info" onclick="voirTache(<?php echo $t['id']; ?>)">
                                            <i class="fas fa-eye"></i> Consulter
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="modifierTache(<?php echo $t['id']; ?>)">
                                            <i class="fas fa-edit"></i> Modifier
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="supprimerTache(<?php echo $t['id']; ?>)">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                       
                        
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>Aucune tâche trouvée.</p>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php elseif ($onglet_actif === 'presences'): ?>
    <?php
        // Récupérer le stagiaire sélectionné, s'il y en a un
        $stagiaire_id_selectionne = isset($_GET['stagiaire_id']) ? (int)$_GET['stagiaire_id'] : null;
        $semaine_data = [];

        // Récupérer la date de référence pour la navigation
        $date_ref = isset($_GET['date']) ? $_GET['date'] : 'today';
        $ref_date_obj = new DateTime($date_ref);

        $semaine_prec = (clone $ref_date_obj)->modify('-7 days')->format('Y-m-d');
        $semaine_suiv = (clone $ref_date_obj)->modify('+7 days')->format('Y-m-d');
        
        // Si un stagiaire est sélectionné, on charge ses données
        if ($stagiaire_id_selectionne) {
            $presence = new Presence();
            $semaine_data = $presence->getPresencePourSemaine($stagiaire_id_selectionne, $date_ref);
        }

        // Déterminer le titre de la semaine
        $debut_semaine_ts = strtotime((clone $ref_date_obj)->modify('monday this week')->format('Y-m-d'));
        $fin_semaine_ts = strtotime((clone $ref_date_obj)->modify('friday this week')->format('Y-m-d'));
        $titre_semaine = "Semaine du " . date('d M', $debut_semaine_ts) . " au " . date('d M Y', $fin_semaine_ts);
    ?>
    <div class="presence-content-liste">
        
        <div class="semaine-view">
            <div class="semaine-header">
                <!-- Sélecteur de stagiaire -->
                <form method="GET" class="stagiaire-select-form">
                    <input type="hidden" name="tab" value="presences">
                    <!-- Barre de recherche indépendante -->
                    <select id="stagiaire-select-presence" name="stagiaire_id" onchange="this.form.submit()" class="filter-select">
                        <option value=""></option>
                        <?php foreach($stagiaires_encadreur as $stag): ?>
                            <option value="<?php echo $stag['id']; ?>" <?php if($stag['id'] == $stagiaire_id_selectionne) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($stag['prenom'] . ' ' . $stag['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <!-- Ajout des CDN jQuery et Select2 + initialisation -->
                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                <script>
                $(document).ready(function() {
                    $('#stagiaire-select-presence').select2({
                        placeholder: "Saisir le nom du stagiaire à rechercher",
                        allowClear: true,
                        language: "fr",
                        width: '100%',
                        minimumResultsForSearch: 0
                    });
                });
                </script>
                    <!-- Ajout des CDN jQuery et Select2 + initialisation -->
                    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                    <script>
                    $(document).ready(function() {
                        $('#stagiaire-select-presence').select2({
                            placeholder: "Rechercher ou sélectionner un stagiaire",
                            allowClear: true,
                            language: "fr",
                            width: '100%',
                            minimumResultsForSearch: 0 // Toujours afficher la barre de recherche
                        });
                    });
                    </script>
            </div>

            <?php if ($stagiaire_id_selectionne): // N'afficher la grille que si un stagiaire est choisi ?>
                <div class="semaine-navigation">
                    <a href="?tab=presences&stagiaire_id=<?php echo $stagiaire_id_selectionne; ?>&date=<?php echo $semaine_prec; ?>" class="btn btn-nav"><i class="fas fa-chevron-left"></i></a>
                    <h2><?php echo $titre_semaine; ?></h2>
                    <a href="?tab=presences&stagiaire_id=<?php echo $stagiaire_id_selectionne; ?>&date=<?php echo $semaine_suiv; ?>" class="btn btn-nav"><i class="fas fa-chevron-right"></i></a>
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
                 <a href="?tab=presences&stagiaire_id=<?php echo $stagiaire_id_selectionne; ?>" class="btn btn-semaine-actuelle">Revenir à la semaine actuelle</a>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-clock"></i>
                    <p>Veuillez sélectionner un stagiaire pour afficher son suivi de présence.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>





    <?php elseif ($onglet_actif === 'profil'): ?>
    <div class="profil-content">
        <div class="profil-header">
            <div class="profil-avatar">
                <!-- Icône différente pour l'admin -->
                <i class="fas <?php echo $role === 'admin' ? 'fa-user-shield' : 'fa-user-tie'; ?>"></i>
            </div>
            <div class="profil-titles">
                <h1><?php echo htmlspecialchars($nom_complet); ?></h1>
                <p class="role-badge"><?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>

        <div class="profil-grid">
            <div class="profil-card">
                <div class="card-header">
                    <i class="fas fa-id-card"></i>
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
                        <span class="info-value"><?php echo ($profil['sex'] ?? 'M') === 'M' ? 'Masculin' : 'Féminin'; ?></span>
                    </div>
                </div>
            </div>

            <div class="profil-card">
                <div class="card-header">
                    <i class="fas fa-briefcase"></i>
                    <h3>Informations professionnelles</h3>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="info-label">Poste :</span>
                        <span class="info-value"><?php echo htmlspecialchars($profil['poste']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Service :</span>
                        <span class="info-value"><?php echo htmlspecialchars($profil['service']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <?php echo $role === 'admin' ? 'Stagiaires dans le système :' : 'Stagiaires encadrés :'; ?>
                        </span>
                        <!-- On utilise la nouvelle variable calculée -->
                        <span class="info-value"><?php echo $profil_nb_stagiaires; ?></span>
                    </div>
                </div>
            </div>

            <div class="profil-card full-width">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h3>Statistiques globales</h3>
                </div>
                <div class="stats-grid">
                    <div class="mini-stat">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <span class="stat-number"><?php echo $nb_messages_non_lus; ?></span>
                            <span class="stat-label">Messages non lus</span>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <i class="fas fa-file-alt"></i>
                        <div>
                            <span class="stat-number"><?php echo $stats['nb_rapports_attente']; ?></span>
                            <span class="stat-label">Rapports en attente</span>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <i class="fas fa-users"></i>
                        <div>
                            <!-- Ici aussi on utilise la nouvelle variable -->
                            <span class="stat-number"><?php echo $profil_nb_stagiaires; ?></span>
                            <span class="stat-label">
                                <?php echo $role === 'admin' ? 'Total Stagiaires' : 'Mes Stagiaires'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($onglet_actif === 'gestion-stagiaires'): ?>
    <div class="gestion-stagiaires-content">
        <div class="toolbar">
    <div class="toolbar-left">
        <h2>Mes Stagiaires</h2>
    </div>
    <div class="toolbar-right">
        <!-- NOUVELLE BARRE DE RECHERCHE -->
        <form method="GET" class="search-form">
            <input type="hidden" name="tab" value="gestion-stagiaires">
            <div class="search-box">
                <input type="text" name="search" placeholder="Rechercher un stagiaire..." value="<?php echo htmlspecialchars($recherche); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</div>
        

        <div class="stagiaires-grid">
            <?php if (isset($stagiaires) && $stagiaires->num_rows > 0): ?>
                <?php while ($stag = $stagiaires->fetch_assoc()): ?>
                    <div class="stagiaire-card">
                        <div class="stagiaire-avatar">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stagiaire-info">
                            <h3><?php echo htmlspecialchars($stag['prenom'] . ' ' . $stag['nom']); ?></h3>
                            <p><?php echo htmlspecialchars($stag['email']); ?></p>
                            <div class="stagiaire-dates">
                                <span>Du <?php echo date('d/m/Y', strtotime($stag['date_debut'])); ?></span>
                                <span>au <?php echo date('d/m/Y', strtotime($stag['date_fin'])); ?></span>
                            </div>
                        </div>
                        <!-- ACTIONS SÉPARÉES -->
                        <div class="stagiaire-actions">
                            <button class="btn btn-sm btn-secondary" onclick="consulterStagiaire(<?php echo $stag['id']; ?>)">
                                <i class="fas fa-eye"></i> Consulter
                            </button>
                            <?php 
                                // On passe le nom du stagiaire en paramètre pour l'afficher dans le titre de la modale
                                $stagiaireNomComplet = htmlspecialchars($stag['prenom'] . ' ' . $stag['nom'], ENT_QUOTES);
                            ?>
                            <button class="btn btn-sm btn-primary" onclick="ouvrirModalAttribuerAStagiaire(<?php echo $stag['id']; ?>, '<?php echo $stagiaireNomComplet; ?>')">
                                <i class="fas fa-lightbulb"></i> Attribuer Thème
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Aucun stagiaire assigné</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

     
            <?php elseif ($onglet_actif === 'gestion-theme'): ?>
<div class="themes-content">
    <div class="toolbar">
        <div class="toolbar-actions">
            <button class="btn btn-primary" onclick="ouvrirModalTheme()">
                <i class="fas fa-plus"></i> Nouveau Thème
            </button>
        </div>
        <div class="toolbar-filters">
            <form method="GET" class="search-form">
                <input type="hidden" name="tab" value="gestion-theme">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Rechercher un thème..." value="<?php echo htmlspecialchars($recherche); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>
    </div>
        <div class="themes-grid">
        <?php if ($themes->num_rows > 0): ?>
            <?php while($th = $themes->fetch_assoc()): ?>
                <div class="theme-card">
                    <div class="theme-card-header">
                        <h3><?php echo htmlspecialchars($th['titre']); ?></h3>
                        <ul class="header-meta">
                            <li>
                                <i class="fas fa-user-tie"></i> 
                                <span><?php echo htmlspecialchars($th['encadreur_prenom'] . ' ' . $th['encadreur_nom']); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-graduation-cap"></i>
                                <span><?php echo htmlspecialchars($th['filiere']); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="theme-card-body">
                        <p><?php echo htmlspecialchars(substr($th['description'], 0, 150)) . '...'; ?></p>
                        <div class="theme-dates">
                            <span>Début: <?php echo date('d/m/Y', strtotime($th['date_debut'])); ?></span>
                            <span>Fin: <?php echo date('d/m/Y', strtotime($th['date_fin'])); ?></span>
                        </div>
                    </div>
                    <div class="theme-card-footer">
                        <div class="theme-status">
                            <?php if ($th['stagiaire_id']): ?>
                                <span class="status-badge status-attribue">
                                    <i class="fas fa-user-check"></i> Attribué à <?php echo htmlspecialchars($th['stagiaire_prenom'] . ' ' . $th['stagiaire_nom']); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-disponible">
                                    <i class="fas fa-circle-notch"></i> Disponible
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="theme-actions">
                            <button class="btn btn-sm btn-info" onclick="voirTheme(<?php echo $th['id']; ?>)">
                                <i class="fas fa-eye"></i> Consulter
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="ouvrirModalAttribuer(<?php echo $th['id']; ?>)">
                                <i class="fas fa-user-plus"></i> Attribuer
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="ouvrirModalTheme(<?php echo $th['id']; ?>)">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="supprimerTheme(<?php echo $th['id']; ?>)">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </div>
                    </div>
                </div>
                
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-lightbulb"></i>
                <p>Aucun thème créé pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

    <?php elseif ($onglet_actif === 'evaluation'): ?>
    <?php if (isset($evaluation_data)): // Si un stagiaire est sélectionné, afficher son évaluation ?>
        
        <a href="?tab=evaluation" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
        <?php include 'evaluation_view.php'; ?>

    <?php else: // Sinon, afficher la liste des stagiaires à évaluer ?>
        <div class="evaluation-selection-container">
            <div class="evaluation-selection-header">
                <h1>Sélectionner un Stagiaire</h1>
                <p>Cliquez sur un stagiaire pour consulter ou remplir son rapport de performance.</p>
            </div>

            <!-- Barre de recherche stylisée -->
            <div class="selection-toolbar">
                <form method="GET" class="search-form">
                    <input type="hidden" name="tab" value="evaluation">
                    <div class="search-box-stylish">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher un stagiaire..." value="<?php echo htmlspecialchars($recherche); ?>">
                    </div>
                </form>
            </div>

            <!-- Nouvelle grille pour les cartes de stagiaire -->
            <div class="evaluation-stagiaire-grid">
                <?php if (!empty($stagiaires_encadreur)): ?>
                    <?php foreach ($stagiaires_encadreur as $stag): ?>
                        <a href="?tab=evaluation&stagiaire_id=<?php echo $stag['id']; ?>" class="stagiaire-select-card">
                            <div class="stagiaire-select-avatar">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="stagiaire-select-info">
                                <h3><?php echo htmlspecialchars($stag['prenom'] . ' ' . $stag['nom']); ?></h3>
                                <span>Voir l'évaluation</span>
                            </div>
                            <div class="stagiaire-select-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <p>Aucun stagiaire ne correspond à votre recherche.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        

    <?php endif; ?>
<?php endif; ?>
    </main>
<!-- ======================================================== -->
    <!-- ==      BLOC COMPLET DES MODALES À AJOUTER            == -->
    <!-- ======================================================== -->
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
                        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" required></div>
                        <div class="form-group"><label>Nom *</label><input type="text" name="nom" required></div>
                        <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone"></div>
                        <div class="form-group"><label>Sexe *</label><select name="sex" required><option value="M">Masculin</option><option value="F">Féminin</option></select></div>
                        <div class="form-group"><label>Mot de passe *</label><input type="password" name="password" required></div>
                    </div>
                    <hr>
                    <h4>Rôle et Informations Spécifiques</h4>
                    <div class="form-group">
                        <label>Rôle de l'utilisateur *</label>
                        <select name="role" id="roleSelect" onchange="toggleRoleFields()" required>
                            <option value="">-- Sélectionner un rôle --</option>
                            <option value="stagiaire">Stagiaire</option>
                            <option value="encadreur">Encadreur</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div id="stagiaireFields" class="role-fields" style="display: none;">
                        <h5>Détails du Stagiaire</h5>
                        <div class="form-grid">
                            <div class="form-group"><label>Filière *</label><input type="text" name="filiere"></div>
                            <div class="form-group"><label>Niveau *</label><input type="text" name="niveau"></div>
                            <div class="form-group"><label>Date de début *</label><input type="date" name="date_debut"></div>
                            <div class="form-group"><label>Date de fin *</label><input type="date" name="date_fin"></div>
                        </div>
                    </div>
                    <div id="encadreurFields" class="role-fields" style="display: none;">
                        <h5>Détails de l'Encadreur</h5>
                        <div class="form-grid">
                            <div class="form-group"><label>Poste *</label><input type="text" name="poste"></div>
                            <div class="form-group"><label>Service *</label><input type="text" name="service"></div>
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
                    <input type="hidden" name="stagiaire_id" id="affecter_stagiaire_id">
                    <div class="form-group">
                        <label for="affecter_encadreur_id">Choisir un encadreur</label>
                        <select id="affecter_encadreur_id" name="encadreur_id">
                            <option value="">-- Aucun --</option>
                            <?php 
                                if (isset($liste_encadreurs) && $liste_encadreurs->num_rows > 0) {
                                    $liste_encadreurs->data_seek(0);
                                    while($enc = $liste_encadreurs->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $enc['id']; ?>"><?php echo htmlspecialchars($enc['prenom'] . ' ' . $enc['nom']); ?></option>
                            <?php 
                                    endwhile;
                                } 
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fermerModal('modalAffecterEncadreur')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalVoirUtilisateur" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="utilisateurModalTitle">Détails de l'Utilisateur</h3>
                <button class="modal-close" onclick="fermerModal('modalVoirUtilisateur')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="utilisateurModalBody">
                <!-- Le contenu détaillé de l'utilisateur sera injecté ici par JavaScript -->
                <div class="loading-spinner"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirUtilisateur')">Fermer</button>
            </div>
        </div>
    </div>
    <!-- Modals -->
    <div id="modalNouveauMessage" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouveau message</h3>
                <button class="modal-close" onclick="fermerModal('modalNouveauMessage')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formNouveauMessage" enctype="multipart/form-data">
                <div class="modal-body">
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
                    <div class="form-group">
                        <label>Sujet</label>
                        <input type="text" name="sujet" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="contenu" rows="6" required></textarea>
                    </div>
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

    <div id="modalAfficherMessage" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="message-subject"></h3>
                <button class="modal-close" onclick="fermerModal('modalAfficherMessage')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="message-header">
                    <div id="message-from"></div>
                    <div id="message-date"></div>
                </div>
                <div id="message-content" class="message-content"></div>
                <div id="message-pieces-jointes"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalAfficherMessage')">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <div id="modalValidationRapport" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Validation du rapport</h3>
                <button class="modal-close" onclick="fermerModal('modalValidationRapport')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formValidationRapport">
                <input type="hidden" name="rapport_id" id="validationRapportId">
                <input type="hidden" name="statut" id="validationStatut">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Commentaire (optionnel)</label>
                        <textarea name="commentaire" rows="4" placeholder="Ajoutez un commentaire pour le stagiaire..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fermerModal('modalValidationRapport')">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i>
                        Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Modal Nouvelle Tâche -->
<div id="modalNouvelleTache" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Attribuer une nouvelle tâche</h3>
            <button class="modal-close" onclick="fermerModal('modalNouvelleTache')"><i class="fas fa-times"></i></button>
        </div>
        <form id="formNouvelleTache" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-group">
                    <label for="stagiaire_id">Choisir un stagiaire *</label>
                    <select name="stagiaire_id" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($stagiaires_encadreur as $stagiaire): ?>
                            <option value="<?php echo $stagiaire['id']; ?>">
                                <?php echo htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="titre">Intitulé de la tâche *</label>
                    <input type="text" name="titre" required>
                </div>
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea name="description" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="date_echeance">Date d’échéance *</label>
                    <input type="date" name="date_echeance" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="fichier_joint">Fichier joint (optionnel)</label>
                    <input type="file" name="fichier_joint">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalNouvelleTache')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>
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

<!-- Modal Modifier Tâche -->
<div id="modalModifierTache" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Modifier la tâche</h3>
            <button class="modal-close" onclick="fermerModal('modalModifierTache')"><i class="fas fa-times"></i></button>
        </div>
        <form id="formModifierTache" enctype="multipart/form-data">
            <input type="hidden" name="tache_id" id="editTacheId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_stagiaire_id">Stagiaire *</label>
                    <select name="stagiaire_id" id="editStagiaireId" required>
                         <?php foreach ($stagiaires_encadreur as $stagiaire): ?>
                            <option value="<?php echo $stagiaire['id']; ?>">
                                <?php echo htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_titre">Intitulé de la tâche *</label>
                    <input type="text" name="titre" id="editTitre" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description *</label>
                    <textarea name="description" id="editDescription" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_date_echeance">Date d’échéance *</label>
                    <input type="date" name="date_echeance" id="editDateEcheance" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Fichier actuel : <span id="fichierActuel"></span></label>
                    <label for="edit_fichier_joint">Remplacer le fichier (optionnel)</label>
                    <input type="file" name="fichier_joint">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalModifierTache')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Mettre à jour</button>
            </div>
        </form>
    </div>
</div>
<!-- Modal pour Créer/Modifier un Thème -->
<div id="modalTheme" class="modal">
    <div class="modal-content">
        <form id="formTheme">
            <div class="modal-header">
                <h3 id="modalThemeTitle">Nouveau Thème</h3>
                <button type="button" class="modal-close" onclick="fermerModal('modalTheme')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="theme_id" id="theme_id">
                <div class="form-group">
                    <label for="titre">Titre du thème *</label>
                    <input type="text" name="titre" id="theme_titre" required>
                </div>
                <div class="form-group">
                    <label for="filiere">Filière concernée *</label>
                    <input type="text" name="filiere" id="theme_filiere" placeholder="Ex: Informatique, Marketing, etc." required>
                </div>
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea name="description" id="theme_description" rows="5" required></textarea>
                </div>
                <div class="form-group-grid">
                    <div class="form-group">
                        <label for="date_debut">Date de début *</label>
                        <input type="date" name="date_debut" id="theme_date_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="date_fin">Date de fin *</label>
                        <input type="date" name="date_fin" id="theme_date_fin" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalTheme')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal pour Attribuer un Thème -->
<div id="modalAttribuerTheme" class="modal">
    <div class="modal-content">
        <form id="formAttribuerTheme">
            <div class="modal-header">
                <h3>Attribuer un thème</h3>
                <button type="button" class="modal-close" onclick="fermerModal('modalAttribuerTheme')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="theme_id" id="attribuer_theme_id">
                <p>Sélectionnez le stagiaire à qui attribuer ce thème :</p>
                <div class="form-group">
                    <label for="stagiaire_id">Stagiaire *</label>
                    <select name="stagiaire_id" id="attribuer_stagiaire_id" required>
                        <option value="">-- Choisir un stagiaire --</option>
                        <?php foreach ($stagiaires_encadreur as $stagiaire): ?>
                            <option value="<?php echo $stagiaire['id']; ?>">
                                <?php echo htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalAttribuerTheme')">Annuler</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-user-check"></i> Confirmer l'attribution</button>
            </div>
        </form>
    </div>
</div>

<div id="modalVoirTheme" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3 id="themeModalTitle">Détails du Thème</h3>
            <button class="modal-close" onclick="fermerModal('modalVoirTheme')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="themeModalBody">
            <!-- Le contenu détaillé du thème sera injecté ici par JavaScript -->
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirTheme')">Fermer</button>
        </div>
    </div>
</div>

<!-- NOUVELLE MODALE : Consulter les Détails du Stagiaire (Lecture Seule) -->
<div id="modalConsulterStagiaire" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3 id="consulterModalTitle">Détails du Stagiaire</h3>
            <button type="button" class="modal-close" onclick="fermerModal('modalConsulterStagiaire')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="stagiaire-details-grid">
                <!-- Informations Personnelles -->
                <div class="profil-card">
                    <div class="card-header"><i class="fas fa-id-card"></i><h3>Informations Personnelles</h3></div>
                    <div class="card-body">
                        <div class="info-item"><span class="info-label">Email :</span><span class="info-value" id="consulterStagiaireEmail"></span></div>
                        <div class="info-item"><span class="info-label">Téléphone :</span><span class="info-value" id="consulterStagiaireTel"></span></div>
                        <div class="info-item"><span class="info-label">Sexe :</span><span class="info-value" id="consulterStagiaireSexe"></span></div>
                    </div>
                </div>
                <!-- Informations de Stage -->
                <div class="profil-card">
                    <div class="card-header"><i class="fas fa-graduation-cap"></i><h3>Informations de Stage</h3></div>
                    <div class="card-body">
                        <div class="info-item"><span class="info-label">Filière :</span><span class="info-value" id="consulterStagiaireFiliere"></span></div>
                        <div class="info-item"><span class="info-label">Niveau :</span><span class="info-value" id="consulterStagiaireNiveau"></span></div>
                        <div class="info-item"><span class="info-label">Période :</span><span class="info-value" id="consulterStagiairePeriode"></span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fermerModal('modalConsulterStagiaire')">Fermer</button>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- ============= NOUVELLE MODALE : VOIR TÂCHE ============ -->
<!-- ======================================================= -->
<div id="modalVoirTache" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3 id="tacheModalTitle">Détails de la Tâche</h3>
            <button class="modal-close" onclick="fermerModal('modalVoirTache')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="tacheModalBody">
            <!-- Le contenu détaillé de la tâche sera injecté ici par JavaScript -->
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer" id="tacheModalFooter">
            <button type="button" class="btn btn-secondary" onclick="fermerModal('modalVoirTache')">Fermer</button>
        </div>
    </div>
</div>

<!-- NOUVELLE MODALE : Attribuer un Thème à un Stagiaire -->
<div id="modalAttribuerThemeAStagiaire" class="modal">
    <div class="modal-content">
        <form id="formAttribuerThemePourStagiaire">
            <div class="modal-header">
                <h3 id="attribuerModalTitle">Attribuer un thème</h3>
                <button type="button" class="modal-close" onclick="fermerModal('modalAttribuerThemeAStagiaire')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="stagiaire_id" id="attribuer_form_stagiaire_id">
                <div class="form-group">
                    <label for="attribuer_theme_id_select">Choisir un thème disponible</label>
                    <select name="theme_id" id="attribuer_theme_id_select" required>
                        <!-- Options chargées par JavaScript -->
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModal('modalAttribuerThemeAStagiaire')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Attribuer ce thème</button>
            </div>
        </form>
    </div>
</div>


    <div id="notifications"></div>

    <script src="js/dashboardEncadreur.js"></script>

<script>
/**
 * Supprime un rapport après confirmation.
 * Cette fonction est réservée aux administrateurs.
 * @param {Event} event - L'événement de clic pour l'arrêter.
 * @param {number} rapportId - L'ID du rapport à supprimer.
 * @param {HTMLElement} element - Le bouton sur lequel on a cliqué.
 */
function supprimerRapport(event, rapportId, element) {
    event.stopPropagation(); // Empêche l'ouverture de la modale de détails
    
    if (confirm('Êtes-vous sûr de vouloir supprimer ce rapport ? Cette action est irréversible et supprimera également le fichier PDF associé.')) {
        const formData = new FormData();
        formData.append('action', 'supprimer_rapport');
        formData.append('rapport_id', rapportId);

        fetch('dashboardAdmin.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                afficherNotification('Rapport supprimé avec succès.', 'success');
                const rapportItem = element.closest('.rapport-item');
                if (rapportItem) {
                    rapportItem.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    rapportItem.style.opacity = '0';
                    rapportItem.style.transform = 'scale(0.95)';
                    setTimeout(() => rapportItem.remove(), 500);
                }
            } else { afficherNotification(data.message || 'Erreur lors de la suppression.', 'error'); }
        }).catch(error => afficherNotification('Une erreur technique est survenue.', 'error'));
    }
}
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>