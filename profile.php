<?php
// profile.php - Nexagram Profile with Post Management (Edit & Delete)
session_start();
require_once 'config.php';
require_once 'lang.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$profile_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $logged_in_user_id;
$is_own_profile = ($logged_in_user_id === $profile_user_id);

// --- HANDLE POST DELETE ACTION ---
if ($is_own_profile && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    $delete_post_id = (int)$_POST['post_id'];
    
    // Fetch media path to remove file if needed
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_post_id, $logged_in_user_id]);
    $post_to_delete = $stmt->fetch();

    if ($post_to_delete) {
        $raw_file = $post_to_delete['image_url'] ?? $post_to_delete['image_path'] ?? $post_to_delete['image'] ?? $post_to_delete['file_path'] ?? $post_to_delete['media_url'] ?? null;
        if ($raw_file) {
            $file_to_remove = (strpos($raw_file, 'uploads/') === 0) ? $raw_file : 'uploads/' . ltrim($raw_file, '/');
            if (file_exists($file_to_remove)) {
                @unlink($file_to_remove);
            }
        }

        // Delete comments, likes, and the post record
        $pdo->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$delete_post_id]);
        $pdo->prepare("DELETE FROM likes WHERE post_id = ?")->execute([$delete_post_id]);
        $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?")->execute([$delete_post_id, $logged_in_user_id]);
    }
    
    header("Location: profile.php?id=" . $profile_user_id);
    exit();
}

