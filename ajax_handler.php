<?php
/**
 * Gestionnaire central pour toutes les requêtes AJAX GET de l'application.
 * Ce fichier ne renvoie que du JSON. Il est la destination unique pour les
 * requêtes qui doivent retourner des données brutes.
 */

// Configuration initiale et en-tête de réponse JSON
header('Content-Type: application/json');
date_default_timezone_set('Africa/Douala');
session_start();

// Inclusion des classes nécessaires
require_once 'classes/Database.php';
require_once 'classes/Presence.php';
require_once 'classes/Message.php';

// Sécurité : Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Non autorisé
    echo json_encode(['error' => 'Utilisateur non connecté.']);
    exit();
}

// Sécurité : Vérifier qu'une action est demandée
if (!isset($_GET['action'])) {
    http_response_code(400); // Mauvaise requête
    echo json_encode(['error' => 'Action non spécifiée.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    switch ($_GET['action']) {

        // --- ACTIONS POUR LE CALENDRIER DE PRÉSENCE ---

        case 'get_presence_events': // Côté Stagiaire
            $annee = intval($_GET['year']);
            $mois = intval($_GET['month']);
            $presence = new Presence();
            $events = $presence->getPresencePourMois($user_id, $annee, $mois);
            echo json_encode($events);
            break;

        case 'get_presence_events_encadreur': // Côté Encadreur
            if ($role !== 'encadreur' && $role !== 'admin') {
                 http_response_code(403); // Interdit
                 echo json_encode(['error' => 'Accès non autorisé pour ce rôle.']);
                 exit();
            }
            $stagiaire_id = intval($_GET['stagiaire_id']);
            $annee = intval($_GET['year']);
            $mois = intval($_GET['month']);
            $presence = new Presence();
            $events = $presence->getPresencePourMois($stagiaire_id, $annee, $mois);
            echo json_encode($events);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500); // Erreur serveur
    echo json_encode(['error' => 'Une erreur interne est survenue: ' . $e->getMessage()]);
}

exit();