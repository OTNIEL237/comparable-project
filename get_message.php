<?php
session_start();

require_once 'classes/Database.php';
require_once 'classes/Message.php';

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérification de l'ID du message
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de message invalide']);
    exit();
}

$user_id = $_SESSION['user_id'];
$message_id = (int)$_GET['id'];

try {
    $message = new Message($user_id);
    $messageDetails = $message->getMessageById($message_id);
    
    if (!$messageDetails) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Message non trouvé']);
        exit();
    }
    
    // Vérifier que l'utilisateur a accès à ce message
    if ($messageDetails['expediteur_id'] != $user_id && $messageDetails['destinataire_id'] != $user_id) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Accès refusé']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode($messageDetails);
    
} catch (Exception $e) {
    error_log("Erreur get_message.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}