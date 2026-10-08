<?php
// login.php - Nexagram Modern Glassmorphism Login Page
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: index.php");
                exit;
            } else {
                $error_message = 'ユーザー名またはパスワードが正しくありません。';
            }
        } catch (\PDOException $e) {
            $error_message = 'データベースエラーが発生しました。';
        }
    } else {
        $error_message = 'すべての項目を入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • ログイン</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        const storedTheme = localStorage.getItem("theme") || "dark";
        document.documentElement.setAttribute("data-theme", storedTheme);
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --bg-gradient: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #0b0f17 55%, #030712 100%);
            --card-bg: rgba(23, 29, 45, 0.65);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.1);
            --input-focus: #8b5cf6;
            --accent-gradient: linear-gradient(135deg, #ec4899 0%, #8b5cf6 50%, #3b82f6 100%);
            --accent-shadow: rgba(236, 72, 153, 0.35);
            --glass-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            --orb-1: rgba(236, 72, 153, 0.25);
            --orb-2: rgba(59, 130, 246, 0.25);
        }

        [data-theme="light"] {
            --bg-gradient: radial-gradient(circle at 15% 15%, #e0e7ff 0%, #f1f5f9 55%, #e2e8f0 100%);
            --card-bg: rgba(255, 255, 255, 0.75);
            --card-border: rgba(255, 255, 255, 0.6);
            --text-main: #0f172a;
            --text-sub: #64748b;
            --input-bg: rgba(248, 250, 252, 0.9);
            --input-border: rgba(203, 213, 225, 0.8);
            --input-focus: #6366f1;
            --accent-shadow: rgba(99, 102, 241, 0.25);
            --glass-shadow: 0 20px 50px rgba(31, 38, 135, 0.08);
            --orb-1: rgba(236, 72, 153, 0.15);
            --orb-2: rgba(99, 102, 241, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        }

        body {
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /* Ambient Glowing Background Orbs */
        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            z-index: 0;
            pointer-events: none;
        }

        .orb-1 {
            top: 15%;
            left: 20%;
            width: 320px;
            height: 320px;
            background: var(--orb-1);
        }

        .orb-2 {
            bottom: 15%;
            right: 20%;
            width: 380px;
            height: 380px;
            background: var(--orb-2);
        }

        /* Container Card */
        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 42px 36px;
            background: var(--card-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            box-shadow: var(--glass-shadow);
            animation: floatUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes floatUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Brand Logo Header */
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-logo {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -1px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
            display: inline-block;
        }

        .brand-subtitle {
            font-size: 14px;
            color: var(--text-sub);
            font-weight: 500;
        }

        /* Alert Box */
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 20px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: var(--text-sub);
            font-size: 16px;
            transition: color 0.2s;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 16px;
            color: var(--text-main);
            font-size: 14.5px;
            font-weight: 500;
            outline: none;
            transition: all 0.25s ease;
        }

        .form-input::placeholder {
            color: var(--text-sub);
            opacity: 0.7;
        }

        .form-input:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px var(--accent-shadow);
        }

        .form-input:focus + .input-icon {
            color: var(--input-focus);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            color: var(--text-sub);
            cursor: pointer;
            font-size: 15px;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--text-main);
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 16px;
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 20px var(--accent-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 8px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px var(--accent-shadow);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 26px 0;
            color: var(--text-sub);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--card-border);
        }

        .divider span {
            padding: 0 14px;
        }

        /* Footer Links */
        .footer-text {
            text-align: center;
            font-size: 14px;
            color: var(--text-sub);
        }

        .footer-link {
            color: #ec4899;
            text-decoration: none;
            font-weight: 700;
            margin-left: 4px;
            transition: opacity 0.2s;
        }

        .footer-link:hover {
            opacity: 0.85;
            text-decoration: underline;
        }

        /* Theme Toggle Button (Top Right) */
        .theme-toggle-fixed {
            position: fixed;
            top: 24px;
            right: 24px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            backdrop-filter: blur(12px);
            z-index: 10;
            transition: all 0.2s ease;
        }

        .theme-toggle-fixed:hover {
            transform: scale(1.08);
        }
    </style>
</head>

<body>

    <button class="theme-toggle-fixed" id="themeToggle" title="テーマ切り替え">
        <i class="fa-solid fa-moon" id="themeIcon"></i>
    </button>

    <div class="ambient-orb orb-1"></div>
    <div class="ambient-orb orb-2"></div>

    <div class="login-card">
        <div class="brand-header">
            <h1 class="brand-logo">Nexagram</h1>
            <p class="brand-subtitle">おかえりなさい！アカウントにログイン</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <div class="input-wrapper">
                    <input type="text" name="username" class="form-input" placeholder="ユーザー名またはメール" required autocomplete="username">
                    <i class="fa-regular fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <input type="password" name="password" id="passwordInput" class="form-input" placeholder="パスワード" required autocomplete="current-password">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" class="submit-btn">ログイン</button>
        </form>

        <div class="divider">
            <span>または</span>
        </div>

        <div class="footer-text">
            アカウントをお持ちでないですか？
            <a href="register.php" class="footer-link">新規登録</a>
        </div>
    </div>

    <script>
        // Password Visibility Toggle
        const passwordInput = document.getElementById('passwordInput');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            this.classList.toggle('fa-eye', !isPassword);
            this.classList.toggle('fa-eye-slash', isPassword);
        });

        // Theme Toggle Handler
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            themeIcon.className = theme === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        }

        const currentTheme = localStorage.getItem('theme') || 'dark';
        applyTheme(currentTheme);

        themeToggleBtn.addEventListener('click', () => {
            const activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            applyTheme(activeTheme === 'dark' ? 'light' : 'dark');
        });
    </script>
</body>

</html>