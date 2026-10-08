<?php
session_start();
require_once 'config.php'; // သင့် database connection ဖိုင်အမည်

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: profile.php");
    exit();
}

$post_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// မိမိပိုင်ဆိုင်သော Post ဟုတ်မဟုတ် စစ်ဆေးပြီးမှ ဖျက်မည်
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
$stmt->execute([$post_id, $user_id]);
$post = $stmt->fetch();

if ($post) {
    // ပုံပါရှိပါက Server ပေါ်မှ ဖျက်မည်
    if (!empty($post['image_path']) && file_exists($post['image_path'])) {
        unlink($post['image_path']);
    }
    
    // Database မှ ဖျက်မည်
    $delete_stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $delete_stmt->execute([$post_id]);
}

header("Location: profile.php");
exit();
?>