// --- HANDLE POST EDIT ACTION ---
if ($is_own_profile && isset($_POST['action']) && $_POST['action'] === 'edit_post') {
    $edit_post_id = (int)$_POST['post_id'];
    $new_caption = trim($_POST['caption'] ?? '');

    $stmt = $pdo->prepare("UPDATE posts SET caption = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$new_caption, $edit_post_id, $logged_in_user_id]);

    header("Location: profile.php?id=" . $profile_user_id);
    exit();
}

// 1. Fetch User Info
$user_stmt = $pdo->prepare("SELECT id, username, profile_image, bio FROM users WHERE id = ?");
$user_stmt->execute([$profile_user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    header("Location: index.php");
    exit();
}

// 2. Metrics
$posts_count = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$posts_count->execute([$profile_user_id]);
$posts_count = $posts_count->fetchColumn();

$followers_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$followers_count->execute([$profile_user_id]);
$followers_count = $followers_count->fetchColumn();

$following_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$following_count->execute([$profile_user_id]);
$following_count = $following_count->fetchColumn();

$friends_count = $pdo->prepare("SELECT COUNT(*) FROM friend_requests WHERE (sender_id = ? OR receiver_id = ?) AND status = 'accepted'");
$friends_count->execute([$profile_user_id, $profile_user_id]);
$friends_count = $friends_count->fetchColumn();

// 3. User Posts
$posts_stmt = $pdo->prepare("
    SELECT posts.*, 
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as like_count,
    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comment_count
    FROM posts 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$posts_stmt->execute([$profile_user_id]);
$user_posts = $posts_stmt->fetchAll();

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
    <title>Nexagram • <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        const storedTheme = localStorage.getItem("theme") || "dark";
        document.documentElement.setAttribute("data-theme", storedTheme);
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --bg-color: #0b0f17;
            --card-bg: rgba(23, 29, 45, 0.65);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --sidebar-bg: #111827;
            --hover-bg: rgba(255, 255, 255, 0.06);
            --accent-gradient: linear-gradient(135deg, #ec4899, #8b5cf6, #3b82f6);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        [data-theme="light"] {
            --bg-color: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.85);
            --card-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-sub: #64748b;
            --sidebar-bg: #ffffff;
            --hover-bg: rgba(0, 0, 0, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); display: flex; min-height: 100vh; }

        /* Sidebar Navigation */
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 250px; border-right: 1px solid var(--card-border); padding: 30px 18px; display: flex; flex-direction: column; background-color: var(--sidebar-bg); z-index: 100; }
        .logo { font-size: 28px; font-weight: 800; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 40px; padding-left: 10px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; height: 100%; }
        .nav-item a { text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; transition: background 0.2s; }
        .nav-item:hover a, .nav-item.active a { background-color: var(--hover-bg); }
        .nav-item i { font-size: 18px; width: 22px; }

        /* Language Selector Styling */
        .lang-dropdown { position: relative; margin-top: auto; }
        .lang-dropdown > a { cursor: pointer; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; color: var(--text-main); }
        .lang-arrow { margin-left: auto; font-size: 12px; color: var(--text-sub); transition: transform 0.2s; }
        .lang-menu { display: none; position: absolute; bottom: 100%; left: 0; width: 100%; background: var(--sidebar-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 6px; box-shadow: var(--glass-shadow); margin-bottom: 8px; z-index: 10; }
        .lang-menu.show { display: flex; flex-direction: column; gap: 4px; }
        .lang-menu a { padding: 10px 14px; font-size: 14px; font-weight: 600; color: var(--text-main); text-decoration: none; border-radius: 8px; display: flex; align-items: center; gap: 10px; }
        .lang-menu a:hover { background-color: var(--hover-bg); }

        .theme-btn { cursor: pointer; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; transition: background 0.2s; }
        .theme-btn:hover { background-color: var(--hover-bg); }
        .logout-item a { color: #ef4444 !important; }

        /* Main Content */
        .main-content { margin-left: 250px; width: calc(100% - 250px); display: flex; justify-content: center; padding: 40px 20px; }
        .profile-wrapper { max-width: 935px; width: 100%; }

        .profile-card { background: var(--card-bg); backdrop-filter: blur(16px); border: 1px solid var(--card-border); box-shadow: var(--glass-shadow); border-radius: 24px; padding: 36px 40px; display: flex; align-items: center; gap: 48px; margin-bottom: 36px; }
        .avatar-ring { width: 140px; height: 140px; border-radius: 50%; padding: 4px; background: var(--accent-gradient); flex-shrink: 0; }
        .avatar-inner { width: 100%; height: 100%; border-radius: 50%; overflow: hidden; background: var(--sidebar-bg); display: flex; align-items: center; justify-content: center; font-size: 42px; font-weight: 800; color: var(--text-main); }
        .avatar-inner img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info { display: flex; flex-direction: column; gap: 20px; flex-grow: 1; }
        .header-top { display: flex; align-items: center; gap: 20px; }
        .username { font-size: 26px; font-weight: 800; }
        .btn-edit-profile { padding: 9px 20px; border-radius: 12px; font-weight: 700; font-size: 13.5px; background: var(--hover-bg); border: 1px solid var(--card-border); color: var(--text-main); text-decoration: none; transition: background 0.2s; }
        .btn-edit-profile:hover { background: rgba(255, 255, 255, 0.12); }
        .stats-row { display: flex; gap: 32px; }
        .stat-box { display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text-main); font-size: 15px; }
        .stat-val { font-weight: 800; font-size: 17px; }
        .stat-lbl { color: var(--text-sub); font-weight: 500; }
        .user-bio { font-size: 14.5px; color: var(--text-main); line-height: 1.5; }

        .posts-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .grid-card { aspect-ratio: 1 / 1; border-radius: 16px; overflow: hidden; position: relative; background: var(--card-bg); border: 1px solid var(--card-border); }
        .grid-media { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
        .grid-card:hover .grid-media { transform: scale(1.06); }

        /* Modern Grid Overlay Controls */
        .grid-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(3px); display: flex; flex-direction: column; justify-content: space-between; padding: 16px; opacity: 0; transition: opacity 0.25s ease; color: #ffffff; z-index: 2; }
        .grid-card:hover .grid-overlay { opacity: 1; }

        .overlay-top-actions { display: flex; justify-content: flex-end; gap: 8px; }
        .action-btn { background: rgba(255, 255, 255, 0.2); border: none; color: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.2s; }
        .action-btn:hover { background: rgba(255, 255, 255, 0.4); transform: scale(1.1); }
        .action-btn.btn-delete:hover { background: #ef4444; }

        .overlay-center-stats { display: flex; justify-content: center; align-items: center; gap: 24px; font-weight: 800; font-size: 16px; margin-top: auto; margin-bottom: auto; }
        .overlay-item { display: flex; align-items: center; gap: 8px; }
        .card-caption-tag { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: rgba(0,0,0,0.4); padding: 6px 10px; border-radius: 8px; }

        /* Edit Modal Styling */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.75); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-box { background: var(--sidebar-bg); border: 1px solid var(--card-border); border-radius: 20px; width: 100%; max-width: 480px; padding: 24px; box-shadow: var(--glass-shadow); color: var(--text-main); }
        .modal-title { font-size: 18px; font-weight: 800; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { cursor: pointer; color: var(--text-sub); }
        .modal-textarea { width: 100%; height: 110px; background: var(--hover-bg); border: 1px solid var(--card-border); border-radius: 12px; color: var(--text-main); padding: 12px; font-size: 14px; outline: none; resize: none; margin-bottom: 20px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; }
        .btn-cancel { padding: 10px 18px; border-radius: 10px; background: transparent; border: 1px solid var(--card-border); color: var(--text-main); cursor: pointer; font-weight: 600; }
        .btn-save { padding: 10px 18px; border-radius: 10px; background: #3b82f6; border: none; color: #fff; cursor: pointer; font-weight: 700; }
    </style>
</head>

<body>

    <nav class="sidebar">
        <div class="logo">Nexagram</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php"><i class="fa-solid fa-house"></i> <?php echo __('nav_home'); ?></a></li>
            <li class="nav-item"><a href="friends.php"><i class="fa-solid fa-user-group"></i> <?php echo __('nav_friends'); ?></a></li>
            <li class="nav-item"><a href="users.php"><i class="fa-solid fa-users"></i> <?php echo __('nav_users'); ?></a></li>
            <li class="nav-item"><a href="#"><i class="fa-solid fa-paper-plane"></i> <?php echo __('nav_messages'); ?></a></li>
            <li class="nav-item"><a href="create_post.php"><i class="fa-solid fa-square-plus"></i> <?php echo __('nav_create'); ?></a></li>
            <li class="nav-item active"><a href="profile.php"><i class="fa-solid fa-user"></i> <?php echo __('nav_profile'); ?></a></li>

            <!-- Language Dropdown Item -->
            <li class="nav-item lang-dropdown">
                <a href="javascript:void(0);" onclick="toggleLangMenu()">
                    <i class="fa-solid fa-globe"></i>
                    <span><?php echo $current_lang === 'ja' ? 'JP 日本語' : 'EN English'; ?></span>
                    <i class="fa-solid fa-chevron-down lang-arrow"></i>
                </a>
                <div class="lang-menu" id="langMenu">
                    <a href="?id=<?php echo $profile_user_id; ?>&lang=ja">JP 日本語</a>
                    <a href="?id=<?php echo $profile_user_id; ?>&lang=en">EN English</a>
                </div>
            </li>

            <li class="theme-btn" id="theme-toggle">
                <i class="fa-solid fa-moon" id="theme-icon"></i>
                <span id="theme-text"><?php echo __('dark_mode'); ?></span>
            </li>
            <li class="nav-item logout-item"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <?php echo __('nav_logout'); ?></a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="profile-wrapper">

            <!-- Profile Header -->
            <div class="profile-card">
                <div class="avatar-ring">
                    <div class="avatar-inner">
                        <?php 
                            $profile_img_src = resolveImagePath($user['profile_image'] ?? null);
                            if ($profile_img_src): 
                        ?>
                            <img src="<?php echo htmlspecialchars($profile_img_src); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo mb_substr(htmlspecialchars($user['username']), 0, 1, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-info">
                    <div class="header-top">
                        <h1 class="username"><?php echo htmlspecialchars($user['username']); ?></h1>
                        <?php if ($is_own_profile): ?>
                            <a href="edit_profile.php" class="btn-edit-profile"><?php echo __('edit_profile'); ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="stats-row">
                        <div class="stat-box"><span class="stat-val"><?php echo $posts_count; ?></span> <span class="stat-lbl"><?php echo __('stat_posts'); ?></span></div>
                        <a href="friends.php?tab=followers" class="stat-box"><span class="stat-val"><?php echo $followers_count; ?></span> <span class="stat-lbl"><?php echo __('stat_followers'); ?></span></a>
                        <a href="friends.php?tab=following" class="stat-box"><span class="stat-val"><?php echo $following_count; ?></span> <span class="stat-lbl"><?php echo __('stat_following'); ?></span></a>
                        <a href="friends.php?tab=friends" class="stat-box"><span class="stat-val"><?php echo $friends_count; ?></span> <span class="stat-lbl"><?php echo __('stat_friends'); ?></span></a>
                    </div>

                    <?php if (!empty($user['bio'])): ?>
                        <div class="user-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Posts Grid -->
            <div class="posts-grid">
                <?php if (!empty($user_posts)): ?>
                    <?php foreach ($user_posts as $post): ?>
                        <?php 
                            $raw_file = $post['image_url'] ?? $post['image_path'] ?? $post['image'] ?? $post['file_path'] ?? $post['media_url'] ?? null;
                            $caption = $post['caption'] ?? $post['content'] ?? '';
                            $media_src = resolveImagePath($raw_file);
                        ?>
                        <div class="grid-card">
                            <?php if (!empty($media_src)): ?>
                                <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $media_src)): ?>
                                    <video src="<?php echo htmlspecialchars($media_src); ?>" class="grid-media"></video>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($media_src); ?>" class="grid-media" alt="Post Image">
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Hover Overlay with Edit/Delete Controls -->
                            <div class="grid-overlay">
                                <?php if ($is_own_profile): ?>
                                    <div class="overlay-top-actions">
                                        <button class="action-btn" title="<?php echo __('edit_caption_tooltip'); ?>" onclick="openEditModal(<?php echo $post['id']; ?>, '<?php echo addslashes(htmlspecialchars($caption)); ?>')">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('<?php echo addslashes(__('confirm_delete_post')); ?>');">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="action-btn btn-delete" title="<?php echo __('delete_post_tooltip'); ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>

                                <div class="overlay-center-stats">
                                    <div class="overlay-item"><i class="fa-solid fa-heart"></i> <?php echo $post['like_count']; ?></div>
                                    <div class="overlay-item"><i class="fa-solid fa-comment"></i> <?php echo $post['comment_count']; ?></div>
                                </div>

                                <?php if (!empty($caption)): ?>
                                    <div class="card-caption-tag"><?php echo htmlspecialchars($caption); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Modal for Editing Post Caption -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-title">
                <span><?php echo __('edit_caption_title'); ?></span>
                <i class="fa-solid fa-xmark modal-close" onclick="closeEditModal()"></i>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_post">
                <input type="hidden" name="post_id" id="modalPostId">
                <textarea name="caption" id="modalCaption" class="modal-textarea" placeholder="<?php echo __('write_caption_placeholder'); ?>"></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()"><?php echo __('btn_cancel'); ?></button>
                    <button type="submit" class="btn-save"><?php echo __('btn_save'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const i18n = {
            darkMode: "<?php echo addslashes(__('dark_mode')); ?>",
            lightMode: "<?php echo addslashes(__('light_mode')); ?>"
        };

        // Language Dropdown Handler
        function toggleLangMenu() {
            const langMenu = document.getElementById('langMenu');
            if (langMenu) {
                langMenu.classList.toggle('show');
            }
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.lang-dropdown');
            const langMenu = document.getElementById('langMenu');
            if (dropdown && !dropdown.contains(e.target) && langMenu) {
                langMenu.classList.remove('show');
            }
        });

        // Edit Post Modal Handlers
        function openEditModal(postId, currentCaption) {
            document.getElementById('modalPostId').value = postId;
            document.getElementById('modalCaption').value = currentCaption;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Theme Switcher
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');

        function updateThemeUI(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            if (theme === 'dark') {
                if (themeIcon) themeIcon.className = 'fa-solid fa-moon';
                if (themeText) themeText.textContent = i18n.darkMode;
            } else {
                if (themeIcon) themeIcon.className = 'fa-solid fa-sun';
                if (themeText) themeText.textContent = i18n.lightMode;
            }
        }

        updateThemeUI(localStorage.getItem('theme') || 'dark');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme') || 'dark';
                updateThemeUI(current === 'dark' ? 'light' : 'dark');
            });
        }
    </script>
</body>
</html>