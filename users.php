<?php
// users.php - Nexagram User Directory
session_start();
require_once 'config.php';
require_once 'lang.php'; // Included language support

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$role_tab = $_GET['role'] ?? 'all'; // 'all', 'student', or 'teacher'

function resolveImagePath($fileName)
{
    if (empty($fileName)) return null;
    if (strpos($fileName, 'http') === 0) return $fileName;

    $cleanName = ltrim($fileName, '/');
    if (file_exists($cleanName)) return $cleanName;
    if (file_exists('uploads/' . $cleanName)) return 'uploads/' . $cleanName;

    return (strpos($cleanName, 'uploads/') === 0) ? $cleanName : 'uploads/' . $cleanName;
}

try {
    // Count totals for badges
    $stmtCountAll = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id != ?");
    $stmtCountAll->execute([$current_user_id]);
    $count_all = $stmtCountAll->fetchColumn();

    $stmtCountStudent = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id != ? AND (role = 'student' OR role IS NULL)");
    $stmtCountStudent->execute([$current_user_id]);
    $count_students = $stmtCountStudent->fetchColumn();

    $stmtCountTeacher = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id != ? AND role = 'teacher'");
    $stmtCountTeacher->execute([$current_user_id]);
    $count_teachers = $stmtCountTeacher->fetchColumn();

    // Query Users with Follow & Friend Status
    $sql = "SELECT u.id, u.username, u.profile_image, u.bio, u.role, u.department,
            (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = u.id) AS is_following,
            (SELECT status FROM friend_requests WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) LIMIT 1) AS friend_status,
            (SELECT sender_id FROM friend_requests WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) LIMIT 1) AS request_sender_id
            FROM users u
            WHERE u.id != ?";

    $params = [$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id];

    // Filter by Role Tab
    if ($role_tab === 'student') {
        $sql .= " AND (u.role = 'student' OR u.role IS NULL)";
    } elseif ($role_tab === 'teacher') {
        $sql .= " AND u.role = 'teacher'";
    }

    // Filter by Search Query
    if ($search !== '') {
        $sql .= " AND (u.username LIKE ? OR u.department LIKE ? OR u.bio LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • <?php echo __('nav_users'); ?></title>
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
            --avatar-bg: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); display: flex; min-height: 100vh; }

        /* Sidebar Navigation */
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 250px; border-right: 1px solid var(--card-border); padding: 30px 18px; display: flex; flex-direction: column; background-color: var(--sidebar-bg); z-index: 100; }
        .logo { font-size: 28px; font-weight: 800; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 30px; padding-left: 10px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; flex-grow: 1; }
        .nav-item a { text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; }
        .nav-item:hover a, .nav-item.active a { background-color: var(--hover-bg); }

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
        .main-content { margin-left: 250px; width: calc(100% - 250px); padding: 40px; max-width: 1050px; margin-right: auto; }
        
        .top-nav-bar { display: flex; gap: 10px; margin-bottom: 24px; }
        .nav-back-btn { background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-main); text-decoration: none; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .nav-back-btn:hover { background: var(--hover-bg); }

        .page-title { font-size: 26px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        /* Role Filter Tabs */
        .role-tabs { display: flex; gap: 8px; background: var(--card-bg); padding: 6px; border-radius: 16px; border: 1px solid var(--card-border); margin-bottom: 20px; width: fit-content; }
        .tab-btn { text-align: center; padding: 10px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 700; color: var(--text-sub); text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .tab-btn:hover { color: var(--text-main); background: var(--hover-bg); }
        .tab-btn.active { background: var(--accent-gradient); color: #ffffff; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.25); }
        .badge { background: rgba(255, 255, 255, 0.2); padding: 2px 8px; border-radius: 10px; font-size: 11px; }

        /* Search Bar */
        .search-bar { display: flex; gap: 12px; background: var(--card-bg); border: 1px solid var(--card-border); padding: 12px 18px; border-radius: 16px; align-items: center; margin-bottom: 30px; }
        .search-bar input { background: transparent; border: none; outline: none; color: var(--text-main); width: 100%; font-size: 14px; }
        .search-btn { background: none; border: none; color: var(--text-sub); cursor: pointer; font-size: 15px; display: flex; align-items: center; justify-content: center; padding: 2px; }
        .search-btn:hover { color: var(--text-main); }
        .clear-btn { background: none; border: none; color: var(--text-sub); cursor: pointer; font-size: 15px; display: none; align-items: center; justify-content: center; }
        .clear-btn:hover { color: #ef4444; }

        /* Grid Cards Layout */
        .users-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .user-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; transition: transform 0.2s; }
        .user-card:hover { transform: translateY(-4px); }

        .avatar-img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; }
        .avatar-circle { width: 72px; height: 72px; border-radius: 50%; background: var(--avatar-bg); display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: 700; border: 1px solid var(--card-border); margin-bottom: 12px; }

        .username { font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; text-decoration: none; }
        .username:hover { text-decoration: underline; }

        /* Role & Department Badges */
        .role-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 8px; margin-bottom: 8px; }
        .role-student { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .role-teacher { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .department-text { font-size: 12px; color: var(--text-sub); margin-bottom: 8px; font-weight: 600; }
        .bio { font-size: 13px; color: var(--text-sub); margin-bottom: 20px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 36px; }

        .card-actions { display: flex; gap: 8px; width: 100%; justify-content: center; }

        /* Buttons */
        .btn { flex: 1; padding: 9px 12px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }

        .btn-follow { background: var(--accent-gradient); color: #fff; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.25); }
        .btn-following { background: var(--hover-bg); border: 1px solid var(--card-border); color: var(--text-main); }
        .btn-following:hover { background: rgba(239, 68, 68, 0.12); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }

        .btn-add-friend { background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.3); }
        .btn-add-friend:hover { background: #8b5cf6; color: #fff; }

        .btn-pending { background: var(--hover-bg); color: var(--text-sub); border: 1px solid var(--card-border); }
        .btn-pending:hover { background: rgba(239, 68, 68, 0.12); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }

        .btn-friends { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        
        .no-results-msg { grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-sub); display: none; }
    </style>
</head>

<body>

    <!-- Sidebar Component -->
    <nav class="sidebar">
        <div class="logo">Nexagram</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php"><i class="fa-solid fa-house"></i> <?php echo __('nav_home'); ?></a></li>
            <li class="nav-item"><a href="friends.php"><i class="fa-solid fa-user-group"></i> <?php echo __('nav_friends'); ?></a></li>
            <li class="nav-item active"><a href="users.php"><i class="fa-solid fa-users"></i> <?php echo __('nav_users'); ?></a></li>
            <li class="nav-item"><a href="#"><i class="fa-solid fa-paper-plane"></i> <?php echo __('nav_messages'); ?></a></li>
            <li class="nav-item"><a href="create_post.php"><i class="fa-solid fa-square-plus"></i> <?php echo __('nav_create'); ?></a></li>
            <li class="nav-item"><a href="profile.php"><i class="fa-solid fa-user"></i> <?php echo __('nav_profile'); ?></a></li>
        </ul>

        <!-- Bottom Controls & Language Switcher Dropdown -->
        <div class="sidebar-bottom">
            <div class="lang-dropdown-container" id="langDropdownContainer">
                <!-- Dropdown Popup Options (Shown above dropdown when clicked) -->
                <div class="lang-dropdown-menu" id="langDropdownMenu">
                    <a href="?lang=ja&role=<?php echo urlencode($role_tab); ?>&search=<?php echo urlencode($search); ?>" class="lang-option <?php echo $current_lang === 'ja' ? 'active' : ''; ?>">
                        JP 日本語
                    </a>
                    <a href="?lang=en&role=<?php echo urlencode($role_tab); ?>&search=<?php echo urlencode($search); ?>" class="lang-option <?php echo $current_lang === 'en' ? 'active' : ''; ?>">
                        EN English
                    </a>
                </div>

                <!-- Dropdown Button Bar -->
                <button class="lang-dropdown-btn" id="langDropdownToggle" type="button">
                    <i class="fa-solid fa-globe"></i>
                    <span><?php echo $current_lang === 'ja' ? 'JP 日本語' : 'EN English'; ?></span>
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
        <div class="top-nav-bar">
            <a href="javascript:history.back()" class="nav-back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
            <a href="index.php" class="nav-back-btn"><i class="fa-solid fa-house"></i> Home</a>
        </div>

        <h1 class="page-title"><i class="fa-solid fa-users" style="color:#8b5cf6;"></i> Nexagram <?php echo __('nav_users'); ?></h1>

        <!-- Student / Teacher Role Filter Tabs -->
        <div class="role-tabs">
            <a href="users.php?role=all&search=<?php echo urlencode($search); ?>" class="tab-btn <?php echo $role_tab === 'all' ? 'active' : ''; ?>">
                <i class="fa-solid fa-globe"></i> <?php echo __('tab_all'); ?> <span class="badge"><?php echo $count_all; ?></span>
            </a>
            <a href="users.php?role=student&search=<?php echo urlencode($search); ?>" class="tab-btn <?php echo $role_tab === 'student' ? 'active' : ''; ?>">
                <i class="fa-solid fa-graduation-cap"></i> <?php echo __('tab_students'); ?> <span class="badge"><?php echo $count_students; ?></span>
            </a>
            <a href="users.php?role=teacher&search=<?php echo urlencode($search); ?>" class="tab-btn <?php echo $role_tab === 'teacher' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chalkboard-user"></i> <?php echo __('tab_teachers'); ?> <span class="badge"><?php echo $count_teachers; ?></span>
            </a>
        </div>

        <!-- Search Bar with Form & Interactive Features -->
        <form method="GET" action="users.php" class="search-bar" id="searchForm">
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_tab); ?>">
            <button type="submit" class="search-btn" title="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="search" id="searchInput" placeholder="<?php echo __('search_placeholder'); ?>" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            <button type="button" class="clear-btn" id="clearSearchBtn" title="Clear">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>
        </form>

        <!-- User Cards Grid -->
        <div class="users-grid" id="usersGrid">
            <div class="no-results-msg" id="noResultsMsg">
                <?php echo __('no_users'); ?>
            </div>

            <?php if (empty($users)): ?>
                <div style="grid-column: 1 / -1; text-align:center; padding: 40px; color:var(--text-sub);" id="serverNoResults">
                    <?php echo __('no_users'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <?php
                        $u_avatar = resolveImagePath($u['profile_image'] ?? null);
                        $is_following = (bool)$u['is_following'];
                        $friend_status = $u['friend_status'];
                        $is_sender = ($u['request_sender_id'] == $current_user_id);
                        $is_teacher = ($u['role'] === 'teacher');
                    ?>
                    <div class="user-card" 
                         data-username="<?php echo htmlspecialchars(mb_strtolower($u['username'], 'UTF-8')); ?>" 
                         data-department="<?php echo htmlspecialchars(mb_strtolower($u['department'] ?? '', 'UTF-8')); ?>"
                         data-bio="<?php echo htmlspecialchars(mb_strtolower($u['bio'] ?? '', 'UTF-8')); ?>">
                        
                        <a href="profile.php?id=<?php echo $u['id']; ?>">
                            <?php if (!empty($u_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($u_avatar); ?>" class="avatar-img">
                            <?php else: ?>
                                <div class="avatar-circle"><?php echo mb_substr(htmlspecialchars($u['username']), 0, 1, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </a>

                        <a href="profile.php?id=<?php echo $u['id']; ?>" class="username"><?php echo htmlspecialchars($u['username']); ?></a>

                        <!-- Role Badge -->
                        <span class="role-badge <?php echo $is_teacher ? 'role-teacher' : 'role-student'; ?>">
                            <i class="fa-solid <?php echo $is_teacher ? 'fa-chalkboard-user' : 'fa-graduation-cap'; ?>"></i>
                            <?php echo $is_teacher ? __('role_teacher') : __('role_student'); ?>
                        </span>

                        <!-- Department Tag -->
                        <?php if (!empty($u['department'])): ?>
                            <div class="department-text">
                                <i class="fa-solid fa-building-columns"></i> <?php echo htmlspecialchars($u['department']); ?>
                            </div>
                        <?php endif; ?>

                        <p class="bio"><?php echo htmlspecialchars($u['bio'] ?? 'Nexagram user'); ?></p>

                        <div class="card-actions">
                            <!-- Follow Button -->
                            <button class="btn <?php echo $is_following ? 'btn-following' : 'btn-follow'; ?>"
                                    onclick="handleFollowAction(this, <?php echo $u['id']; ?>)">
                                <i class="fa-solid <?php echo $is_following ? 'fa-user-check' : 'fa-user-plus'; ?>"></i>
                                <span><?php echo $is_following ? __('btn_following') : __('btn_follow'); ?></span>
                            </button>

                            <!-- Friend Button -->
                            <?php if ($friend_status === 'accepted'): ?>
                                <button class="btn btn-friends" onclick="handleFriendAction(this, <?php echo $u['id']; ?>, 'remove')">
                                    <i class="fa-solid fa-user-check"></i>
                                    <span><?php echo __('btn_friends'); ?></span>
                                </button>
                            <?php elseif ($friend_status === 'pending' && $is_sender): ?>
                                <button class="btn btn-pending" onclick="handleFriendAction(this, <?php echo $u['id']; ?>, 'cancel')">
                                    <i class="fa-solid fa-clock"></i>
                                    <span><?php echo __('btn_pending'); ?></span>
                                </button>
                            <?php elseif ($friend_status === 'pending' && !$is_sender): ?>
                                <button class="btn btn-add-friend" onclick="handleFriendAction(this, <?php echo $u['id']; ?>, 'accept')">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span>Accept</span>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-add-friend" onclick="handleFriendAction(this, <?php echo $u['id']; ?>, 'send')">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span><?php echo __('btn_add_friend'); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

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

        // Language Text JS Variables
        const txtFollow = "<?php echo __('btn_follow'); ?>";
        const txtFollowing = "<?php echo __('btn_following'); ?>";
        const txtAddFriend = "<?php echo __('btn_add_friend'); ?>";
        const txtPending = "<?php echo __('btn_pending'); ?>";
        const txtFriends = "<?php echo __('btn_friends'); ?>";

        // Real-Time Live Search Script
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const userCards = document.querySelectorAll('.user-card');
        const noResultsMsg = document.getElementById('noResultsMsg');

        function toggleClearButton() {
            if (searchInput.value.trim().length > 0) {
                clearSearchBtn.style.display = 'flex';
            } else {
                clearSearchBtn.style.display = 'none';
            }
        }

        function filterUsers() {
            const query = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            userCards.forEach(card => {
                const username = card.getAttribute('data-username') || '';
                const department = card.getAttribute('data-department') || '';
                const bio = card.getAttribute('data-bio') || '';

                if (username.includes(query) || department.includes(query) || bio.includes(query)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (userCards.length > 0) {
                if (visibleCount === 0) {
                    noResultsMsg.style.display = 'block';
                } else {
                    noResultsMsg.style.display = 'none';
                }
            }
            toggleClearButton();
        }

        searchInput.addEventListener('input', filterUsers);

        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterUsers();
            searchInput.focus();
            
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('search')) {
                const role = urlParams.get('role') || 'all';
                window.location.href = `users.php?role=${role}`;
            }
        });

        toggleClearButton();

        // Follow / Unfollow Handler
        function handleFollowAction(button, targetUserId) {
            const formData = new FormData();
            formData.append('target_user_id', targetUserId);
            formData.append('target_id', targetUserId);

            fetch('follow_process.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const icon = button.querySelector('i');
                const textSpan = button.querySelector('span');
                const status = data.status || data.action || (data.success ? 'followed' : 'none');

                if (status === 'followed' || status === 'follow') {
                    button.className = 'btn btn-following';
                    icon.className = 'fa-solid fa-user-check';
                    textSpan.textContent = txtFollowing;
                } else {
                    button.className = 'btn btn-follow';
                    icon.className = 'fa-solid fa-user-plus';
                    textSpan.textContent = txtFollow;
                }
            })
            .catch(err => console.error("Follow error:", err));
        }

        // Friend Action Handler
        function handleFriendAction(button, targetId, currentAction) {
            const formData = new FormData();
            formData.append('target_id', targetId);
            formData.append('target_user_id', targetId);
            formData.append('action', currentAction);

            fetch('friend_process.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (!data.success && !data.status) return;

                const icon = button.querySelector('i');
                const textSpan = button.querySelector('span');

                if (data.status === 'requested' || currentAction === 'send') {
                    button.className = 'btn btn-pending';
                    button.setAttribute('onclick', `handleFriendAction(this, ${targetId}, 'cancel')`);
                    icon.className = 'fa-solid fa-clock';
                    textSpan.textContent = txtPending;
                } else if (data.status === 'accepted' || currentAction === 'accept') {
                    button.className = 'btn btn-friends';
                    button.setAttribute('onclick', `handleFriendAction(this, ${targetId}, 'remove')`);
                    icon.className = 'fa-solid fa-user-check';
                    textSpan.textContent = txtFriends;
                } else {
                    button.className = 'btn btn-add-friend';
                    button.setAttribute('onclick', `handleFriendAction(this, ${targetId}, 'send')`);
                    icon.className = 'fa-solid fa-user-plus';
                    textSpan.textContent = txtAddFriend;
                }
            })
            .catch(err => console.error("Friend action error:", err));
        }

        // Theme Switcher
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');
        const darkTxt = "<?php echo __('dark_mode'); ?>";
        const lightTxt = "<?php echo __('light_mode'); ?>";

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