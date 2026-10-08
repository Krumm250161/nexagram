<?php
// lang.php - 多言語辞書ファイル
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 言語切り替え処理 (URLパラメータ ?lang=ja または ?lang=en)
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    if (in_array($lang, ['ja', 'en'])) {
        $_SESSION['lang'] = $lang;
    }
}

// デフォルト言語の設定 (未設定の場合は 'ja')
$current_lang = $_SESSION['lang'] ?? 'ja';

// 翻訳データ辞書
$translations = [
    'ja' => [
        // ナビゲーション
        'nav_home' => 'ホーム',
        'nav_friends' => '接続',
        'nav_users' => 'ユーザー',
        'nav_messages' => 'メッセージ',
        'nav_create' => '作成',
        'nav_profile' => 'プロフィール',
        'nav_logout' => 'ログアウト',
        'dark_mode' => 'ダークモード',
        'light_mode' => 'ライトモード',

        // タイムライン / フィード (index.php)
        'like_button' => 'いいね！',
        'comment_button' => 'コメント',
        'likes_label' => '件のいいね',
        'comments_label' => '件のコメント',
        'comments_title' => 'コメント',
        'reply_to' => '返信先',
        'btn_reply' => '返信',
        'no_comments' => 'コメントはまだありません。',
        'add_comment_placeholder' => 'コメントを追加...',
        'post_comment_btn' => '投稿',

        // 登録画面 (register.php)
        'reg_subtitle' => 'キャンパスの仲間とつながり、最新の学内情報をシェアしよう。',
        'role_student' => '学生 (Student)',
        'role_teacher' => '教員 (Teacher)',
        'ph_student_id' => 'Student ID (学籍番号)',
        'ph_teacher_id' => 'Teacher ID (教員番号)',
        'ph_username' => 'Username (ユーザー名)',
        'ph_email' => 'School Email (学校のメールアドレス)',
        'ph_password' => 'Password (パスワード)',
        'select_dept' => '学科を選択 (Select Department)',
        'btn_signup' => 'Sign up',
        'has_account' => 'アカウントをお持ちですか？',
        'login_link' => 'Log in',

        // 検索画面 (users.php)
        'search_placeholder' => 'ユーザー名や学科名で検索...',
        'tab_all' => '全体',
        'tab_students' => '学生 (Students)',
        'tab_teachers' => '教員 (Teachers)',
        'btn_follow' => 'フォロー',
        'btn_following' => 'フォロー中',
        'btn_add_friend' => '友達追加',
        'btn_pending' => '申請中',
        'btn_friends' => '友達',
        'no_users' => '該当するユーザーが見つかりませんでした。',

        // 接続画面 (friends.php)
        'network_title' => 'ピープル＆ネットワーク',
        'search_connections_placeholder' => '名前で接続を検索...',
        'tab_friends' => '友達',
        'tab_requests' => 'リクエスト',
        'tab_following' => 'フォロー中',
        'tab_followers' => 'フォロワー',
        'follows_you' => 'フォローされています',
        'btn_unfriend' => '友達解除',
        'btn_follow_back' => 'フォロー返す',
        'btn_accept' => '承認',
        'btn_reject' => '削除',
        'confirm_unfriend' => '本当にこのユーザーの友達登録を解除しますか？',

        // プロフィール画面 (profile.php)
        'edit_profile' => 'プロフィールを編集',
        'stat_posts' => '投稿',
        'stat_followers' => 'フォロワー',
        'stat_following' => 'フォロー中',
        'stat_friends' => '友達',
        'edit_caption_title' => 'キャプションを編集',
        'write_caption_placeholder' => 'キャプションを書く...',
        'btn_cancel' => 'キャンセル',
        'btn_save' => '保存',
        'confirm_delete_post' => 'この投稿を削除してもよろしいですか？',
        'delete_post_tooltip' => '投稿を削除',
        'edit_caption_tooltip' => 'キャプションを編集',

        // 投稿作成画面 (create_post.php)
        'create_post_title' => '新規投稿を作成',
        'dropzone_title' => '写真や動画をドラッグ＆ドロップ',
        'dropzone_subtitle' => '複数の写真を選択可能 (PNG, JPG, MP4)',
        'add_more_photos' => '写真を追加',
        'caption_label' => 'キャプション',
        'btn_share' => 'シェアする',
        'err_invalid_format' => '対応していないファイル形式が含まれています。(PNG, JPG, MP4など)',
        'err_empty_post' => 'キャプションまたは画像/動画のいずれかを入力してください。'
    ],
    'en' => [
        // Navigation
        'nav_home' => 'Home',
        'nav_friends' => 'Connections',
        'nav_users' => 'Users',
        'nav_messages' => 'Messages',
        'nav_create' => 'Create',
        'nav_profile' => 'Profile',
        'nav_logout' => 'Logout',
        'dark_mode' => 'Dark Mode',
        'light_mode' => 'Light Mode',

        // Timeline / Feed (index.php)
        'like_button' => 'Like',
        'comment_button' => 'Comment',
        'likes_label' => 'likes',
        'comments_label' => 'comments',
        'comments_title' => 'Comments',
        'reply_to' => 'Replying to',
        'btn_reply' => 'Reply',
        'no_comments' => 'No comments yet.',
        'add_comment_placeholder' => 'Add a comment...',
        'post_comment_btn' => 'Post',

        // Register Page
        'reg_subtitle' => 'Connect with campus peers and share the latest updates.',
        'role_student' => 'Student',
        'role_teacher' => 'Teacher',
        'ph_student_id' => 'Student ID',
        'ph_teacher_id' => 'Teacher ID',
        'ph_username' => 'Username',
        'ph_email' => 'School Email',
        'ph_password' => 'Password',
        'select_dept' => 'Select Department',
        'btn_signup' => 'Sign Up',
        'has_account' => 'Already have an account?',
        'login_link' => 'Log In',

        // Users Page
        'search_placeholder' => 'Search by username or department...',
        'tab_all' => 'All',
        'tab_students' => 'Students',
        'tab_teachers' => 'Teachers',
        'btn_follow' => 'Follow',
        'btn_following' => 'Following',
        'btn_add_friend' => 'Add Friend',
        'btn_pending' => 'Pending',
        'btn_friends' => 'Friends',
        'no_users' => 'No users found.',

        // Connections Page (friends.php)
        'network_title' => 'People & Network',
        'search_connections_placeholder' => 'Search connections by name...',
        'tab_friends' => 'Friends',
        'tab_requests' => 'Requests',
        'tab_following' => 'Following',
        'tab_followers' => 'Followers',
        'follows_you' => 'Follows You',
        'btn_unfriend' => 'Unfriend',
        'btn_follow_back' => 'Follow Back',
        'btn_accept' => 'Accept',
        'btn_reject' => 'Delete',
        'confirm_unfriend' => 'Are you sure you want to unfriend this user?',

        // Profile Page
        'edit_profile' => 'Edit Profile',
        'stat_posts' => 'Posts',
        'stat_followers' => 'Followers',
        'stat_following' => 'Following',
        'stat_friends' => 'Friends',
        'edit_caption_title' => 'Edit Caption',
        'write_caption_placeholder' => 'Write a caption...',
        'btn_cancel' => 'Cancel',
        'btn_save' => 'Save',
        'confirm_delete_post' => 'Are you sure you want to delete this post?',
        'delete_post_tooltip' => 'Delete Post',
        'edit_caption_tooltip' => 'Edit Caption',

        // Create Post Page (create_post.php)
        'create_post_title' => 'Create New Post',
        'dropzone_title' => 'Drag & drop photos or videos',
        'dropzone_subtitle' => 'Multiple photos allowed (PNG, JPG, MP4)',
        'add_more_photos' => 'Add More',
        'caption_label' => 'Caption',
        'btn_share' => 'Share',
        'err_invalid_format' => 'Unsupported file format included. (PNG, JPG, MP4, etc.)',
        'err_empty_post' => 'Please enter a caption or upload image/video.'
    ]
];

// 翻訳用ヘルパー関数
function __($key) {
    global $translations, $current_lang;
    return $translations[$current_lang][$key] ?? $key;
}
?>