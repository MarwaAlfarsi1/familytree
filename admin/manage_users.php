<?php
session_start();
require_once '../auth/check_login.php'; // التأكد من أن الأدمن داخل
require_once '../../config/db.php';

$message = "";

// جلب المستخدمين
$users = $pdo->query("SELECT users.*, members.full_name AS member_name 
                      FROM users 
                      LEFT JOIN members ON users.member_id = members.id
                      ORDER BY users.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// جلب أفراد العائلة لعرضهم في القائمة لاختيار العضو المرتبط
$members = $pdo->query("SELECT id, full_name FROM members ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// إضافة مستخدم جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $member_id = intval($_POST['member_id']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, member_id, is_admin) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $email, $password, $member_id, $is_admin]);

    $message = "تمت إضافة المستخدم بنجاح";
    header("Refresh:0");
    exit;
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة المستخدمين</title>
<style>
body {
    font-family: 'Cairo', sans-serif;
    background: #f4f4f4;
}
.container {
    width: 90%;
    margin: auto;
    margin-top: 30px;
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.10);
}
h2 {
    font-size: 26px;
    margin-bottom: 20px;
    color: #2c3e50;
}
button, input, select {
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
}
.add-button {
    background: #2a6f54;
    color: #fff;
    font-weight: bold;
    border: none;
    cursor: pointer;
}
.add-button:hover {
    background: #1d5240;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: center;
}
th {
    background: #e3e3e3;
}
.form-box {
    margin-top: 20px;
    background: #fafafa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #ddd;
}
.success {
    background: #e8ffea;
    color: #007f00;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 8px;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="container">

    <h2>إدارة المستخدمين</h2>

    <?php if ($message): ?>
    <div class="success"><?php echo $message; ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>الاسم</th>
            <th>البريد</th>
            <th>العضو المرتبط</th>
            <th>صلاحيات</th>
        </tr>
        <?php foreach($users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= $u['member_name'] ?: 'غير مرتبط' ?></td>
            <td><?= $u['is_admin'] ? 'مشرف' : 'مستخدم' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="form-box">
        <h3>إضافة مستخدم جديد</h3>
        <form method="POST">
            <input type="text" name="full_name" placeholder="اسم المستخدم" required>
            <input type="email" name="email" placeholder="البريد الإلكتروني" required>
            <input type="password" name="password" placeholder="كلمة المرور" required>

            <select name="member_id" required>
                <option value="">اختر عضو العائلة</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>
                <input type="checkbox" name="is_admin"> مشرف
            </label>

            <br><br>
            <button type="submit" class="add-button">إضافة</button>
        </form>
    </div>

</div>

</body>
</html>
