<?php
/**
 * Dashboard principal pour les encadreurs et administrateurs
 */
date_default_timezone_set('Africa/Douala'); // Définir le fuseau horaire au début

session_start();

require_once 'classes/Database.php';
require_once 'classes/Message.php';
require_once 'classes/Rapport.php';
require_once 'classes/Tache.php';
require_once 'classes/Theme.php'; // Assurez-vous que cette ligne est présente
require_once 'classes/Evaluation.php';
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
$rapport_instance = new Rapport(); 
$theme_instance = new Theme(); // Instanciation pour les méthodes non statiques de Theme (comme creer ou getThemesByEncadreur)


// Statistiques pour le dashboard
$nb_messages_non_lus = $message->compterNonLus();

// Statistiques spécifiques aux encadreurs ou admin
$conn = Database::getConnection();
$stats = [];
if ($role === 'admin') {
    $stats['nb_stagiaires'] = $conn->query("SELECT COUNT(*) FROM stagiaire")->fetch_row()[0] ?? 0;
    $stats['nb_encadreurs'] = $conn->query("SELECT COUNT(*) FROM encadreur")->fetch_row()[0] ?? 0;
    $stats['nb_rapports_attente'] = $conn->query("SELECT COUNT(*) FROM rapports WHERE statut = 'en attente'")->fetch_row()[0] ?? 0;
} else { // Encadreur
    $stats_sql_encadreur = "SELECT 
        (SELECT COUNT(*) FROM stagiaire WHERE encadreur_id = ?) as nb_stagiaires,
        (SELECT COUNT(*) FROM rapports r JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur WHERE s.encadreur_id = ?) as nb_rapports_total,
        (SELECT COUNT(*) FROM rapports r JOIN stagiaire s ON r.stagiaire_id = s.id_utilisateur WHERE s.encadreur_id = ? AND r.statut = 'en attente') as nb_rapports_attente";
    $stats_stmt_encadreur = $conn->prepare($stats_sql_encadreur);
    if ($stats_stmt_encadreur === false) { error_log("Error preparing stats_sql_encadreur: " . $conn->error); }
    $stats_stmt_encadreur->bind_param("iii", $user_id, $user_id, $user_id);
    $stats_stmt_encadreur->execute();
    $stats = $stats_stmt_encadreur->get_result()->fetch_assoc();
    $stats_stmt_encadreur->close();
    $stats['nb_encadreurs'] = 0; // Pas pertinent pour un encadreur
}

// === DÉFINITION DES VARIABLES DE PAGINATION POUR TOUS LES ONGLETTS QUI EN AURONT BESOIN ===
// Variable de pagination pour les MESSAGES
$current_page_messages = isset($_GET['p_msg']) ? (int)$_GET['p_msg'] : 1;
$messages_per_page = 20;

// Variable de pagination pour les RAPPORTS
$current_page_reports = isset($_GET['p_rpt']) ? (int)$_GET['p_rpt'] : 1;
$reports_per_page = 20;

// Variable de pagination pour les TÂCHES
$current_page_tasks = isset($_GET['p_tache']) ? (int)$_GET['p_tache'] : 1;
$tasks_per_page = 20;

