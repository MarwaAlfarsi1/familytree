<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id'])) {
    die('يجب تسجيل الدخول كإدمن');
}

$messages = [];

try {
    // التحقق من وجود عمود username
    $checkUsername = $pdo->query("SHOW COLUMNS FROM persons LIKE 'username'");
    if ($checkUsername->rowCount() == 0) {
        $pdo->exec("ALTER TABLE persons ADD COLUMN username VARCHAR(255) NULL");
        $messages[] = "✓ تم إضافة عمود username";
    } else {
        $messages[] = "✓ عمود username موجود بالفعل";
    }
    
    // التحقق من وجود عمود password_hash
    $checkPassword = $pdo->query("SHOW COLUMNS FROM persons LIKE 'password_hash'");
    if ($checkPassword->rowCount() == 0) {
        $pdo->exec("ALTER TABLE persons ADD COLUMN password_hash VARCHAR(255) NULL");
        $messages[] = "✓ تم إضافة عمود password_hash";
    } else {
        $messages[] = "✓ عمود password_hash موجود بالفعل";
    }
    
    $success = true;
} catch (Exception $e) {
    $messages[] = "✗ خطأ: " . $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة أعمدة الحسابات</title>
<style>
body { 
    font-family: 'Cairo', sans-serif; 
    padding: 20px; 
    background: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    max-width: 500px;
    width: 100%;
}
h1 {
    color: #3c2f2f;
    margin-bottom: 20px;
    text-align: center;
}
.message {
    padding: 12px;
    margin: 10px 0;
    border-radius: 8px;
    background: <?= $success ? '#d4edda' : '#f8d7da' ?>;
    color: <?= $success ? '#155724' : '#721c24' ?>;
    border: 1px solid <?= $success ? '#c3e6cb' : '#f5c6cb' ?>;
}
a {
    display: block;
    margin-top: 20px;
    padding: 12px 20px;
    background: #3c2f2f;
    color: #f2c200;
    text-decoration: none;
    border-radius: 8px;
    text-align: center;
    transition: all 0.3s;
}
a:hover {
    background: #2a2222;
    transform: translateY(-2px);
}
</style>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>إضافة أعمدة الحسابات</h1>
    <?php foreach ($messages as $msg): ?>
        <div class="message"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>
    <a href="dashboard_new.php">رجوع للوحة التحكم</a>
</div>
</body>
</html>