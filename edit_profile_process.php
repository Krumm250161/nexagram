<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $bio = trim($_POST['bio']);
    
    try {
        // 1. အရင်ဆုံး လက်ရှိ Profile ပုံအဟောင်း ရှိမရှိ ဒေတာဘေ့စ်ကနေ စစ်မယ်
        $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch();
        $image_destination = $current_user['profile_pic']; // default အဟောင်းအတိုင်း ထားမယ်

        // 2. တကယ်လို့ ဓာတ်ပုံအသစ် ရွေးပြီး တင်လာခဲ့ရင်
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
            $file = $_FILES['profile_pic'];
            $file_name = $file['name'];
            $file_tmp  = $file['tmp_name'];
            $file_size = $file['size'];
            
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_ext) && $file_size < 3000000) { // Max 3MB
                $new_file_name = uniqid('AVATAR_', true) . "." . $file_ext;
                $image_destination = 'uploads/' . $new_file_name;
                
                // ပုံအသစ်ကို uploads ထဲ ရွှေ့မယ်
                move_uploaded_file($file_tmp, $image_destination);
            }
        }

        // 3. Database ထဲမှာ အချက်အလက်တွေကို UPDATE လုပ်မယ်
        $update_sql = "UPDATE users SET bio = ?, profile_pic = ? WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$bio, $image_destination, $user_id]);

        echo "<script>
                alert('プロフィールが更新されました！');
                window.location.href = 'profile.php';
              </script>";
        exit;

    } catch (\PDOException $e) {
        die("エラー: " . $e->getMessage());
    }
} else {
    header("Location: edit_profile.php");
    exit;
}
?>