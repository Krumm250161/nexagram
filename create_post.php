<?php
// create_post.php - Nexagram Multi-Photo Post Creator
session_start();
require_once 'config.php';
require_once 'lang.php'; // Localized strings and language system logic

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {$caption = trim($_POST['caption'] ?? '');$visibility = $_POST['visibility'] ?? 'public';$allowed_visibilities = ['public', 'followers', 'friends', 'only_me'];

    // Fallback if an invalid visibility setting is provided
    if (!in_array($visibility, $allowed_visibilities, true)) {$visibility = 'public';
    }

    $uploaded_files = [];

    // Process file uploads
    if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
        $file_count = count($_FILES['media']['name']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];$upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        for ($i = 0; $i < $file_count; $i++) {
            if (isset($_FILES['media']['error'][$i]) && $_FILES['media']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp =$_FILES['media']['tmp_name'][$i];$file_name = $_FILES['media']['name'][$i];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed_exts, true)) {$new_file_name = uniqid('post_', true) . '_' . $i . '.' .$ext;
                    $destination = $upload_dir .$new_file_name;

                    if (move_uploaded_file($file_tmp,$destination)) {
                        $uploaded_files[] =$destination;
                    }
                } else {
                    $error_message = __('err_invalid_format');
                    break;
                }
            }
        }
    }

    if (empty($error_message)) {
       if (!empty($caption) || !empty($uploaded_files)) {
            try {
                $pdo->beginTransaction();

                // 1. Insert main post with visibility setting
                $first_image = !empty($uploaded_files) ?$uploaded_files[0] : null;
                $stmt =$pdo->prepare("INSERT INTO posts (user_id, caption, image_path, visibility, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id,$caption, $first_image,$visibility]);
                $post_id =$pdo->lastInsertId();

                // 2. Insert individual media records into post_media for multi-photo support
                if (!empty($uploaded_files)) {
                    $media_stmt =$pdo->prepare("INSERT INTO post_media (post_id, media_path) VALUES (?, ?)");
                    foreach ($uploaded_files as $path) {$media_stmt->execute([$post_id,$path]);
                    }
                }

                $pdo->commit();
                header("Location: index.php");
                exit;
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {$pdo->rollBack();
                }
                $error_message = 'データベース登録エラー: ' . $e->getMessage();
            }
        } else {
            $error_message = __('err_empty_post');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang ?? 'en', ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • <?php echo __('create_post_title'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Synchronize dark/light theme on page load
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
            --dropzone-bg: rgba(30, 41, 59, 0.4);
            --dropzone-border: #8b5cf6;
            --accent-gradient: linear-gradient(135deg, #ec4899, #8b5cf6, #3b82f6);
            --glass-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.45);
        }

        [data-theme="light"] {
            --bg-color: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.88);
            --card-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-sub: #64748b;
            --sidebar-bg: #ffffff;
            --hover-bg: rgba(0, 0, 0, 0.05);
            --dropzone-bg: rgba(241, 245, 249, 0.8);
            --dropzone-border: #6366f1;
            --glass-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.08);
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

        /* Sidebar Styling */
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
        }

        .nav-item:hover a,
        .nav-item.active a {
            background-color: var(--hover-bg);
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

        /* Sidebar Action Buttons (Dark Mode & Logout) */
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

        /* Main Workspace Content */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .create-card {
            width: 100%;
            max-width: 600px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: var(--glass-shadow);
            padding: 28px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .card-header i {
            color: #8b5cf6;
        }

        /* Dropzone Component */
        .drop-zone {
            width: 100%;
            min-height: 180px;
            border: 2px dashed rgba(139, 92, 246, 0.4);
            border-radius: 18px;
            background: var(--dropzone-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: var(--dropzone-border);
            background: rgba(139, 92, 246, 0.1);
        }

        .drop-zone-prompt {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
        }

        .upload-icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 22px;
        }

        .prompt-title {
            font-weight: 700;
            font-size: 15px;
        }

        .prompt-subtitle {
            font-size: 12.5px;
            color: var(--text-sub);
        }

        .file-input {
            display: none;
        }

        /* Multi-Media Grid Preview */
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            width: 100%;
            margin-top: 16px;
        }

        .preview-item {
            position: relative;
            width: 100%;
            height: 130px;
            border-radius: 14px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .preview-media {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-single-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 13px;
            backdrop-filter: blur(4px);
            z-index: 10;
        }

        .remove-single-btn:hover {
            background: rgba(239, 68, 68, 0.9);
        }

        .add-more-box {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 130px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            cursor: pointer;
            color: var(--text-sub);
            font-size: 13px;
            font-weight: 600;
            gap: 6px;
            transition: all 0.2s;
        }

        .add-more-box:hover {
            border-color: #8b5cf6;
            color: var(--text-main);
        }

        /* Visibility Selector Styling */
        .visibility-section {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .visibility-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .visibility-select {
            width: 100%;
            padding: 12px 16px;
            background: var(--dropzone-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            color: var(--text-main);
            font-size: 14.5px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .visibility-select:focus {
            border-color: #8b5cf6;
        }

        .visibility-select option {
            background: var(--sidebar-bg);
            color: var(--text-main);
        }

        /* Textarea Form Controls */
        .caption-section {
            margin-top: 22px;
        }

        .caption-header {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-sub);
            margin-bottom: 8px;
        }

        .caption-textarea {
            width: 100%;
            min-height: 100px;
            background: var(--dropzone-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 14px;
            color: var(--text-main);
            font-size: 14.5px;
            resize: vertical;
            outline: none;
        }

        .button-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        .cancel-btn {
            padding: 12px 24px;
            border-radius: 14px;
            background: var(--hover-bg);
            color: var(--text-main);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .share-btn {
            padding: 12px 28px;
            border-radius: 14px;
            border: none;
            background: var(--accent-gradient);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 13.5px;
            margin-bottom: 18px;
        }
    </style>
</head>

<body>

    <!-- Sidebar Component -->
    <nav class="sidebar">
        <div class="logo">Nexagram</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php"><i class="fa-solid fa-house"></i> <?php echo __('nav_home'); ?></a></li>
            <li class="nav-item"><a href="friends.php"><i class="fa-solid fa-user-group"></i> <?php echo __('nav_friends'); ?></a></li>
            <li class="nav-item"><a href="users.php"><i class="fa-solid fa-users"></i> <?php echo __('nav_users'); ?></a></li>
            <li class="nav-item"><a href="#"><i class="fa-solid fa-paper-plane"></i> <?php echo __('nav_messages'); ?></a></li>
            <li class="nav-item active"><a href="create_post.php"><i class="fa-solid fa-square-plus"></i> <?php echo __('nav_create'); ?></a></li>
            <li class="nav-item"><a href="profile.php"><i class="fa-solid fa-user"></i> <?php echo __('nav_profile'); ?></a></li>
        </ul>

        <!-- Bottom Controls & Language Switcher Dropdown -->
        <div class="sidebar-bottom">
            <div class="lang-dropdown-container" id="langDropdownContainer">
                <div class="lang-dropdown-menu" id="langDropdownMenu">
                    <a href="?lang=ja" class="lang-option <?php echo ($current_lang ?? '') === 'ja' ? 'active' : ''; ?>">
                        JP 日本語
                    </a>
                    <a href="?lang=en" class="lang-option <?php echo ($current_lang ?? '') === 'en' ? 'active' : ''; ?>">
                        EN English
                    </a>
                </div>

                <button class="lang-dropdown-btn" id="langDropdownToggle" type="button">
                    <i class="fa-solid fa-globe"></i>
                    <span><?php echo ($current_lang ?? '') === 'ja' ? 'JP 日本語' : 'EN English'; ?></span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </button>
            </div>

            <!-- Dark Mode Toggle Button -->
            <button type="button" class="sidebar-action-btn" id="themeToggleBtn">
                <i class="fa-solid fa-moon"></i>
                <span><?php echo __('dark_mode'); ?></span>
            </button>

            <!-- Logout Link -->
            <a href="logout.php" class="sidebar-action-btn logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?php echo __('nav_logout'); ?></span>
            </a>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="create-card">
            <div class="card-header">
                <i class="fa-solid fa-square-plus"></i>
                <span><?php echo __('create_post_title'); ?></span>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="create_post.php" method="POST" enctype="multipart/form-data" id="postForm">
                <!-- File Dropzone -->
                <div class="drop-zone" id="dropZone">
                    <div class="drop-zone-prompt" id="dropZonePrompt">
                        <div class="upload-icon-circle">
                            <i class="fa-solid fa-images"></i>
                        </div>
                        <div class="prompt-title"><?php echo __('dropzone_title'); ?></div>
                        <div class="prompt-subtitle"><?php echo __('dropzone_subtitle'); ?></div>
                    </div>
                </div>

                <!-- File input configured for multiple selections -->
                <input type="file" name="media[]" id="mediaInput" class="file-input" accept="image/*,video/*" multiple>

                <!-- Multi-image Preview Grid -->
                <div class="preview-grid" id="previewGrid" style="display:none;"></div>

                <!-- Privacy / Visibility Selection Field -->
                <div class="visibility-section">
                    <label for="visibilitySelect" class="visibility-label">
                        <i class="fa-solid fa-lock"></i> Visibility / Target Audience
                    </label>
                    <select name="visibility" id="visibilitySelect" class="visibility-select">
                        <option value="public">🌍 Public (Everyone)</option>
                        <option value="followers">👥 Followers Only</option>
                        <option value="friends">🤝 Friends Only</option>
                        <option value="only_me">🔒 Only Me</option>
                    </select>
                </div>

                <!-- Caption Field -->
                <div class="caption-section">
                    <div class="caption-header">
                        <span><?php echo __('caption_label'); ?></span>
                        <span id="charCounter">0 / 500</span>
                    </div>
                    <textarea name="caption" id="captionInput" class="caption-textarea" maxlength="500" placeholder="<?php echo __('write_caption_placeholder'); ?>"></textarea>
                </div>

                <!-- Form Action Buttons -->
                <div class="button-row">
                    <a href="index.php" class="cancel-btn"><?php echo __('btn_cancel'); ?></a>
                    <button type="submit" class="share-btn">
                        <i class="fa-solid fa-paper-plane"></i> <?php echo __('btn_share'); ?>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Language Dropdown Toggle Script
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

        // Theme Toggle Handler Script
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Dropzone & File Upload Logic
        const dropZone = document.getElementById('dropZone');
        const mediaInput = document.getElementById('mediaInput');
        const previewGrid = document.getElementById('previewGrid');
        const captionInput = document.getElementById('captionInput');
        const charCounter = document.getElementById('charCounter');
        const postForm = document.getElementById('postForm');
        const addMoreText = "<?php echo addslashes(__('add_more_photos')); ?>";

        let fileList = [];

        dropZone.addEventListener('click', () => mediaInput.click());

        ['dragenter', 'dragover'].forEach(name => {
            dropZone.addEventListener(name, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            dropZone.addEventListener(name, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer.files);
            addFiles(files);
        });

        mediaInput.addEventListener('change', function() {
            const files = Array.from(this.files);
            addFiles(files);
        });

        function addFiles(files) {
            files.forEach(file => {
                if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
                    fileList.push(file);
                }
            });
            updateFileInput();
            renderPreviews();
        }

        function removeFile(index) {
            fileList.splice(index, 1);
            updateFileInput();
            renderPreviews();
        }

        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            fileList.forEach(file => dataTransfer.items.add(file));
            mediaInput.files = dataTransfer.files;
        }

        // Final sync check before submission
        postForm.addEventListener('submit', function() {
            updateFileInput();
        });

        function renderPreviews() {
            previewGrid.innerHTML = '';

            if (fileList.length === 0) {
                previewGrid.style.display = 'none';
                dropZone.style.display = 'flex';
                return;
            }

            dropZone.style.display = 'none';
            previewGrid.style.display = 'grid';

            fileList.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-single-btn';
                removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                removeBtn.onclick = (e) => {
                    e.stopPropagation();
                    removeFile(index);
                };

                const reader = new FileReader();
                reader.onload = (e) => {
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-media';
                        item.appendChild(img);
                    } else if (file.type.startsWith('video/')) {
                        const video = document.createElement('video');
                        video.src = e.target.result;
                        video.className = 'preview-media';
                        item.appendChild(video);
                    }
                    item.appendChild(removeBtn);
                };
                reader.readAsDataURL(file);

                previewGrid.appendChild(item);
            });

            // "Add More" photo tile
            const addMoreBox = document.createElement('div');
            addMoreBox.className = 'add-more-box';
            addMoreBox.innerHTML = `<i class="fa-solid fa-plus" style="font-size:20px;"></i><span>${addMoreText}</span>`;
            addMoreBox.onclick = () => mediaInput.click();
            previewGrid.appendChild(addMoreBox);
        }

        captionInput.addEventListener('input', () => {
            charCounter.textContent = `${captionInput.value.length} / 500`;
        });
    </script>
</body>

</html>