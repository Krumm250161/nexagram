<?php
// index.php - Nexagram Feed with Multi-Media Grid, Lightbox, Comment Likes & Notifications
session_start();
require_once 'config.php';
require_once 'lang.php'; // Included language support

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'] ?? 'User';

// Get current user's profile image
$user_stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
$user_stmt->execute([$current_user_id]);
$current_user_data = $user_stmt->fetch();

function resolveImagePath($fileName)
{
    if (empty($fileName)) return null;
    if (strpos($fileName, 'http') === 0) return $fileName;

    $cleanName = ltrim($fileName, '/');
    if (file_exists($cleanName)) return $cleanName;
    if (file_exists('uploads/' . $cleanName)) return 'uploads/' . $cleanName;

    return (strpos($cleanName, 'uploads/') === 0) ? $cleanName : 'uploads/' . $cleanName;
}

$current_user_avatar = resolveImagePath($current_user_data['profile_image'] ?? null);

// Fetch Posts with Public, Follower-Only, and Friend-Only Privacy Rules
// Fetch Posts with Public, Follower-Only, and Friend-Only Privacy Rules
try {
    $stmt = $pdo->prepare("
        SELECT posts.*, users.username, users.profile_image,
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as like_count,
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :user_id1) as is_liked,
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comment_count
        FROM posts 
        JOIN users ON posts.user_id = users.id 
        WHERE 
            -- 1. Viewer's own posts
            posts.user_id = :user_id2
            
            -- 2. Public posts
            OR posts.visibility = 'public'
            
            -- 3. Followers-Only posts (Viewer follows author)
            OR (
                posts.visibility = 'followers' 
                AND EXISTS (
                    SELECT 1 FROM follows 
                    WHERE follower_id = :user_id3 
                      AND following_id = posts.user_id
                )
            )
            
            -- 4. Friends-Only posts (Accepted friend request in friend_requests table)
            OR (
                posts.visibility = 'friends' 
                AND EXISTS (
                    SELECT 1 FROM friend_requests 
                    WHERE ((sender_id = :user_id4 AND receiver_id = posts.user_id)
                       OR  (sender_id = posts.user_id AND receiver_id = :user_id5))
                      AND status = 'accepted'
                )
            )
        ORDER BY posts.created_at DESC
    ");

    $stmt->execute([
        'user_id1' => $current_user_id,
        'user_id2' => $current_user_id,
        'user_id3' => $current_user_id,
        'user_id4' => $current_user_id,
        'user_id5' => $current_user_id
    ]);

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • <?php echo __('nav_home'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        const storedTheme = localStorage.getItem("theme") || "dark";
        document.documentElement.setAttribute("data-theme", storedTheme);
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --bg-color: #0b0f17;
            --card-bg: rgba(23, 29, 45, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --sidebar-bg: #111827;
            --hover-bg: rgba(255, 255, 255, 0.06);
            --accent-gradient: linear-gradient(135deg, #ec4899, #8b5cf6, #3b82f6);
            --glass-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.45);
            --comment-bubble-bg: rgba(30, 41, 59, 0.7);
            --overlay-bg: rgba(0, 0, 0, 0.65);
            --avatar-bg: #1e293b;
        }

        [data-theme="light"] {
            --bg-color: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.88);
            --card-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-sub: #64748b;
            --sidebar-bg: #ffffff;
            --hover-bg: rgba(0, 0, 0, 0.05);
            --glass-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.08);
            --comment-bubble-bg: #f1f5f9;
            --overlay-bg: rgba(15, 23, 42, 0.4);
            --avatar-bg: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Navigation */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 250px;
            border-right: 1px solid var(--card-border);
            padding: 30px 18px;
            display: flex;
            flex-direction: column;
            background-color: var(--sidebar-bg);
            z-index: 100;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
            padding-left: 10px;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-grow: 1;
        }

        .nav-item a {
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            position: relative;
        }

        .nav-item:hover a,
        .nav-item.active a {
            background-color: var(--hover-bg);
        }

        .notif-badge {
            background: #ec4899;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: auto;
            display: inline-block;
        }

        /* Sidebar Bottom Container */
        .sidebar-bottom {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Language Dropdown Container */
        .lang-dropdown-container {
            position: relative;
            width: 100%;
        }

        .lang-dropdown-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            color: var(--text-main);
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .lang-dropdown-btn:hover {
            background: var(--hover-bg);
        }

        .lang-dropdown-btn .arrow-icon {
            margin-left: auto;
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .lang-dropdown-container.open .arrow-icon {
            transform: rotate(180deg);
        }

        /* Language Options Popup Menu */
        .lang-dropdown-menu {
            display: none;
            flex-direction: column;
            gap: 4px;
            background: rgba(23, 29, 45, 0.95);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 6px;
            margin-bottom: 8px;
            backdrop-filter: blur(12px);
        }

        .lang-dropdown-container.open .lang-dropdown-menu {
            display: flex;
        }

        .lang-option {
            display: block;
            padding: 10px 14px;
            border-radius: 10px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .lang-option:hover,
        .lang-option.active {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar Action Buttons (Darkmode / Logout) */
        .sidebar-action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            transition: background 0.2s;
            background: transparent;
            border: none;
            cursor: pointer;
            width: 100%;
        }

        .sidebar-action-btn:hover {
            background: var(--hover-bg);
        }

        .logout-btn {
            color: #ef4444;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }

        .feed-container {
            width: 100%;
            max-width: 580px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .post-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
        }

        .post-header {
            display: flex;
            align-items: center;
            padding: 18px 20px;
        }

        .profile-img-avatar,
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .avatar-circle,
        .comment-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--avatar-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            border: 1px solid var(--card-border);
            flex-shrink: 0;
        }

        .header-info {
            display: flex;
            flex-direction: column;
            margin-left: 12px;
        }

        .username {
            font-weight: 700;
            font-size: 15.5px;
        }

        .post-time-top {
            font-size: 12.5px;
            color: var(--text-sub);
        }

        .post-info {
            padding: 0 20px 16px 20px;
        }

        .post-caption {
            font-size: 15px;
            line-height: 1.55;
            white-space: pre-wrap;
        }

        /* Multi-Image / Media Grid Styles */
        .post-media-grid {
            display: grid;
            gap: 3px;
            width: 100%;
            max-height: 520px;
            overflow: hidden;
            background: #000;
        }

        .post-media-grid.count-1 {
            grid-template-columns: 1fr;
        }

        .post-media-grid.count-2 {
            grid-template-columns: 1fr 1fr;
        }

        .post-media-grid.count-3 {
            grid-template-columns: 2fr 1fr;
            grid-template-rows: 1fr 1fr;
        }

        .post-media-grid.count-3 .media-item-box:first-child {
            grid-row: span 2;
        }

        .post-media-grid.count-4 {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }

        .media-item-box {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 250px;
            cursor: pointer;
            overflow: hidden;
        }

        .post-media-grid.count-1 .media-item-box {
            min-height: auto;
            max-height: 580px;
        }

        .post-media-element {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.2s ease;
        }

        .media-item-box:hover .post-media-element {
            transform: scale(1.02);
        }

        .media-overlay-more {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .counts-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 20px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-sub);
            border-bottom: 1px solid var(--card-border);
        }

        .action-bar {
            display: flex;
            padding: 6px 12px;
        }

        .action-button {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 10px;
            font-size: 14.5px;
            font-weight: 700;
            color: var(--text-sub);
            border-radius: 12px;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
        }

        .action-button:hover {
            background-color: var(--hover-bg);
            color: var(--text-main);
        }

        .action-button.liked {
            color: #ec4899;
        }

        .action-button.liked i {
            color: #ec4899;
        }

        /* Lightbox Modal */
        .lightbox-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.95);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .lightbox-modal.active {
            display: flex;
        }

        .lightbox-content {
            max-width: 90vw;
            max-height: 90vh;
            object-fit: contain;
        }

        .lightbox-close,
        .lightbox-prev,
        .lightbox-next {
            position: absolute;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            user-select: none;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-close:hover,
        .lightbox-prev:hover,
        .lightbox-next:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .lightbox-close {
            top: 20px;
            right: 25px;
        }

        .lightbox-prev {
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .lightbox-next {
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Comment Modal Styles */
        .comment-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: var(--overlay-bg);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .comment-sheet {
            position: fixed;
            bottom: -100%;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 600px;
            height: 75vh;
            background: var(--sidebar-bg);
            border-radius: 24px 24px 0 0;
            box-shadow: var(--glass-shadow);
            z-index: 1001;
            display: flex;
            flex-direction: column;
            transition: bottom 0.3s ease;
        }

        .comment-sheet.active {
            bottom: 0;
        }

        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px solid var(--card-border);
            font-weight: 700;
        }

        .sheet-close-btn {
            cursor: pointer;
            font-size: 18px;
            color: var(--text-sub);
        }

        .sheet-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .panel-comment-item {
            display: flex;
            gap: 12px;
        }

        .panel-comment-item.reply-item {
            margin-left: 48px;
        }

        .comment-content-wrapper {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-width: 85%;
        }

        .panel-comment-bubble {
            background: var(--comment-bubble-bg);
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 14px;
        }

        .panel-comment-user {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .comment-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 12px;
            color: var(--text-sub);
            padding-left: 4px;
        }

        .comment-action-btn {
            cursor: pointer;
            font-weight: 600;
        }

        .comment-action-btn:hover {
            color: var(--text-main);
        }

        .comment-like-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-sub);
            transition: color 0.2s;
        }

        .comment-like-btn.liked {
            color: #ec4899;
        }

        .comment-like-btn:hover {
            color: #ec4899;
        }

        .reply-target-tag {
            display: none;
            align-items: center;
            justify-content: space-between;
            background: var(--hover-bg);
            padding: 6px 16px;
            font-size: 12.5px;
            color: var(--text-sub);
        }

        .sheet-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--card-border);
        }

        .comment-form {
            display: flex;
            gap: 10px;
        }

        .comment-input {
            flex: 1;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 10px 16px;
            color: var(--text-main);
            outline: none;
        }

        .comment-send-btn {
            background: var(--accent-gradient);
            border: none;
            color: #fff;
            padding: 0 18px;
            border-radius: 20px;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <!-- Sidebar Component -->
    <nav class="sidebar">
        <div class="logo">Nexagram</div>
        <ul class="nav-menu">
            <li class="nav-item active"><a href="index.php"><i class="fa-solid fa-house"></i> <?php echo __('nav_home'); ?></a></li>
            <li class="nav-item">
                <a href="notifications.php">
                    <i class="fa-solid fa-bell"></i> Notifications
                    <span class="notif-badge" id="navNotifBadge" style="display:none;">0</span>
                </a>
            </li>
            <li class="nav-item"><a href="friends.php"><i class="fa-solid fa-user-group"></i> <?php echo __('nav_friends'); ?></a></li>
            <li class="nav-item"><a href="users.php"><i class="fa-solid fa-users"></i> <?php echo __('nav_users'); ?></a></li>
            <li class="nav-item"><a href="#"><i class="fa-solid fa-paper-plane"></i> <?php echo __('nav_messages'); ?></a></li>
            <li class="nav-item"><a href="create_post.php"><i class="fa-solid fa-square-plus"></i> <?php echo __('nav_create'); ?></a></li>
            <li class="nav-item"><a href="profile.php"><i class="fa-solid fa-user"></i> <?php echo __('nav_profile'); ?></a></li>
        </ul>

        <!-- Bottom Controls & Language Switcher Dropdown -->
        <div class="sidebar-bottom">
            <div class="lang-dropdown-container" id="langDropdownContainer">
                <!-- Dropdown Popup Options -->
                <div class="lang-dropdown-menu" id="langDropdownMenu">
                    <a href="?lang=ja" class="lang-option <?php echo ($current_lang ?? '') === 'ja' ? 'active' : ''; ?>">
                        JP 日本語
                    </a>
                    <a href="?lang=en" class="lang-option <?php echo ($current_lang ?? '') === 'en' ? 'active' : ''; ?>">
                        EN English
                    </a>
                </div>

                <!-- Dropdown Button Bar -->
                <button class="lang-dropdown-btn" id="langDropdownToggle" type="button">
                    <i class="fa-solid fa-globe"></i>
                    <span><?php echo ($current_lang ?? '') === 'ja' ? 'JP 日本語' : 'EN English'; ?></span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </button>
            </div>

            <!-- Dark Mode Toggle Button -->
            <button type="button" class="sidebar-action-btn" id="theme-toggle">
                <i class="fa-solid fa-moon" id="theme-icon"></i>
                <span id="theme-text"><?php echo __('dark_mode'); ?></span>
            </button>

            <!-- Logout Link -->
            <a href="logout.php" class="sidebar-action-btn logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?php echo __('nav_logout'); ?></span>
            </a>
        </div>
    </nav>

    <main class="main-content">
        <div class="feed-container">
            <?php foreach ($posts as $post): ?>
                <?php
                $user_avatar = resolveImagePath($post['profile_image'] ?? null);

                // Fetch media from post_media table
                $media_items = [];
                try {
                    $m_stmt = $pdo->prepare("SELECT media_path FROM post_media WHERE post_id = ? ORDER BY id ASC");
                    $m_stmt->execute([$post['id']]);
                    $fetched_media = $m_stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($fetched_media as $m) {
                        $resolved = resolveImagePath($m);
                        if ($resolved) {
                            $media_items[] = $resolved;
                        }
                    }
                } catch (\PDOException $e) {
                    // Fallback if table doesn't exist yet
                }

                // Fallback to legacy posts.image_path if post_media is empty
                if (empty($media_items)) {
                    $raw_file = $post['image_path'] ?? $post['image_url'] ?? $post['image'] ?? $post['file_path'] ?? $post['media_url'] ?? null;
                    $single_media = resolveImagePath($raw_file);
                    if (!empty($single_media)) {
                        $media_items[] = $single_media;
                    }
                }

                $total_media = count($media_items);
                $display_count = min($total_media, 4);
                $media_json = htmlspecialchars(json_encode($media_items), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="post-card" id="post_<?php echo $post['id']; ?>">
                    <div class="post-header">
                        <a href="profile.php?id=<?php echo $post['user_id']; ?>" style="text-decoration:none;">
                            <?php if (!empty($user_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="profile-img-avatar">
                            <?php else: ?>
                                <div class="avatar-circle"><?php echo mb_substr(htmlspecialchars($post['username']), 0, 1, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </a>

                        <div class="header-info">
                            <a href="profile.php?id=<?php echo $post['user_id']; ?>" style="text-decoration:none; color:inherit;">
                                <span class="username"><?php echo htmlspecialchars($post['username']); ?></span>
                            </a>
                            <span class="post-time-top"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($post['caption'])): ?>
                        <div class="post-info">
                            <div class="post-caption"><?php echo htmlspecialchars($post['caption']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($media_items)): ?>
                        <div class="post-media-grid count-<?php echo $display_count; ?>">
                            <?php for ($i = 0; $i < $display_count; $i++): ?>
                                <?php
                                $media_src = $media_items[$i];
                                $ext = strtolower(pathinfo($media_src, PATHINFO_EXTENSION));
                                $is_last = ($i === 3 && $total_media > 4);
                                $more_count = $total_media - 4;
                                ?>
                                <div class="media-item-box" onclick="openLightbox(<?php echo $media_json; ?>, <?php echo $i; ?>)">
                                    <?php if (in_array($ext, ['mp4', 'webm', 'mov'])): ?>
                                        <video src="<?php echo htmlspecialchars($media_src); ?>" class="post-media-element"></video>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($media_src); ?>" class="post-media-element" alt="Post photo">
                                    <?php endif; ?>

                                    <?php if ($is_last): ?>
                                        <div class="media-overlay-more">+<?php echo $more_count; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <div class="counts-row">
                        <div class="likes-count-display">
                            <i class="fa-solid fa-heart" style="color: #ec4899;"></i>
                            <span class="like-count-num"><?php echo (int)$post['like_count']; ?></span> <?php echo __('likes_label'); ?>
                        </div>
                        <div class="comments-count-display">
                            <span class="comment-count-text"><?php echo (int)$post['comment_count']; ?> <?php echo __('comments_label'); ?></span>
                        </div>
                    </div>

                    <div class="action-bar">
                        <div class="action-button like-btn <?php echo $post['is_liked'] ? 'liked' : ''; ?>" data-post-id="<?php echo $post['id']; ?>">
                            <i class="<?php echo $post['is_liked'] ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; ?> action-icon"></i>
                            <span><?php echo __('like_button'); ?></span>
                        </div>
                        <div class="action-button comment-btn" onclick="openCommentSheet(<?php echo $post['id']; ?>)">
                            <i class="fa-regular fa-comment action-icon"></i>
                            <span><?php echo __('comment_button'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Lightbox Modal Viewer -->
    <div class="lightbox-modal" id="lightboxModal">
        <span class="lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></span>
        <span class="lightbox-prev" onclick="changeLightboxMedia(-1)"><i class="fa-solid fa-chevron-left"></i></span>
        <span class="lightbox-next" onclick="changeLightboxMedia(1)"><i class="fa-solid fa-chevron-right"></i></span>
        <div id="lightboxContainer"></div>
    </div>

    <!-- COMMENT MODAL STRUCTURE -->
    <div class="comment-overlay" id="commentOverlay" onclick="closeCommentSheet()"></div>
    <div class="comment-sheet" id="commentSheet">
        <div class="sheet-header">
            <span><?php echo __('comments_title'); ?></span>
            <i class="fa-solid fa-xmark sheet-close-btn" onclick="closeCommentSheet()"></i>
        </div>
        <div class="sheet-body" id="sheetBody"></div>
        <div class="reply-target-tag" id="replyTargetTag">
            <span><?php echo __('reply_to'); ?>: <strong id="replyTargetUser"></strong></span>
            <i class="fa-solid fa-xmark" style="cursor:pointer;" onclick="cancelReply()"></i>
        </div>
        <div class="sheet-footer">
            <form class="comment-form" id="commentForm">
                <input type="hidden" id="sheetPostId" value="">
                <input type="hidden" id="sheetParentId" value="">
                <input type="text" class="comment-input" id="commentInput" placeholder="<?php echo __('add_comment_placeholder'); ?>" required autocomplete="off">
                <button type="submit" class="comment-send-btn"><?php echo __('post_comment_btn'); ?></button>
            </form>
        </div>
    </div>

    <script>
        // Language Dropdown Toggle Handler
        const langToggleBtn = document.getElementById('langDropdownToggle');
        const langContainer = document.getElementById('langDropdownContainer');

        langToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            langContainer.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (!langContainer.contains(e.target)) {
                langContainer.classList.remove('open');
            }
        });

        // JS Translations
        const txtReply = "<?php echo __('btn_reply'); ?>";
        const txtNoComments = "<?php echo __('no_comments'); ?>";
        const txtCommentsLabel = "<?php echo __('comments_label'); ?>";
        const darkTxt = "<?php echo __('dark_mode'); ?>";
        const lightTxt = "<?php echo __('light_mode'); ?>";

        // Theme Toggle
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');

        function updateThemeUI(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);

            if (theme === 'dark') {
                if (themeIcon) themeIcon.className = 'fa-solid fa-moon';
                if (themeText) themeText.textContent = darkTxt;
            } else {
                if (themeIcon) themeIcon.className = 'fa-solid fa-sun';
                if (themeText) themeText.textContent = lightTxt;
            }
        }

        const initialTheme = localStorage.getItem('theme') || 'dark';
        updateThemeUI(initialTheme);

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                updateThemeUI(newTheme);
            });
        }

        // Real-Time Unread Notification Badge Updater
        function updateNotifBadge() {
            fetch('api_unread_notifications.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('navNotifBadge');
                    if (badge) {
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(err => console.error('Notification Badge Error:', err));
        }

        updateNotifBadge();
        setInterval(updateNotifBadge, 8000); // Poll every 8 seconds

        // Lightbox Handler
        let currentLightboxItems = [];
        let currentLightboxIndex = 0;

        function openLightbox(items, index) {
            currentLightboxItems = items;
            currentLightboxIndex = index;
            renderLightboxItem();
            document.getElementById('lightboxModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightboxModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function renderLightboxItem() {
            const container = document.getElementById('lightboxContainer');
            const src = currentLightboxItems[currentLightboxIndex];
            const ext = src.split('.').pop().toLowerCase();

            if (['mp4', 'webm', 'mov'].includes(ext)) {
                container.innerHTML = `<video src="${src}" controls autoplay class="lightbox-content"></video>`;
            } else {
                container.innerHTML = `<img src="${src}" class="lightbox-content">`;
            }
        }

        function changeLightboxMedia(direction) {
            currentLightboxIndex += direction;
            if (currentLightboxIndex < 0) {
                currentLightboxIndex = currentLightboxItems.length - 1;
            } else if (currentLightboxIndex >= currentLightboxItems.length) {
                currentLightboxIndex = 0;
            }
            renderLightboxItem();
        }

        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('lightboxModal');
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') changeLightboxMedia(-1);
            if (e.key === 'ArrowRight') changeLightboxMedia(1);
        });

        // Post Like Handling
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                const postCard = document.getElementById('post_' + postId);
                const likeCountNum = postCard.querySelector('.like-count-num');
                const likeIcon = this.querySelector('.action-icon');

                fetch('like_process.php?post_id=' + postId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            likeCountNum.textContent = data.like_count;
                            if (data.is_liked) {
                                this.classList.add('liked');
                                likeIcon.className = 'fa-solid fa-heart action-icon';
                            } else {
                                this.classList.remove('liked');
                                likeIcon.className = 'fa-regular fa-heart action-icon';
                            }
                        } else {
                            alert('Error: ' + (data.message || 'Action failed.'));
                        }
                    })
                    .catch(err => {
                        console.error('Like error:', err);
                    });
            });
        });

        // Load Comments & Comment Likes from Database
        const currentUsername = <?php echo json_encode($current_username); ?>;
        const currentUserAvatar = <?php echo json_encode($current_user_avatar); ?>;

        <?php
        $encoded_data = [];
        foreach ($posts as $p) {
            $c_stmt = $pdo->prepare("SELECT comments.*, users.username, users.profile_image,
                                     (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id) as like_count,
                                     (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id AND comment_likes.user_id = ?) as is_liked
                                     FROM comments 
                                     JOIN users ON comments.user_id = users.id 
                                     WHERE post_id = ? 
                                     ORDER BY comments.id ASC");
            $c_stmt->execute([$current_user_id, $p['id']]);
            $comments = $c_stmt->fetchAll();

            $encoded_data[$p['id']] = [];
            foreach ($comments as $c) {
                $encoded_data[$p['id']][] = [
                    'id' => (int)$c['id'],
                    'parent_id' => $c['parent_id'] ? (int)$c['parent_id'] : null,
                    'username' => htmlspecialchars($c['username']),
                    'avatar' => resolveImagePath($c['profile_image'] ?? null),
                    'text' => htmlspecialchars($c['comment_text']),
                    'like_count' => (int)$c['like_count'],
                    'is_liked' => (bool)$c['is_liked']
                ];
            }
        }
        ?>
        const postsCommentsData = <?php echo json_encode($encoded_data, JSON_UNESCAPED_UNICODE); ?>;

        const overlay = document.getElementById('commentOverlay');
        const sheet = document.getElementById('commentSheet');
        const sheetBody = document.getElementById('sheetBody');
        const sheetPostIdInput = document.getElementById('sheetPostId');
        const sheetParentIdInput = document.getElementById('sheetParentId');
        const replyTargetTag = document.getElementById('replyTargetTag');
        const replyTargetUser = document.getElementById('replyTargetUser');
        const commentForm = document.getElementById('commentForm');
        const commentInput = document.getElementById('commentInput');

        function openCommentSheet(postId) {
            sheetPostIdInput.value = postId;
            cancelReply();
            renderComments(postId);

            overlay.style.display = 'block';
            setTimeout(() => overlay.style.opacity = '1', 10);
            sheet.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCommentSheet() {
            overlay.style.opacity = '0';
            sheet.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => overlay.style.display = 'none', 300);
        }

        function renderComments(postId) {
            sheetBody.innerHTML = '';
            const comments = postsCommentsData[postId] || [];

            if (comments.length === 0) {
                sheetBody.innerHTML = `<div style="text-align:center; padding: 40px; color: var(--text-sub);">${txtNoComments}</div>`;
                return;
            }

            const parents = comments.filter(c => !c.parent_id);
            const replies = comments.filter(c => c.parent_id);

            parents.forEach(parent => {
                appendCommentNode(parent, false);
                const childReplies = replies.filter(r => r.parent_id == parent.id);
                childReplies.forEach(reply => {
                    appendCommentNode(reply, true);
                });
            });
        }

        function appendCommentNode(comment, isReply) {
            const item = document.createElement('div');
            item.className = `panel-comment-item ${isReply ? 'reply-item' : ''}`;
            item.id = `comment_node_${comment.id}`;

            const avatarHTML = comment.avatar ?
                `<img src="${comment.avatar}" class="comment-avatar">` :
                `<div class="comment-avatar-circle">${comment.username.charAt(0)}</div>`;

            const heartClass = comment.is_liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
            const likedClass = comment.is_liked ? 'liked' : '';

            item.innerHTML = `
                ${avatarHTML}
                <div class="comment-content-wrapper">
                    <div class="panel-comment-bubble">
                        <div class="panel-comment-user">${comment.username}</div>
                        <div class="panel-comment-text">${comment.text}</div>
                    </div>
                    <div class="comment-actions">
                        <span class="comment-action-btn" onclick="setReplyTarget(${comment.id}, '${comment.username}')">${txtReply}</span>
                        <span class="comment-like-btn ${likedClass}" id="comment_like_btn_${comment.id}" onclick="toggleCommentLike(${comment.id})">
                            <i class="${heartClass}" id="comment_like_icon_${comment.id}"></i>
                            <span id="comment_like_count_${comment.id}">${comment.like_count > 0 ? comment.like_count : ''}</span>
                        </span>
                    </div>
                </div>
            `;
            sheetBody.appendChild(item);
        }

        function toggleCommentLike(commentId) {
            fetch('comment_like_process.php?comment_id=' + commentId)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const btn = document.getElementById('comment_like_btn_' + commentId);
                        const icon = document.getElementById('comment_like_icon_' + commentId);
                        const countSpan = document.getElementById('comment_like_count_' + commentId);

                        if (data.is_liked) {
                            btn.classList.add('liked');
                            icon.className = 'fa-solid fa-heart';
                        } else {
                            btn.classList.remove('liked');
                            icon.className = 'fa-regular fa-heart';
                        }

                        countSpan.innerText = data.like_count > 0 ? data.like_count : '';

                        const postId = sheetPostIdInput.value;
                        if (postsCommentsData[postId]) {
                            const found = postsCommentsData[postId].find(c => c.id == commentId);
                            if (found) {
                                found.is_liked = data.is_liked;
                                found.like_count = data.like_count;
                            }
                        }
                    } else {
                        alert('Error: ' + (data.message || ''));
                    }
                })
                .catch(err => {
                    console.error('Comment like error:', err);
                });
        }

        function setReplyTarget(parentId, username) {
            sheetParentIdInput.value = parentId;
            replyTargetUser.innerText = username;
            replyTargetTag.style.display = 'flex';
            commentInput.focus();
        }

        function cancelReply() {
            sheetParentIdInput.value = '';
            replyTargetTag.style.display = 'none';
        }

        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const postId = sheetPostIdInput.value;
            const parentId = sheetParentIdInput.value;
            const text = commentInput.value.trim();
            if (!text) return;

            const formData = new FormData();
            formData.append('post_id', postId);
            if (parentId) {
                formData.append('parent_id', parentId);
            }
            formData.append('comment_text', text);

            fetch('comment_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const newComment = {
                            id: data.comment_id,
                            parent_id: parentId ? parseInt(parentId) : null,
                            username: currentUsername,
                            avatar: currentUserAvatar,
                            text: text,
                            like_count: 0,
                            is_liked: false
                        };

                        if (!postsCommentsData[postId]) {
                            postsCommentsData[postId] = [];
                        }

                        postsCommentsData[postId].push(newComment);

                        renderComments(postId);
                        commentInput.value = '';
                        cancelReply();

                        const postCard = document.getElementById('post_' + postId);
                        if (postCard) {
                            const countSpan = postCard.querySelector('.comment-count-text');
                            if (countSpan) {
                                countSpan.innerText = `${postsCommentsData[postId].length} ${txtCommentsLabel}`;
                            }
                        }

                        sheetBody.scrollTop = sheetBody.scrollHeight;
                    } else {
                        alert('Failed: ' + (data.message || ''));
                    }
                })
                .catch(err => {
                    console.error('Error adding comment:', err);
                });
        });
    </script>
</body>

</html>