// Variable de pagination pour les THÈMES
$current_page_themes = isset($_GET['p_theme']) ? (int)$_GET['p_theme'] : 1;
$themes_per_page = 20;
// =========================================================================================


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
                $resultat_suppression = $message->supprimerMessage($message_id, $_SESSION['role']);
                echo json_encode($resultat_suppression);
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
            echo json_encode($resultat);
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
            $resultat = $theme_instance->creer($_POST);
            echo json_encode($resultat);
            exit();

        case 'modifier_theme':
            $_POST['encadreur_id'] = $user_id; // L'ID de l'utilisateur qui modifie pour la vérification des droits
            $resultat = Theme::modifier($_POST); // Appel statique
            echo json_encode($resultat);
            exit();

        case 'get_theme_details':
            $theme_id = $_POST['theme_id'];
            $data = $theme_instance->getThemeById($theme_id);
            echo json_encode($data);
            exit();

        case 'supprimer_theme':
            $theme_id = $_POST['theme_id'];
            $resultat = Theme::supprimer($theme_id, $user_id); // Appel statique
            echo json_encode($resultat);
            exit();

        case 'attribuer_theme':
            $theme_id = $_POST['theme_id'];
            $stagiaire_id = $_POST['stagiaire_id'];
            $resultat = Theme::attribuer($theme_id, $stagiaire_id, $user_id); // Appel statique
            echo json_encode($resultat);
            exit();

        case 'get_stagiaire_details':
            header('Content-Type: application/json');
            $stagiaire_id = (int)$_POST['stagiaire_id'];
            
            // Correction : requête différente selon le rôle
            if ($role === 'admin') {
                $sql_stagiaire = "SELECT u.*, s.filiere, s.niveau, s.date_debut, s.date_fin
                                  FROM utilisateurs u
                                  JOIN stagiaire s ON u.id = s.id_utilisateur
                                  WHERE u.id = ?";
                $stmt_stagiaire = $conn->prepare($sql_stagiaire);
                if ($stmt_stagiaire === false) { error_log("Error preparing sql_stagiaire (admin): " . $conn->error); }
                $stmt_stagiaire->bind_param("i", $stagiaire_id);
            } else {
                $sql_stagiaire = "SELECT u.*, s.filiere, s.niveau, s.date_debut, s.date_fin
                                  FROM utilisateurs u
                                  JOIN stagiaire s ON u.id = s.id_utilisateur
                                  WHERE u.id = ? AND s.encadreur_id = ?";
                $stmt_stagiaire = $conn->prepare($sql_stagiaire);
                if ($stmt_stagiaire === false) { error_log("Error preparing sql_stagiaire (encadreur): " . $conn->error); }
                $stmt_stagiaire->bind_param("ii", $stagiaire_id, $user_id);
            }
            $stmt_stagiaire->execute();
            $stagiaire_details = $stmt_stagiaire->get_result()->fetch_assoc();
            $stmt_stagiaire->close();

            $theme = new Theme(); // Nouvelle instance pour les méthodes non statiques si besoin
            // Correction: getThemesByEncadreur retourne maintenant un tableau, pas un mysqli_result direct
            $themes_data_for_select = $theme->getThemesByEncadreur($user_id, '', 1, 999); // Récupérer tous les thèmes pour le select
            $themes_result_for_select = $themes_data_for_select['themes'];

            $available_themes = [];
            if ($themes_result_for_select) {
                while ($th = $themes_result_for_select->fetch_assoc()) {
                    if ($th['stagiaire_id'] === null) {
                        $available_themes[] = $th;
                    }
                }
                $themes_result_for_select->free(); // Libérer le résultat
            }
            
            echo json_encode([
                'success' => $stagiaire_details ? true : false,
                'stagiaireDetails' => $stagiaire_details,
                'availableThemes' => $available_themes
            ]);
            exit();

        case 'get_rapport_details':
            if (isset($_POST['rapport_id'])) {
                $rapport_id = (int)$_POST['rapport_id'];
                
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
                
                $conn->begin_transaction();

                try {
                    $stmt_select = $conn->prepare("SELECT fichier_pdf FROM rapports WHERE id = ?");
                    if ($stmt_select === false) { throw new Exception("Erreur de préparation SELECT fichier_pdf: " . $conn->error); }
                    $stmt_select->bind_param("i", $rapport_id);
                    $stmt_select->execute();
                    $result = $stmt_select->get_result();
                    $row = $result->fetch_assoc();
                    $result->free();
                    $stmt_select->close();

                    $stmt_delete = $conn->prepare("DELETE FROM rapports WHERE id = ?");
                    if ($stmt_delete === false) { throw new Exception("Erreur de préparation DELETE rapports: " . $conn->error); }
                    $stmt_delete->bind_param("i", $rapport_id);
                    $stmt_delete->execute();

                    if ($stmt_delete->affected_rows > 0) {
                        if ($row && !empty($row['fichier_pdf'])) {
                            $file_path = __DIR__ . '/uploads/rapports/' . $row['fichier_pdf'];
                            if (file_exists($file_path) && is_file($file_path)) {
                                unlink($file_path);
                            }
                        }
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'Rapport supprimé avec succès.']);
                    } else {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Le rapport n\'a pas été trouvé ou a déjà été supprimé.']);
                    }
                    $stmt_delete->close();

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log('Erreur de suppression de rapport: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Une erreur de base de données est survenue lors de la suppression.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de rapport manquant.']);
            }
            exit();

        case 'sauvegarder_evaluation':
            $evaluation = new Evaluation();
            $resultat = $evaluation->sauvegarder($_POST);
            echo json_encode(['success' => $resultat]);
            exit();

        case 'creer_utilisateur':
            $resultat = Utilisateur::creer($_POST);
            if (is_array($resultat)) {
                echo json_encode($resultat);
            } else {
                echo json_encode(['success' => $resultat]);
            }
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
            if (is_array($resultat)) {
                echo json_encode($resultat);
            } else {
                echo json_encode(['success' => $resultat]);
            }
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


