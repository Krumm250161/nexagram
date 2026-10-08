<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$follower_id = $_SESSION['user_id'];
$following_id = (int)($_POST['target_user_id'] ?? $_POST['target_id'] ?? 0);

if (!$following_id || $follower_id === $following_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid target ID']);
    exit;
}

// Check if already following
$check = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
$check->execute([$follower_id, $following_id]);

if ($check->fetch()) {
    // Unfollow
    $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$follower_id, $following_id]);
    echo json_encode(['status' => 'unfollowed']);
} else {
    // Follow
    $stmt = $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
    $stmt->execute([$follower_id, $following_id]);
    echo json_encode(['status' => 'followed']);
}