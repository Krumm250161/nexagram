<?php
// register.php
session_start();
require_once 'config.php';
require_once 'lang.php'; // Included language support

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'student';
    $user_code = trim($_POST['user_code'] ?? ''); // Student ID or Teacher ID
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = $_POST['department'] ?? '';

    if (empty($user_code) || empty($username) || empty($email) || empty($password) || empty($department)) {
        $error = 'すべての項目を入力してください。';
    } else {
        try {
            // Check if email or username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);

            if ($stmt->fetch()) {
                $error = 'ユーザー名またはメールアドレスはすでに使用されています。';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (role, user_code, student_id, username, email, password, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$role, $user_code, $user_code, $username, $email, $hashedPassword, $department]);

                $success = '登録が完了しました！ログインしてください。';
            }
        } catch (\PDOException $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexagram • <?php echo __('register_title') ?? '新規登録'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        }

        body {
            background-color: #0b0f17;
            color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        /* Top-Right Language Switcher Bar */
        .top-lang-bar {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(23, 29, 45, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px 12px;
            border-radius: 12px;
        }

        .top-lang-bar select {
            background: transparent;
            border: none;
            color: #f8fafc;
            font-size: 13px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .top-lang-bar select option {
            background-color: #111827;
            color: #f8fafc;
        }

        .register-card {
            background: rgba(23, 29, 45, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 36px 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .logo {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #ec4899, #8b5cf6, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            font-style: italic;
        }

        .sub-text {
            color: #94a3b8;
            font-size: 13.5px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* Role Selector Tabs */
        .role-selector {
            display: flex;
            background: rgba(15, 23, 42, 0.6);
            padding: 4px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 20px;
            gap: 4px;
        }

        .role-option {
            flex: 1;
            padding: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #94a3b8;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .role-option.active {
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 14px;
            text-align: left;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 13px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #f8fafc;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .form-input::placeholder {
            color: #64748b;
        }

        .form-input:focus,
        .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        .form-select option {
            background-color: #111827;
            color: #f8fafc;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0095f6, #0066cc);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: opacity 0.2s;
        }

        .btn-submit:hover {
            opacity: 0.9;
        }

        .message-box {
            padding: 10px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .success-msg {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .footer-text {
            margin-top: 20px;
            font-size: 13.5px;
            color: #94a3b8;
        }

        .footer-text a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 700;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <!-- Language Selector Dropdown -->
    <div class="top-lang-bar">
        <i class="fa-solid fa-globe"></i>
        <select onchange="location = this.value;">
            <option value="?lang=ja" <?php echo ($current_lang === 'ja') ? 'selected' : ''; ?>>🇯🇵 日本語</option>
            <option value="?lang=en" <?php echo ($current_lang === 'en') ? 'selected' : ''; ?>>🇺🇸 English</option>
        </select>
    </div>

    <div class="register-card">
        <div class="logo">Nexagram</div>
        <p class="sub-text">キャンパスの仲間とつながり、最新の学内情報をシェアしよう。</p>

        <?php if ($error): ?>
            <div class="message-box error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message-box success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <!-- Hidden Role Input -->
            <input type="hidden" name="role" id="roleInput" value="student">

            <!-- Role Selector Toggle -->
            <div class="role-selector">
                <div class="role-option active" id="btnStudent" onclick="setRole('student')">
                    <i class="fa-solid fa-graduation-cap"></i> <?php echo __('role_student') ?? '学生 (Student)'; ?>
                </div>
                <div class="role-option" id="btnTeacher" onclick="setRole('teacher')">
                    <i class="fa-solid fa-chalkboard-user"></i> <?php echo __('role_teacher') ?? '教員 (Teacher)'; ?>
                </div>
            </div>

            <!-- ID Input (Changes dynamically) -->
            <div class="form-group">
                <input type="text" name="user_code" id="userCodeInput" class="form-input" placeholder="Student ID (学籍番号)" required>
            </div>

            <!-- Username -->
            <div class="form-group">
                <input type="text" name="username" class="form-input" placeholder="Username (ユーザー名)" required>
            </div>

            <!-- Email -->
            <div class="form-group">
                <input type="email" name="email" class="form-input" placeholder="School Email (学校のメールアドレス)" required>
            </div>

            <!-- Password -->
            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Password (パスワード)" required>
            </div>

            <!-- Department Dropdown Selection -->
            <div class="form-group">
                <select name="department" class="form-select" required>
                    <option value="" disabled selected>Department (学科を選択)</option>
                    <option value="Global Systems">グローバルシステム学科 (Global Systems)</option>
                    <option value="Information Technology">IT学科 (Information Technology)</option>
                    <option value="Business Management">経営ビジネス学科 (Business Management)</option>
                    <option value="Digital Design">デジタルデザイン学科 (Digital Design)</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Sign up</button>
        </form>

        <p class="footer-text">アカウントをお持ちですか？ <a href="login.php">Log in</a></p>
    </div>

    <script>
        function setRole(role) {
            const roleInput = document.getElementById('roleInput');
            const btnStudent = document.getElementById('btnStudent');
            const btnTeacher = document.getElementById('btnTeacher');
            const userCodeInput = document.getElementById('userCodeInput');

            roleInput.value = role;

            if (role === 'student') {
                btnStudent.classList.add('active');
                btnTeacher.classList.remove('active');
                userCodeInput.placeholder = 'Student ID (学籍番号)';
            } else {
                btnTeacher.classList.add('active');
                btnStudent.classList.remove('active');
                userCodeInput.placeholder = 'Teacher ID (教員番号)';
            }
        }
    </script>
</body>

</html>