// === DÉFINITION DES VARIABLES DE PAGINATION POUR TOUS LES ONGLETTS QUI EN AURONT BESOIN ===
// Variable de pagination pour les MESSAGES
$current_page_messages = isset($_GET['p_msg']) ? (int)$_GET['p_msg'] : 1;
$messages_per_page = 20;

// Variable de pagination pour les RAPPORTS
$current_page_reports = isset($_GET['p_rpt']) ? (int)$_GET['p_rpt'] : 1;
$reports_per_page = 20;

// Variable de pagination pour les TÂCHES
$current_page_tasks = isset($_GET['p_tache']) ? (int)$_GET['p_tache'] : 1;
$tasks_per_page = 20;

// Variable de pagination pour les THÈMES
$current_page_themes = isset($_GET['p_theme']) ? (int)$_GET['p_theme'] : 1;
$themes_per_page = 20;
// =========================================================================================


switch ($onglet_actif) {

    case 'gestion-utilisateurs':
        $liste_utilisateurs = Utilisateur::listerTous($recherche);
        $liste_encadreurs = Utilisateur::listerEncadreurs();
        break;

    case 'messagerie':
        // Appel de la méthode getMessages avec les paramètres de pagination
        $message_data = $message->getMessages($filtre, $recherche, $current_page_messages, $messages_per_page);

        // Extrayez les données nécessaires pour l'affichage
        $messages_result_set = $message_data['messages'];
        $total_messages = $message_data['total_messages'];
        $current_page_messages = $message_data['current_page'];
        $limit_messages = $message_data['limit'];
        $total_pages_messages = $message_data['total_pages'];

        $utilisateurs = $message->getUtilisateursDisponibles();
        break;

    case 'rapports':
        $rapport_data = [];
        if ($role === 'admin') {
            // L'admin voit TOUS les rapports du système
            $rapport_data = Rapport::listerTousLesRapports($filtre, $recherche, $current_page_reports, $reports_per_page);
        } else {
            // L'encadreur ne voit que les rapports de ses stagiaires
            $rapport_data = Rapport::getRapportsEncadreur($user_id, $filtre, $recherche, $current_page_reports, $reports_per_page);
        }
        $rapports_result_set = $rapport_data['rapports'];
        $total_rapports = $rapport_data['total_rapports'];
        $current_page_reports = $rapport_data['current_page'];
        $limit_reports = $rapport_data['limit'];
        $total_pages_reports = $rapport_data['total_pages'];
        break;

     case 'gestion-stagiaires':
        // La requête de base sélectionne tous les stagiaires et leur encadreur
        // Récupérer les stagiaires avec leurs encadreurs pour l'affichage
        $sql = "SELECT u.id, u.nom, u.prenom, u.email, s.date_debut, s.date_fin,
                       ue.prenom as encadreur_prenom, ue.nom as encadreur_nom,
                       s.encadreur_id -- Important pour le bouton d'affectation
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
            $sql .= (strpos($sql, 'WHERE') === false) ? " WHERE" : " AND";
            $sql .= " (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR ue.nom LIKE ? OR ue.prenom LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= "sssss";
        }
        $sql .= " ORDER BY u.nom, u.prenom";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Error preparing sql (gestion-stagiaires): " . $conn->error); }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $stagiaires = $stmt->get_result();
        $stmt->close();
        
        $liste_encadreurs = Utilisateur::listerEncadreurs(); // Nécessaire pour le modal d'affectation
        break;

     case 'taches':
        if ($role === 'admin') {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            if ($stagiaires_stmt === false) { error_log("Error preparing stagiaires_sql (admin-taches): " . $conn->error); }
        } else {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur WHERE s.encadreur_id = ? ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            if ($stagiaires_stmt === false) { error_log("Error preparing stagiaires_sql (encadreur-taches): " . $conn->error); }
            $stagiaires_stmt->bind_param("i", $user_id);
        }
        $stagiaires_stmt->execute();
        $stagiaires_encadreur = $stagiaires_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stagiaires_stmt->close();

        $tache_data = null;
        if ($role === 'admin') {
            $tache_data = Tache::listerToutesLesTaches($recherche, $current_page_tasks, $tasks_per_page);
        } else {
            $tache_data = $tache->getTachesPourEncadreur($user_id, $recherche, $current_page_tasks, $tasks_per_page);
        }
        
        $taches_result_set = $tache_data['tasks'];
        $total_tasks = $tache_data['total_tasks'];
        $current_page_tasks = $tache_data['current_page'];
        $limit_tasks = $tache_data['limit'];
        $total_pages_tasks = $tache_data['total_pages'];

        $taches_par_jour = [];
        if ($taches_result_set) {
            $temp_tasks_for_grouping = [];
            while($t_temp = $taches_result_set->fetch_assoc()) {
                $temp_tasks_for_grouping[] = $t_temp;
            }
            $taches_result_set->data_seek(0); 
            
            foreach ($temp_tasks_for_grouping as $t) {
                $echeance = $t['date_echeance'];
                if (!isset($taches_par_jour[$echeance])) {
                    $taches_par_jour[$echeance] = [];
                }
                $taches_par_jour[$echeance][] = $t;
            }
        }
        ksort($taches_par_jour);
        break;

    case 'presences':
        $stmt = null;
        if ($role === 'admin') {
             $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur ORDER BY u.nom, u.prenom";
             $stmt = $conn->prepare($stagiaires_sql);
             if ($stmt === false) { error_log("Error preparing stagiaires_sql (admin-presences): " . $conn->error); }
        } else {
             $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur WHERE s.encadreur_id = ? ORDER BY u.nom, u.prenom";
             $stmt = $conn->prepare($stagiaires_sql);
             if ($stmt === false) { error_log("Error preparing stagiaires_sql (encadreur-presences): " . $conn->error); }
             $stmt->bind_param("i", $user_id);
        }
        if ($stmt) {
            $stmt->execute();
            $stagiaires_encadreur = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $stagiaires_encadreur = [];
        }
        break;

   case 'profil':
        $profil_nb_stagiaires = 0;

        if ($role === 'admin') {
            $profil_sql = "SELECT * FROM utilisateurs WHERE id = ?";
            $profil_stmt = $conn->prepare($profil_sql);
            if ($profil_stmt === false) { error_log("Error preparing profil_sql (admin-profil): " . $conn->error); }
            $profil_stmt->bind_param("i", $user_id);
            $profil_stmt->execute();
            $profil = $profil_stmt->get_result()->fetch_assoc();
            $profil_stmt->close();

            $profil['poste'] = 'Administrateur Système';
            $profil['service'] = 'Administration';
            
            $profil_nb_stagiaires = $conn->query("SELECT COUNT(*) FROM stagiaire")->fetch_row()[0] ?? 0;

        } else { // Si c'est un encadreur
            $profil_sql = "SELECT u.*, e.poste, e.service 
                           FROM utilisateurs u 
                           JOIN encadreur e ON u.id = e.id_utilisateur 
                           WHERE u.id = ?";
            $profil_stmt = $conn->prepare($profil_sql);
            if ($profil_stmt === false) { error_log("Error preparing profil_sql (encadreur-profil): " . $conn->error); }
            $profil_stmt->bind_param("i", $user_id);
            $profil_stmt->execute();
            $profil = $profil_stmt->get_result()->fetch_assoc();
            $profil_stmt->close();

            $stmt_stag = $conn->prepare("SELECT COUNT(*) FROM stagiaire WHERE encadreur_id = ?");
            if ($stmt_stag === false) { error_log("Error preparing stmt_stag (profil): " . $conn->error); }
            $stmt_stag->bind_param("i", $user_id);
            $stmt_stag->execute();
            $profil_nb_stagiaires = $stmt_stag->get_result()->fetch_row()[0] ?? 0;
            $stmt_stag->close();
        }
        break;

    case 'gestion-theme':
        $theme_data = null;
        if ($role === 'admin') {
            $theme_data = Theme::listerTousLesThemes($recherche, $current_page_themes, $themes_per_page); // Appel avec pagination
        } else {
            $theme_data = $theme_instance->getThemesByEncadreur($user_id, $recherche, $current_page_themes, $themes_per_page); // Appel avec pagination
        }
        
        $themes_result_set = $theme_data['themes'];
        $total_themes = $theme_data['total_themes'];
        $current_page_themes = $theme_data['current_page'];
        $limit_themes = $theme_data['limit'];
        $total_pages_themes = $theme_data['total_pages'];
        
        // Logique pour peupler la liste des stagiaires dans les modaux d'attribution
        $stagiaires_stmt = null;
        if ($role === 'admin') {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            if ($stagiaires_stmt === false) { error_log("Error preparing stagiaires_sql (admin-themes): " . $conn->error); }
        } else {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur WHERE s.encadreur_id = ? ORDER BY u.nom, u.prenom";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            if ($stagiaires_stmt === false) { error_log("Error preparing stagiaires_sql (encadreur-themes): " . $conn->error); }
            $stagiaires_stmt->bind_param("i", $user_id);
        }
        
        if ($stagiaires_stmt) {
            $stagiaires_stmt->execute();
            $stagiaires_encadreur = $stagiaires_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stagiaires_stmt->close();
        } else {
            $stagiaires_encadreur = [];
        }
        break;

    case 'evaluation':
        $stagiaires_stmt = null;
        if ($role === 'admin') {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur";
            $params = [];
            $types = "";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            if ($stagiaires_stmt === false) { error_log("Error preparing stagiaires_sql (admin-evaluation): " . $conn->error); }
        } else {
            $stagiaires_sql = "SELECT u.id, u.prenom, u.nom FROM utilisateurs u JOIN stagiaire s ON u.id = s.id_utilisateur WHERE s.encadreur_id = ?";
            $params = [$user_id];
            $types = "i";
            $stagiaires_stmt = $conn->prepare($stagiaires_sql);
            if ($stagiaires_stmt === false) { error_log("Error preparing stagiaires_sql (encadreur-evaluation): " . $conn->error); }
        }
        
        if ($stagiaires_stmt && !empty($recherche)) {
            $stagiaires_sql_with_search = $stagiaires_sql . (strpos($stagiaires_sql, 'WHERE') === false ? " WHERE" : " AND") . " (u.nom LIKE ? OR u.prenom LIKE ?)";
            $searchTerm = "%{$recherche}%";
            array_push($params, $searchTerm, $searchTerm);
            $types .= "ss";
            $temp_stmt = $conn->prepare($stagiaires_sql_with_search);
            if ($temp_stmt === false) { error_log("Error preparing stagiaires_sql_with_search (evaluation): " . $conn->error); }
            $stagiaires_stmt->close();
            $stagiaires_stmt = $temp_stmt;
        }
        
        if ($stagiaires_stmt) {
            if(!empty($params)){
                $stagiaires_stmt->bind_param($types, ...$params);
            }
            $stagiaires_stmt->execute();
            $stagiaires_encadreur = $stagiaires_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stagiaires_stmt->close();
        } else {
            $stagiaires_encadreur = [];
        }

        $evaluation_data = null;
        $stagiaire_info = null;
        if (isset($_GET['stagiaire_id'])) {
            $stagiaire_id_eval = intval($_GET['stagiaire_id']);
            
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
            <li class="<?php echo $onglet_actif === 'dashboard' ? 'active' : ''; ?>">
                <a href="?tab=dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
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
                <div class="dashboard-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $stats['nb_stagiaires']; ?></h3>
                                <p>Stagiaires encadrés</p>
                            </div>
                        </div>
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
                                <h3><?php echo $stats['nb_rapports_total']; ?></h3>
                                <p>Rapports reçus</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $stats['nb_rapports_attente']; ?></h3>
                                <p>En attente de validation</p>
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
                        <?php if (isset($messages_result_set) && $messages_result_set->num_rows > 0): ?>
                            <?php while ($msg = $messages_result_set->fetch_assoc()): ?>
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
                                            <div class="message-meta">
                                                <span class="date"><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></span>
                                                
                                            </div>
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

                                        <!-- Contrôles de pagination pour les messages -->
                    <?php if (isset($total_pages_messages) && $total_pages_messages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page_messages > 1): ?>
                                <a href="?tab=messagerie&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p=<?php echo $current_page_messages - 1; ?>" class="page-link">Précédent</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages_messages; $i++): ?>
                                <a href="?tab=messagerie&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p=<?php echo $i; ?>" class="page-link <?php echo ($i == $current_page_messages) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($current_page_messages < $total_pages_messages): ?>
                                <a href="?tab=messagerie&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p=<?php echo $current_page_messages + 1; ?>" class="page-link">Suivant</a>
                            <?php endif; ?>
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
    <?php if (isset($rapports_result_set) && $rapports_result_set->num_rows > 0): ?>
        <?php while ($rpt = $rapports_result_set->fetch_assoc()): ?>
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
                        <button class="btn btn-sm" onclick="voirRapport(<?php echo $rpt['id']; ?>)" title="Consulter">
                            <i class="fas fa-eye"></i> 
                        </button>
                        <a href="telecharger_rapport.php?id=<?= $rpt['id'] ?>" class="btn btn-sm btn-download" title="Télécharger">
                            <i class="fas fa-download"></i> 
                        </a>
                                    <?php if ($rpt['statut'] === 'en attente'): ?>
                        <button class="btn btn-sm btn-success" onclick="validerRapport(<?php echo $rpt['id']; ?>, 'validé')" title="Valider">
                            <i class="fas fa-check"></i>
                            
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="validerRapport(<?php echo $rpt['id']; ?>, 'rejeté')" title="Rejeter">
                            <i class="fas fa-times"></i>
                            
                        </button>
                    <?php endif; ?>
                    <!-- AJOUT DU BOUTON SUPPRIMER POUR L'ADMIN -->
                    <?php if ($role === 'admin'): ?>
                        <button class="btn btn-sm btn-danger" title="Supprimer" onclick="supprimerRapport(event, <?php echo $rpt['id']; ?>, this)">
                            <i class="fas fa-trash"></i> 
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

                    <!-- Contrôles de pagination pour les rapports -->
                    <?php if (isset($total_pages_reports) && $total_pages_reports > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page_reports > 1): ?>
                                <a href="?tab=rapports&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_rpt=<?php echo $current_page_reports - 1; ?>" class="page-link">Précédent</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages_reports; $i++): ?>
                                <a href="?tab=rapports&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_rpt=<?php echo $i; ?>" class="page-link <?php echo ($i == $current_page_reports) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($current_page_reports < $total_pages_reports): ?>
                                <a href="?tab=rapports&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_rpt=<?php echo $current_page_reports + 1; ?>" class="page-link">Suivant</a>
                            <?php endif; ?>
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
        <?php if (isset($taches_par_jour) && !empty($taches_par_jour)): ?>
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
                                            <span><?php echo htmlspecialchars($t['stagiaire_prenom'] . ' ' . $t['stagiaire_nom']); ?></span>
                                        </div>

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
                                        <button class="btn btn-sm btn-info" onclick="voirTache(<?php echo $t['id']; ?>)" title="Consulter">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="modifierTache(<?php echo $t['id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="supprimerTache(<?php echo $t['id']; ?>)" title="Supprimer">
                                            <i class="fas fa-trash"></i>
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
    <!-- Contrôles de pagination pour les tâches -->
    <?php if (isset($total_pages_tasks) && $total_pages_tasks > 1): ?>
        <div class="pagination">
            <?php if ($current_page_tasks > 1): ?>
                <a href="?tab=taches&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_tache=<?php echo $current_page_tasks - 1; ?>" class="page-link">Précédent</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages_tasks; $i++): ?>
                <a href="?tab=taches&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_tache=<?php echo $i; ?>" class="page-link <?php echo ($i == $current_page_tasks) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($current_page_tasks < $total_pages_tasks): ?>
                <a href="?tab=taches&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_tache=<?php echo $current_page_tasks + 1; ?>" class="page-link">Suivant</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
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
                    <select id="stagiaire-select-presence" name="stagiaire_id" onchange="this.form.submit()" class="filter-select">
                        <option value="">-- Choisir un stagiaire --</option>
                        <?php foreach($stagiaires_encadreur as $stag): ?>
                            <option value="<?php echo $stag['id']; ?>" <?php if($stag['id'] == $stagiaire_id_selectionne) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($stag['prenom'] . ' ' . $stag['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
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
                <i class="fas fa-user-tie"></i>
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
                        <span class="info-value"><?php echo $profil['sex'] === 'M' ? 'Masculin' : 'Féminin'; ?></span>
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
                        <span class="info-label">Stagiaires encadrés :</span>
                        <span class="info-value"><?php echo $stats['nb_stagiaires']; ?></span>
                    </div>
                </div>
            </div>

            <div class="profil-card full-width">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h3>Statistiques récentes</h3>
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
                            <span class="stat-number"><?php echo $stats['nb_stagiaires']; ?></span>
                            <span class="stat-label">Stagiaires</span>
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
                                <i class="fas fa-eye"></i> 
                            </button>
                            <?php 
                                // On passe le nom du stagiaire en paramètre pour l'afficher dans le titre de la modale
                                $stagiaireNomComplet = htmlspecialchars($stag['prenom'] . ' ' . $stag['nom'], ENT_QUOTES);
                            ?>
                            <button class="btn btn-sm btn-primary" onclick="ouvrirModalAttribuerAStagiaire(<?php echo $stag['id']; ?>, '<?php echo $stagiaireNomComplet; ?>')">
                                <i class="fas fa-lightbulb"></i> 
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
        <?php if (isset($themes_result_set) && $themes_result_set->num_rows > 0): ?>
            <?php while($th = $themes_result_set->fetch_assoc()): ?>
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
                            <button class="btn btn-sm btn-info" onclick="voirTheme(<?php echo $th['id']; ?>)" title="Consulter">
                                <i class="fas fa-eye"></i> 
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="ouvrirModalAttribuer(<?php echo $th['id']; ?>)" title="Attribuer">
                                <i class="fas fa-user-plus"></i> 
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="ouvrirModalTheme(<?php echo $th['id']; ?>)" title="Modifier">
                                <i class="fas fa-edit"></i> 
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="supprimerTheme(<?php echo $th['id']; ?>)" title="Supprimer">
                                <i class="fas fa-trash"></i> 
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
    <!-- Contrôles de pagination pour les thèmes -->
    <?php if (isset($total_pages_themes) && $total_pages_themes > 1): ?>
        <div class="pagination">
            <?php if ($current_page_themes > 1): ?>
                <a href="?tab=gestion-theme&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_theme=<?php echo $current_page_themes - 1; ?>" class="page-link">Précédent</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages_themes; $i++): ?>
                <a href="?tab=gestion-theme&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_theme=<?php echo $i; ?>" class="page-link <?php echo ($i == $current_page_themes) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($current_page_themes < $total_pages_themes): ?>
                <a href="?tab=gestion-theme&filter=<?php echo htmlspecialchars($filtre); ?>&search=<?php echo htmlspecialchars($recherche); ?>&p_theme=<?php echo $current_page_themes + 1; ?>" class="page-link">Suivant</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>