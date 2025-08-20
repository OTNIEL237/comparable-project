<?php
/**
 * Ce script gère le téléchargement sécurisé d'un rapport PDF.
 * Il vérifie les permissions avant de servir le fichier.
 */

session_start();
require_once 'classes/Database.php';
require_once 'classes/Rapport.php';

// --- 1. Sécurité et Validation ---

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID du rapport est fourni et est un nombre
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Erreur : ID de rapport non spécifié ou invalide.");
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$rapport_id = (int)$_GET['id'];

try {
    // --- 2. Récupération du rapport et Vérification des Droits ---

    // On utilise notre méthode statique sécurisée pour récupérer le rapport
    $rapport = Rapport::getRapportById($rapport_id, $user_id, $role);

    // Si la méthode renvoie null, c'est que le rapport n'existe pas OU que l'utilisateur n'a pas le droit de le voir
    if (!$rapport) {
        throw new Exception("Rapport non trouvé ou vous n'avez pas la permission de le télécharger.");
    }

    // --- 3. Vérification de l'existence du fichier PDF ---

    if (empty($rapport['fichier_pdf'])) {
        throw new Exception("Aucun fichier PDF n'est associé à ce rapport.");
    }

    $chemin_pdf = __DIR__ . '/uploads/rapports/' . basename($rapport['fichier_pdf']); // basename() pour la sécurité

    if (!file_exists($chemin_pdf)) {
        error_log("Fichier PDF manquant pour le rapport ID {$rapport_id}: {$chemin_pdf}");
        throw new Exception("Le fichier PDF n'a pas été trouvé sur le serveur. Veuillez contacter un administrateur.");
    }

    // --- 4. Lancement du Téléchargement ---

    // Headers pour forcer le téléchargement et éviter les problèmes de cache
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($chemin_pdf) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($chemin_pdf));
    
    // Nettoyer le buffer de sortie pour éviter toute corruption de fichier
    ob_clean();
    flush();
    
    // Lire et envoyer le fichier au navigateur
    readfile($chemin_pdf);
    
    exit();

} catch (Exception $e) {
    // En cas d'erreur (droits, fichier manquant...), on redirige avec un message clair
    error_log("Erreur téléchargement PDF: " . $e->getMessage());
    
    // Déterminer la page de redirection en fonction du rôle
    $dashboard_page = ($role === 'stagiaire') ? 'dashboardStagiaire.php' : 'dashboardEncadreur.php';
    
    // Rediriger vers le dashboard approprié avec un message d'erreur
    header("Location: {$dashboard_page}?tab=rapports&error=" . urlencode($e->getMessage()));
    exit();
}