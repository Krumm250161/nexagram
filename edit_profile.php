<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$success = false;

// လက်ရှိ User အချက်အလက်ယူမည်
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $profile_image_path = $user['profile_image']; // မူလ ပုံလမ်းကြောင်း ထိန်းထားမည်

    // 1. Profile Picture Update ပြုလုပ်ခြင်း
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = $_FILES['profile_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            // Folder မရှိပါက အလိုအလျောက် ဆောက်မည်
            $uploadFileDir = 'uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // ဖိုင်နာမည် မထပ်အောင် နာမည်အသစ်ပေးမည်
            $newFileName = 'avatar_' . $user_id . '_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // မူလပုံဟောင်းရှိရင် Server ပေါ်မှ ဖျက်မည်
                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                    unlink($user['profile_image']);
                }
                $profile_image_path = $dest_path;
            } else {
                $message = 'ပုံတင်ရာတွင် အမှားအယွင်း ရှိနေပါသည်။';
            }
        } else {
            $message = 'JPG, JPEG, PNG, GIF, WEBP ဖိုင်များကိုသာ လက်ခံပါသည်။';
        }
    }

    // 2. Database တွင် အချက်အလက်များ Update လုပ်မည်
    if (empty($message)) {
        if (!empty($new_username)) {
            $update_stmt = $pdo->prepare("UPDATE users SET username = ?, profile_image = ? WHERE id = ?");
            if ($update_stmt->execute([$new_username, $profile_image_path, $user_id])) {
                $_SESSION['username'] = $new_username;
                header("Location: profile.php");
                exit();
            } else {
                $message = 'Database Update ပြုလုပ်ခြင်း မအောင်မြင်ပါ။';
            }
        } else {
            $message = 'ကျေးဇူးပြု၍ Username ထည့်ပေးပါ။';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile • Nexagram</title>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --border-color: #dbdbdb;
            --card-bg: #ffffff;
            --input-bg: #fafafa;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #121212;
                --text-color: #f5f5f5;
                --border-color: #262626;
                --card-bg: #1e1e1e;
                --input-bg: #262626;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .edit-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 360px;
            box-sizing: border-box;
        }

        .edit-box h3 {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }

        .avatar-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }

        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            background-color: var(--border-color);
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 32px;
        }

        .file-input-label {
            color: #0095f6;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .file-input-label:hover {
            color: #00376b;
        }

        input[type="file"] {
            display: none;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .input-group input[type="text"] {
            width: 100%;
            padding: 10px;
            background-color: var(--input-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
        }

        .save-btn {
            background-color: #0095f6;
            color: white;
            border: none;
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }

        .save-btn:hover {
            background-color: #1877f2;
        }

        .cancel-btn {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--text-color);
            text-decoration: none;
            font-size: 14px;
            opacity: 0.7;
        }

        .error-msg {
            color: #ed4956;
            font-size: 14px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="edit-box">
        <h3>Edit Profile</h3>

        <?php if ($message): ?>
            <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Form မှာ ပုံတင်နိုင်ရန် enctype="multipart/form-data" မဖြစ်မနေ ပါရပါမည် -->
        <form method="POST" enctype="multipart/form-data">
            <div class="avatar-preview-container">
                <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" id="imgPreview" class="avatar-preview">
                <?php else: ?>
                    <div class="avatar-preview" id="imgPreviewText"><?php echo mb_substr(htmlspecialchars($user['username']), 0, 1, 'UTF-8'); ?></div>
                    <img src="" id="imgPreview" class="avatar-preview" style="display:none;">
                <?php endif; ?>

                <label for="profile_image" class="file-input-label">Change profile photo</label>
                <input type="file" name="profile_image" id="profile_image" accept="image/*" onchange="previewImage(event)">
            </div>

            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <button type="submit" class="save-btn">Save Changes</button>
            <a href="profile.php" class="cancel-btn">Cancel</a>
        </form>
    </div>

    <script>
        // ပုံရွေးလိုက်တာနဲ့ တန်းပြီး Preview ပြပေးမည့် JavaScript
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('imgPreview');
                const textPreview = document.getElementById('imgPreviewText');

                output.src = reader.result;
                output.style.display = 'block';
                if (textPreview) {
                    textPreview.style.display = 'none';
                }
            };
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>

</body>

</html>
