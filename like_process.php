<?php
// likeprocess.php
ob_start();
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'notification_helper.php';

// Ensure user is logged in and post_id is provided
if (!isset($_SESSION['user_id']) || !isset($_GET['post_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized or missing post ID.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)$_GET['post_id'];

try {
    // 1. Check if post exists
    $post_check = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ?");
    $post_check->execute([$post_id]);
    $post = $post_check->fetch();

    if (!$post) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Post not found.']);
        exit;
    }

    // 2. Check if already liked
    $check_stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $check_stmt->execute([$user_id, $post_id]);
    $existing_like = $check_stmt->fetch();

    if ($existing_like) {
        // Remove like
        $delete_stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $delete_stmt->execute([$user_id, $post_id]);
        $is_liked = false;
    } else {
        // Add like
        $insert_stmt = $pdo->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $insert_stmt->execute([$user_id, $post_id]);
        $is_liked = true;

        // Trigger notification to the post owner
        if ($post['user_id']) {
            createNotification($pdo, $post['user_id'], $user_id, 'like_post', $post_id);
        }
    }

    // 3. Recalculate total likes
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
    $count_stmt->execute([$post_id]);
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