<?php
// friend_process.php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$target_id = $_POST['target_id'] ?? $_POST['target_user_id'] ?? $_POST['sender_id'] ?? null;
$action = $_POST['action'] ?? '';

if (!$target_id) {
    echo json_encode(['success' => false, 'error' => 'Missing target user ID']);
    exit;
}

try {
    if ($action === 'send' || $action === 'add') {
        // Send a new friend request
        $stmt = $pdo->prepare("SELECT id, status FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->execute([$user_id, $target_id, $target_id, $user_id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$user_id, $target_id]);
            echo json_encode(['success' => true, 'status' => 'requested']);
        } else {
            echo json_encode(['success' => true, 'status' => $existing['status']]);
        }

    } elseif ($action === 'accept') {
        // Accept request
        $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted' WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->execute([$target_id, $user_id]);
        echo json_encode(['success' => true, 'status' => 'accepted']);

    } elseif (in_array($action, ['cancel', 'reject', 'decline', 'delete', 'remove', 'unfriend'])) {
        // Delete pending request or active friendship
        $stmt = $pdo->prepare("DELETE FROM friend_requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $stmt->execute([$user_id, $target_id, $target_id, $user_id]);
        echo json_encode(['success' => true, 'status' => 'removed']);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}