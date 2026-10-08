<?php
// notification_helper.php
require_once 'config.php';

/**
 * Creates a notification if the actor is not the owner.
 */
function createNotification($pdo, $recipient_id, $actor_id, $type, $post_id = null, $comment_id = null) {
    // Prevent notifying users of their own actions
    if ((int)$recipient_id === (int)$actor_id) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, post_id, comment_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$recipient_id, $actor_id, $type, $post_id, $comment_id]);
    } catch (\PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets total count of unread notifications for a user.
 */
function getUnreadNotificationCount($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        return 0;
    }
}