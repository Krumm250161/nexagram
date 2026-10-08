<?php
// register_process.php - Nexagram User Registration Handling
session_start();
require_once 'config.php'; // ရှေ့မှာ ဆောက်ခဲ့တဲ့ Database Connection ကို လှမ်းခေါ်တာပါ

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form ကနေ ပို့လိုက်တဲ့ Data တွေကို လက်ခံပြီး သန့်စင်ခြင်း (Sanitization)
    $student_id = trim($_POST['student_id']);
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];

    // အချက်အလက်တွေ အားလုံး ပါ၊ မပါ အခြေခံ စစ်ဆေးခြင်း
    if (empty($student_id) || empty($username) || empty($email) || empty($password)) {
        die("全ての項目を入力してください。(All fields are required.)");
    }

    try {
        // 1. Database ထဲမှာ ဒီ Student ID, Username သို့မဟုတ် Email က ရှိပြီးသား ဖြစ်နေလား အရင်စစ်မယ်
        $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ? OR username = ? OR email = ?");
        $stmt->execute([$student_id, $username, $email]);
        
        if ($stmt->rowCount() > 0) {
            die("この学籍番号、ユーザー名、またはメールアドレスは既に登録されています。<br>(Student ID, Username, or Email already exists.)");
        }

        // 2. Password (စကားဝှက်) ကို လုံခြုံအောင် Encrypt (Hash) လုပ်ခြင်း
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. Database ထဲကို ကျောင်းသားအသစ်အဖြစ် သိမ်းဆည်းခြင်း
        $sql = "INSERT INTO users (student_id, username, email, password) VALUES (?, ?, ?, ?)";
        $insert_stmt = $pdo->prepare($sql);
        $insert_stmt->execute([$student_id, $username, $email, $hashed_password]);

        // အကောင့်ဖွင့်တာ အောင်မြင်သွားရင် Login Page ကို ပို့ပေးမယ် (Login Page ကို နောက်အဆင့်မှာ ဆောက်ပါမယ်)
        echo "<script>
                alert('アカウント登録が完了しました！ログインしてください。 (Registration successful!)');
                window.location.href = 'login.php';
              </script>";
        exit;

    } catch (\PDOException $e) {
        // Error တက်ခဲ့ရင် ပြပေးဖို့
        die("エラーが発生しました (Error): " . $e->getMessage());
    }
} else {
    // တကယ်လို့ တိုက်ရိုက် url ကနေ ဝင်လာရင် register.php ဆီ ပြန်မောင်းထုတ်မယ်
    header("Location: register.php");
    exit;
}
?>