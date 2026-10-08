<?php
// api_unread_notifications.php
session_start();
require_once 'config.php';
require_once 'notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

$unread_count = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
echo json_encode(['unread_count' => $unread_count]);