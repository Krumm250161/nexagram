<?php
// notifications.php
session_start();
require_once 'config.php';
require_once 'lang.php';
require_once 'notification_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Handle "Mark all as read" action
if (isset($_GET['action']) && $_GET['action'] === 'read_all') {
    $mark_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $mark_stmt->execute([$current_user_id]);
    header("Location: notifications.php");
    exit;
}

// Handle single notification mark as read & redirect
if (isset($_GET['read_id'])) {
    $read_id = (int)$_GET['read_id'];
    
    // Fetch notification target
    $n_stmt = $pdo->prepare("SELECT post_id FROM notifications WHERE id = ? AND user_id = ?");
    $n_stmt->execute([$read_id, $current_user_id]);
    $notif = $n_stmt->fetch();

    if ($notif) {
        $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $update_stmt->execute([$read_id]);

        if ($notif['post_id']) {
            header("Location: index.php#post_" . $notif['post_id']);
            exit;
        }
    }
    header("Location: notifications.php");
    exit;
}

// Fetch user notifications
$stmt = $pdo->prepare("
    SELECT n.*, u.username as actor_name, u.profile_image as actor_image 
    FROM notifications n
    JOIN users u ON n.actor_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([$current_user_id]);
$notifications = $stmt->fetchAll();

function resolveImagePath($fileName) {
    if (empty($fileName)) return null;
    if (strpos($fileName, 'http') === 0) return $fileName;
    $cleanName = ltrim($fileName, '/');
    if (file_exists($cleanName)) return $cleanName;
    if (file_exists('uploads/' . $cleanName)) return 'uploads/' . $cleanName;
    return (strpos($cleanName, 'uploads/') === 0) ? $cleanName : 'uploads/' . $cleanName;
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • Notifications</title>
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
            --unread-bg: rgba(139, 92, 246, 0.12);
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
            --unread-bg: rgba(139, 92, 246, 0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); display: flex; min-height: 100vh; }

        /* Sidebar Navigation */
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 250px; border-right: 1px solid var(--card-border); padding: 30px 18px; display: flex; flex-direction: column; background-color: var(--sidebar-bg); z-index: 100; }
        .logo { font-size: 28px; font-weight: 800; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 30px; padding-left: 10px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; flex-grow: 1; }
        .nav-item a { text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; position: relative; }
        .nav-item:hover a, .nav-item.active a { background-color: var(--hover-bg); }

        .notif-badge {
            background: #ec4899;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: auto;
            display: inline-block;
        }

        /* Main Content */
        .main-content { margin-left: 250px; width: calc(100% - 250px); display: flex; justify-content: center; padding: 40px 20px; }
        .notif-container { width: 100%; max-width: 620px; display: flex; flex-direction: column; gap: 18px; }
        
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .header-title { font-size: 22px; font-weight: 800; }
        .mark-read-btn { color: #8b5cf6; text-decoration: none; font-size: 14px; font-weight: 600; }
        .mark-read-btn:hover { text-decoration: underline; }

        .notif-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--text-main);
            transition: transform 0.2s, background 0.2s;
        }

        .notif-card:hover { transform: translateY(-2px); background: var(--hover-bg); }
        .notif-card.unread { background: var(--unread-bg); border-left: 4px solid #8b5cf6; }

        .actor-avatar { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; }
        .avatar-placeholder { width: 46px; height: 46px; border-radius: 50%; background: #1e293b; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; }

        .notif-body { flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .notif-text { font-size: 14.5px; line-height: 1.4; }
        .notif-text strong { font-weight: 700; }
        .notif-time { font-size: 12px; color: var(--text-sub); }

        .type-icon { font-size: 18px; }
        .icon-like { color: #ec4899; }
        .icon-comment { color: #3b82f6; }
        .icon-reply { color: #8b5cf6; }

        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-sub); font-weight: 600; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="logo">Nexagram</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php"><i class="fa-solid fa-house"></i> <?php echo __('nav_home'); ?></a></li>
            <li class="nav-item active">
                <a href="notifications.php">
                    <i class="fa-solid fa-bell"></i> Notifications
                    <span class="notif-badge" id="navNotifBadge" style="display:none;">0</span>
                </a>
            </li>
            <li class="nav-item"><a href="friends.php"><i class="fa-solid fa-user-group"></i> <?php echo __('nav_friends'); ?></a></li>
            <li class="nav-item"><a href="users.php"><i class="fa-solid fa-users"></i> <?php echo __('nav_users'); ?></a></li>
            <li class="nav-item"><a href="create_post.php"><i class="fa-solid fa-square-plus"></i> <?php echo __('nav_create'); ?></a></li>
            <li class="nav-item"><a href="profile.php"><i class="fa-solid fa-user"></i> <?php echo __('nav_profile'); ?></a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="notif-container">
            <div class="header-bar">
                <h1 class="header-title">Notifications</h1>
                <a href="notifications.php?action=read_all" class="mark-read-btn">Mark all as read</a>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="empty-state">No notifications yet!</div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <?php
                    $avatar = resolveImagePath($n['actor_image']);
                    $unreadClass = $n['is_read'] == 0 ? 'unread' : '';
                    
                    // Determine text & icon based on notification type
                    $actionText = "";
                    $iconClass = "fa-solid fa-bell";
                    $iconColor = "icon-comment";

                    switch ($n['type']) {
                        case 'like_post':
                            $actionText = "liked your post.";
                            $iconClass = "fa-solid fa-heart";
                            $iconColor = "icon-like";
                            break;
                        case 'comment_post':
                            $actionText = "commented on your post.";
                            $iconClass = "fa-solid fa-comment";
                            $iconColor = "icon-comment";
                            break;
                        case 'like_comment':
                            $actionText = "liked your comment.";
                            $iconClass = "fa-solid fa-heart";
                            $iconColor = "icon-like";
                            break;
                        case 'reply_comment':
                            $actionText = "replied to your comment.";
                            $iconClass = "fa-solid fa-reply";
                            $iconColor = "icon-reply";
                            break;
                    }
                    ?>
                    <a href="notifications.php?read_id=<?php echo $n['id']; ?>" class="notif-card <?php echo $unreadClass; ?>">
                        <?php if ($avatar): ?>
                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="actor-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder"><?php echo mb_substr($n['actor_name'], 0, 1, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <div class="notif-body">
                            <div class="notif-text">
                                <strong><?php echo htmlspecialchars($n['actor_name']); ?></strong> <?php echo $actionText; ?>
                            </div>
                            <div class="notif-time"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></div>
                        </div>

                        <i class="<?php echo $iconClass . ' ' . $iconColor; ?> type-icon"></i>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Real-time notification badge updater
        function updateNotifBadge() {
            fetch('api_unread_notifications.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('navNotifBadge');
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(err => console.error(err));
        }

        updateNotifBadge();
        setInterval(updateNotifBadge, 8000); // Check every 8 seconds
    </script>
</body>
</html>