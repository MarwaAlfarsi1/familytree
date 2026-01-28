<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id'])) {
    die('يجب تسجيل الدخول كإدمن');
}

// جلب جميع الأشخاص الذين لديهم حسابات
$stmt = $pdo->query("SELECT id, full_name, username, password_hash, membership_number 
                     FROM persons 
                     WHERE username IS NOT NULL AND username != '' 
                     ORDER BY id DESC");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>اختبار حسابات الأعضاء</title>
<style>
body { 
    font-family: 'Cairo', sans-serif; 
    padding: 20px; 
    background: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
    min-height: 100vh;
}
h1 {
    color: #3c2f2f;
    margin-bottom: 20px;
}
table { 
    border-collapse: collapse; 
    width: 100%; 
    margin-top: 20px; 
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}
th, td { 
    border: 1px solid #ddd; 
    padding: 12px; 
    text-align: right; 
}
th { 
    background-color: #3c2f2f;
    color: #f2c200;
    font-weight: 600;
}
tr:nth-child(even) {
    background-color: #f9f9f9;
}
.has-password { 
    color: green; 
    font-weight: 600;
}
.no-password { 
    color: red; 
    font-weight: 600;
}
a {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 20px;
    background: #3c2f2f;
    color: #f2c200;
    text-decoration: none;
    border-radius: 8px;
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
<h1>حسابات الأعضاء</h1>
<table>
    <tr>
        <th>ID</th>
        <th>الاسم</th>
        <th>رقم العضوية</th>
        <th>اسم المستخدم</th>
        <th>كلمة المرور</th>
    </tr>
    <?php if (empty($members)): ?>
    <tr>
        <td colspan="5" style="text-align: center; padding: 20px; color: #999;">
            لا توجد حسابات أعضاء
        </td>
    </tr>
    <?php else: ?>
        <?php foreach ($members as $member): ?>
        <tr>
            <td><?= htmlspecialchars($member['id']) ?></td>
            <td><?= htmlspecialchars($member['full_name']) ?></td>
            <td><?= htmlspecialchars($member['membership_number'] ?? 'غير محدد') ?></td>
            <td><?= htmlspecialchars($member['username']) ?></td>
            <td class="<?= !empty($member['password_hash']) ? 'has-password' : 'no-password' ?>">
                <?= !empty($member['password_hash']) ? '✓ موجودة' : '✗ غير موجودة' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
<a href="dashboard_new.php">رجوع للوحة التحكم</a>
</body>
</html>