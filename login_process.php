<?php
// login_process.php - Nexagram User Login Handling
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_email = trim($_POST['username_email']);
    $password       = $_POST['password'];

    if (empty($username_email) || empty($password)) {
        die("全ての項目を入力してください。(All fields are required.)");
    }

    try {
        // Username သို့မဟုတ် Email တစ်ခုခုနဲ့ ကိုက်ညီတဲ့ user ရှိလား ရှာမယ်
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username_email, $username_email]);
        $user = $stmt->fetch();

        // User ရှိတယ်ဆိုရင် Password မှန်၊ မမှန် ဆက်စစ်မယ်
        if ($user && password_verify($password, $user['password'])) {
            // Password မှန်ရင် Session ထဲမှာ User ရဲ့ ID နဲ့ နာမည်ကို မှတ်သားလိုက်မယ်
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];

            // အောင်မြင်ရင် Home Feed Page (index.php) ကို ပို့ပေးမယ်
            header("Location: index.php");
            exit;
        } else {
            // Password မှားရင် သို့မဟုတ် User ရှာမတွေ့ရင်
            die("ユーザー名、メールアドレス、またはパスワードが間違っています。<br>(Incorrect username/email or password.)");
        }

    } catch (\PDOException $e) {
        die("エラーが発生しました (Error): " . $e->getMessage());
    }
} else {
    header("Location: login.php");
    exit;
}
?>