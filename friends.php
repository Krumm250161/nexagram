<?php
// friends.php - Nexagram People & Network Directory
session_start();
require_once 'config.php';
require_once 'lang.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'friends';
$search = trim($_GET['search'] ?? '');

function resolveImagePath($fileName)
{
    if (empty($fileName)) return null;
    if (strpos($fileName, 'http') === 0) return $fileName;

    $cleanName = ltrim($fileName, '/');
    if (file_exists($cleanName)) return $cleanName;
    if (file_exists('uploads/' . $cleanName)) return 'uploads/' . $cleanName;

    return (strpos($cleanName, 'uploads/') === 0) ? $cleanName : 'uploads/' . $cleanName;
}

// Counts for tab badges
try {
    // Friends Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM friend_requests WHERE (sender_id = ? OR receiver_id = ?) AND status = 'accepted'");
    $stmt->execute([$current_user_id, $current_user_id]);
    $friends_count = $stmt->fetchColumn();

    // Requests Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM friend_requests WHERE receiver_id = ? AND status = 'pending'");
    $stmt->execute([$current_user_id]);
    $requests_count = $stmt->fetchColumn();

    // Following Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
    $stmt->execute([$current_user_id]);
    $following_count = $stmt->fetchColumn();

    // Followers Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
    $stmt->execute([$current_user_id]);
    $followers_count = $stmt->fetchColumn();

    // Query active tab data
    $list_users = [];

    if ($tab === 'friends') {
        $sql = "SELECT users.id, users.username, users.profile_image, users.bio
                FROM friend_requests fr
                JOIN users ON (users.id = IF(fr.sender_id = ?, fr.receiver_id, fr.sender_id))
                WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted'";
        $params = [$current_user_id, $current_user_id, $current_user_id];
        if ($search !== '') {
            $sql .= " AND users.username LIKE ?";
            $params[] = "%$search%";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $list_users = $stmt->fetchAll();

    } elseif ($tab === 'requests') {
        $sql = "SELECT users.id, users.username, users.profile_image, users.bio, fr.id AS request_id
                FROM friend_requests fr
                JOIN users ON users.id = fr.sender_id
                WHERE fr.receiver_id = ? AND fr.status = 'pending'";
        $params = [$current_user_id];
        if ($search !== '') {
            $sql .= " AND users.username LIKE ?";
            $params[] = "%$search%";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $list_users = $stmt->fetchAll();

    } elseif ($tab === 'following') {
        $sql = "SELECT users.id, users.username, users.profile_image, users.bio, 1 AS is_following
                FROM follows f
                JOIN users ON users.id = f.following_id
                WHERE f.follower_id = ?";
        $params = [$current_user_id];
        if ($search !== '') {
            $sql .= " AND users.username LIKE ?";
            $params[] = "%$search%";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $list_users = $stmt->fetchAll();

    } elseif ($tab === 'followers') {
        $sql = "SELECT users.id, users.username, users.profile_image, users.bio,
                (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = users.id) AS is_following_back
                FROM follows f
                JOIN users ON users.id = f.follower_id
                WHERE f.following_id = ?";
        $params = [$current_user_id, $current_user_id];
        if ($search !== '') {
            $sql .= " AND users.username LIKE ?";
            $params[] = "%$search%";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $list_users = $stmt->fetchAll();
    }

} catch (\PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • <?php echo __('nav_friends'); ?></title>
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
            --avatar-bg: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); display: flex; min-height: 100vh; }

        /* Sidebar Navigation */
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 250px; border-right: 1px solid var(--card-border); padding: 30px 18px; display: flex; flex-direction: column; background-color: var(--sidebar-bg); z-index: 100; }
        .logo { font-size: 28px; font-weight: 800; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 40px; padding-left: 10px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; height: 100%; }
        .nav-item a { text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; }
        .nav-item:hover a, .nav-item.active a { background-color: var(--hover-bg); }
        
        /* Language Selector Styling */
        .lang-dropdown { position: relative; margin-top: auto; }
        .lang-dropdown > a { cursor: pointer; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; color: var(--text-main); }
        .lang-arrow { margin-left: auto; font-size: 12px; color: var(--text-sub); transition: transform 0.2s; }
        .lang-menu { display: none; position: absolute; bottom: 100%; left: 0; width: 100%; background: var(--sidebar-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 6px; box-shadow: var(--glass-shadow); margin-bottom: 8px; z-index: 10; }
        .lang-menu.show { display: flex; flex-direction: column; gap: 4px; }
        .lang-menu a { padding: 10px 14px; font-size: 14px; font-weight: 600; color: var(--text-main); text-decoration: none; border-radius: 8px; display: flex; align-items: center; gap: 10px; }
        .lang-menu a:hover { background-color: var(--hover-bg); }

        .theme-btn { cursor: pointer; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; }
        .theme-btn:hover { background-color: var(--hover-bg); }
        .logout-item a { color: #ef4444 !important; }

        /* Main Content */
        .main-content { margin-left: 250px; width: calc(100% - 250px); padding: 40px; max-width: 800px; margin-right: auto; }
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 26px; font-weight: 800; margin-bottom: 20px; }

        /* Search Bar */
        .search-bar { display: flex; gap: 10px; background: var(--card-bg); border: 1px solid var(--card-border); padding: 12px 18px; border-radius: 16px; align-items: center; margin-bottom: 20px; }
        .search-bar input { background: transparent; border: none; outline: none; color: var(--text-main); width: 100%; font-size: 14px; }

        /* Navigation Tabs */
        .nav-tabs { display: flex; gap: 8px; background: var(--card-bg); padding: 6px; border-radius: 16px; border: 1px solid var(--card-border); margin-bottom: 24px; overflow-x: auto; }
        .tab-btn { flex: 1; text-align: center; padding: 10px 14px; border-radius: 12px; font-size: 13.5px; font-weight: 700; color: var(--text-sub); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; transition: all 0.2s; }
        .tab-btn:hover { color: var(--text-main); background: var(--hover-bg); }
        .tab-btn.active { background: var(--accent-gradient); color: #ffffff; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.25); }
        .badge { background: rgba(255, 255, 255, 0.2); padding: 2px 7px; border-radius: 10px; font-size: 11px; }

        /* Connections List */
        .network-list { display: flex; flex-direction: column; gap: 12px; }
        .user-item { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; transition: opacity 0.2s ease, transform 0.2s ease; }
        .user-item:hover { transform: translateY(-2px); }

        .user-info-wrap { display: flex; align-items: center; gap: 14px; text-decoration: none; color: var(--text-main); }
        .avatar-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        .avatar-circle { width: 50px; height: 50px; border-radius: 50%; background: var(--avatar-bg); display: flex; justify-content: center; align-items: center; font-size: 18px; font-weight: 700; border: 1px solid var(--card-border); }
        .user-details { display: flex; flex-direction: column; gap: 2px; }
        .username { font-weight: 700; font-size: 15px; }
        .badge-follows-you { display: inline-block; font-size: 11px; font-weight: 600; background: rgba(139, 92, 246, 0.15); color: #a78bfa; padding: 2px 8px; border-radius: 8px; border: 1px solid rgba(139, 92, 246, 0.3); width: fit-content; margin-top: 2px; }

        /* Action Buttons */
        .action-group { display: flex; gap: 8px; align-items: center; }

        .btn-unfriend { background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .btn-unfriend:hover { background: #ef4444; color: #ffffff; border-color: #ef4444; }

        .btn-follow-back { background: var(--accent-gradient); color: #ffffff; border: none; padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.25); }
        .btn-follow-back:hover { opacity: 0.9; transform: translateY(-1px); }

        .btn-following { background: var(--hover-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .btn-following:hover { background: rgba(239, 68, 68, 0.12); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }

        .btn-accept { background: #10b981; color: #fff; border: none; padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-reject { background: var(--hover-bg); color: #ef4444; border: 1px solid var(--card-border); padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; }
    </style>
</head>

<body>

    <nav class="sidebar">
        <div class="logo">Nexagram</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php"><i class="fa-solid fa-house"></i> <?php echo __('nav_home'); ?></a></li>
            <li class="nav-item active"><a href="friends.php"><i class="fa-solid fa-user-group"></i> <?php echo __('nav_friends'); ?></a></li>
            <li class="nav-item"><a href="users.php"><i class="fa-solid fa-users"></i> <?php echo __('nav_users'); ?></a></li>
            <li class="nav-item"><a href="#"><i class="fa-solid fa-paper-plane"></i> <?php echo __('nav_messages'); ?></a></li>
            <li class="nav-item"><a href="create_post.php"><i class="fa-solid fa-square-plus"></i> <?php echo __('nav_create'); ?></a></li>
            <li class="nav-item"><a href="profile.php"><i class="fa-solid fa-user"></i> <?php echo __('nav_profile'); ?></a></li>

            <!-- Language Dropdown Item -->
            <li class="nav-item lang-dropdown">
                <a href="javascript:void(0);" onclick="toggleLangMenu()">
                    <i class="fa-solid fa-globe"></i>
                    <span><?php echo $current_lang === 'ja' ? 'JP 日本語' : 'EN English'; ?></span>
                    <i class="fa-solid fa-chevron-down lang-arrow"></i>
                </a>
                <div class="lang-menu" id="langMenu">
                    <a href="?tab=<?php echo htmlspecialchars($tab); ?>&lang=ja">JP 日本語</a>
                    <a href="?tab=<?php echo htmlspecialchars($tab); ?>&lang=en">EN English</a>
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
        <div class="page-header">
            <h1 class="page-title"><?php echo __('network_title'); ?></h1>

            <form method="GET" action="friends.php" class="search-bar">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <i class="fa-solid fa-magnifying-glass" style="color:var(--text-sub);"></i>
                <input type="text" name="search" placeholder="<?php echo __('search_connections_placeholder'); ?>" value="<?php echo htmlspecialchars($search); ?>">
            </form>

            <div class="nav-tabs">
                <a href="friends.php?tab=friends" class="tab-btn <?php echo $tab === 'friends' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-check"></i> <?php echo __('tab_friends'); ?> <span class="badge"><?php echo $friends_count; ?></span>
                </a>
                <a href="friends.php?tab=requests" class="tab-btn <?php echo $tab === 'requests' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-plus"></i> <?php echo __('tab_requests'); ?> <span class="badge"><?php echo $requests_count; ?></span>
                </a>
                <a href="friends.php?tab=following" class="tab-btn <?php echo $tab === 'following' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> <?php echo __('tab_following'); ?> <span class="badge"><?php echo $following_count; ?></span>
                </a>
                <a href="friends.php?tab=followers" class="tab-btn <?php echo $tab === 'followers' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users-viewfinder"></i> <?php echo __('tab_followers'); ?> <span class="badge"><?php echo $followers_count; ?></span>
                </a>
            </div>
        </div>

        <div class="network-list">
            <?php if (empty($list_users)): ?>
                <div style="text-align:center; padding:40px; color:var(--text-sub);"><?php echo __('no_users'); ?></div>
            <?php else: ?>
                <?php foreach ($list_users as $u): ?>
                    <?php $u_avatar = resolveImagePath($u['profile_image'] ?? null); ?>
                    <div class="user-item">
                        <a href="profile.php?id=<?php echo $u['id']; ?>" class="user-info-wrap">
                            <?php if (!empty($u_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($u_avatar); ?>" class="avatar-img">
                            <?php else: ?>
                                <div class="avatar-circle"><?php echo mb_substr(htmlspecialchars($u['username']), 0, 1, 'UTF-8'); ?></div>
                            <?php endif; ?>

                            <div class="user-details">
                                <span class="username"><?php echo htmlspecialchars($u['username']); ?></span>
                                <?php if ($tab === 'followers'): ?>
                                    <span class="badge-follows-you"><?php echo __('follows_you'); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="action-group">
                            <?php if ($tab === 'friends'): ?>
                                <button class="btn-unfriend" onclick="handleUnfriend(this, <?php echo $u['id']; ?>)">
                                    <i class="fa-solid fa-user-minus"></i>
                                    <span><?php echo __('btn_unfriend'); ?></span>
                                </button>

                            <?php elseif ($tab === 'followers'): ?>
                                <?php $is_following_back = (bool)$u['is_following_back']; ?>
                                <button class="action-btn <?php echo $is_following_back ? 'btn-following' : 'btn-follow-back'; ?>" 
                                        onclick="handleFollowAction(this, <?php echo $u['id']; ?>)">
                                    <i class="fa-solid <?php echo $is_following_back ? 'fa-user-check' : 'fa-user-plus'; ?>"></i>
                                    <span><?php echo $is_following_back ? __('btn_following') : __('btn_follow_back'); ?></span>
                                </button>

                            <?php elseif ($tab === 'following'): ?>
                                <button class="action-btn btn-following" 
                                        onclick="handleFollowAction(this, <?php echo $u['id']; ?>)">
                                    <i class="fa-solid fa-user-check"></i>
                                    <span><?php echo __('btn_following'); ?></span>
                                </button>

                            <?php elseif ($tab === 'requests'): ?>
                                <button class="btn-accept" onclick="handleFriendRequest(this, <?php echo $u['id']; ?>, 'accept')"><?php echo __('btn_accept'); ?></button>
                                <button class="btn-reject" onclick="handleFriendRequest(this, <?php echo $u['id']; ?>, 'reject')"><?php echo __('btn_reject'); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const i18n = {
            confirmUnfriend: "<?php echo addslashes(__('confirm_unfriend')); ?>",
            btnFollowing: "<?php echo addslashes(__('btn_following')); ?>",
            btnFollowBack: "<?php echo addslashes(__('btn_follow_back')); ?>",
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

        // Unfriend Action Handler
        function handleUnfriend(button, targetId) {
            if (!confirm(i18n.confirmUnfriend)) return;

            const formData = new FormData();
            formData.append('target_id', targetId);
            formData.append('target_user_id', targetId);
            formData.append('action', 'remove');

            fetch('friend_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success || data.status) {
                    const row = button.closest('.user-item');
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 200);
                }
            })
            .catch(err => console.error("Unfriend error:", err));
        }

        // Follow / Unfollow / Follow Back Action Handler
        function handleFollowAction(button, targetUserId) {
            const formData = new FormData();
            formData.append('target_user_id', targetUserId);
            formData.append('target_id', targetUserId);

            fetch('follow_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const icon = button.querySelector('i');
                const textSpan = button.querySelector('span');
                const status = data.status || data.action || (data.success ? 'followed' : 'none');

                if (status === 'followed' || status === 'follow') {
                    button.className = 'action-btn btn-following';
                    icon.className = 'fa-solid fa-user-check';
                    textSpan.textContent = i18n.btnFollowing;
                } else {
                    button.className = 'action-btn btn-follow-back';
                    icon.className = 'fa-solid fa-user-plus';
                    textSpan.textContent = i18n.btnFollowBack;
                }
            })
            .catch(err => console.error("Follow action error:", err));
        }

        // Friend Request Accept/Reject Handler
        function handleFriendRequest(button, targetId, action) {
            const formData = new FormData();
            formData.append('target_id', targetId);
            formData.append('target_user_id', targetId);
            formData.append('sender_id', targetId);
            formData.append('action', action);

            fetch('friend_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success || data.status) {
                    const row = button.closest('.user-item');
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 200);
                } else {
                    console.error("Server error:", data.error || data.message);
                }
            })
            .catch(err => console.error("Friend request error:", err));
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
            themeToggleBtn.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                updateThemeUI(currentTheme === 'dark' ? 'light' : 'dark');
            });
        }
    </script>
</body>
</html>