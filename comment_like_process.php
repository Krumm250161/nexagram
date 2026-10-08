<?php
// comment_like_process.php
ob_start();
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'notification_helper.php';

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$comment_id = isset($_GET['comment_id']) ? (int)$_GET['comment_id'] : 0;

if ($comment_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
    exit;
}

try {
    // Check if already liked
    $check_stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $check_stmt->execute([$comment_id, $user_id]);
    $existing = $check_stmt->fetch();

    if ($existing) {
        // Unlike
        $del_stmt = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $del_stmt->execute([$comment_id, $user_id]);
        $is_liked = false;
    } else {
        // Like
        $ins_stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        $ins_stmt->execute([$comment_id, $user_id]);
        $is_liked = true;

        // Trigger notification to comment owner
        $c_stmt = $pdo->prepare("SELECT user_id, post_id FROM comments WHERE id = ?");
        $c_stmt->execute([$comment_id]);
        $comment_data = $c_stmt->fetch();

        if ($comment_data) {
            createNotification($pdo, $comment_data['user_id'], $user_id, 'like_comment', $comment_data['post_id'], $comment_id);
        }
    }

    // Get updated count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
    $count_stmt->execute([$comment_id]);
    $like_count = (int)$count_stmt->fetchColumn();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'is_liked' => $is_liked,
        'like_count' => $like_count
    ]);
    exit;
} catch (\PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}