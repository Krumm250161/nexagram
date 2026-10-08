<?php
// comment_process.php
ob_start();
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'notification_helper.php';

// 1. Verify user authentication
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

// 2. Validate required inputs
if ($post_id <= 0 || empty($comment_text)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // 3. Insert comment into database
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, parent_id, comment_text, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$post_id, $user_id, $parent_id, $comment_text]);
    $comment_id = $pdo->lastInsertId();

    // 4. Trigger notifications
    if (!empty($parent_id)) {
        // Trigger notification to the author of the comment being replied to
        $c_stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $c_stmt->execute([$parent_id]);
        $parent_user_id = $c_stmt->fetchColumn();

        if ($parent_user_id) {
            createNotification($pdo, $parent_user_id, $user_id, 'reply_comment', $post_id, $parent_id);
        }
    } else {
        // Trigger notification to post author
        $p_stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $p_stmt->execute([$post_id]);
        $post_owner_id = $p_stmt->fetchColumn();

        if ($post_owner_id) {
            createNotification($pdo, $post_owner_id, $user_id, 'comment_post', $post_id, $comment_id);
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id
    ]);
    exit;
} catch (\PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}