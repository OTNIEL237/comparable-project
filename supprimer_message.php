<?php
require_once 'classes/Database.php';
require_once 'classes/Message.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$messageId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

$message = new Message($userId);
$success = $message->supprimerMessage($messageId);

header('Content-Type: application/json');
echo json_encode(['success' => $success]);