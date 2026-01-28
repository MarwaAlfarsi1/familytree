<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$person_id = isset($_GET['person_id']) ? (int)$_GET['person_id'] : 0;
$tree_id   = isset($_GET['tree_id']) ? (int)$_GET['tree_id'] : 0;

if ($person_id <= 0) {
    header("Location: manage_people_new.php");
    exit();
}

// إذا لم يُرسل tree_id: استخرجه من جدول persons
if ($tree_id <= 0) {
    $st = $pdo->prepare("SELECT tree_id FROM persons WHERE id=? LIMIT 1");
    $st->execute([$person_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: manage_people_new.php");
        exit();
    }
    $tree_id = (int)$row['tree_id'];
}

$st = $pdo->prepare("SELECT id, tree_id, full_name, gender, spouse_person_id, spouse_is_external, external_tree_id FROM persons WHERE id=? AND tree_id=? LIMIT 1");
$st->execute([$person_id, $tree_id]);
$person = $st->fetch(PDO::FETCH_ASSOC);

if (!$person) {
    header("Location: manage_people_new.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>زواج خارجي</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.glass-box {
    width: 100%;
    max-width: 500px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

h2 {
    color: #3c2f2f;
    font-size: 24px;
    margin-bottom: 10px;
    text-align: center;
    font-weight: 700;
}

.info-text {
    color: #6b543f;
    font-size: 13px;
    margin-bottom: 20px;
    text-align: center;
    padding: 10px;
    background: rgba(196, 167, 125, 0.1);
    border-radius: 8px;
}

label {
    display: block;
    margin: 15px 0 5px;
    color: #6b543f;
    font-size: 14px;
    font-weight: 600;
}

input, select {
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 10px;
    border-radius: 10px;
    border: 1px solid rgba(191, 169, 138, 0.5);
    font-size: 15px;
    background: rgba(255, 255, 255, 0.9);
    transition: all 0.3s;
    font-family: 'Cairo', sans-serif;
}

input:focus, select:focus {
    outline: none;
    border-color: #c4a77d;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(196, 167, 125, 0.1);
}

input[type="file"] {
    padding: 8px;
}

button {
    width: 100%;
    padding: 14px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
    margin-top: 10px;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
}

a {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #6b543f;
    text-decoration: none;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.3s;
}

a:hover {
    background: rgba(196, 167, 125, 0.1);
}

.optional {
    color: #999;
    font-size: 12px;
    font-weight: normal;
}

.err {
    background: rgba(255, 236, 236, 0.9);
    color: #c40000;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid rgba(196, 0, 0, 0.2);
}

@media (max-width: 480px) {
    .glass-box {
        padding: 25px;
    }
    
    h2 {
        font-size: 22px;
    }
}
</style>
</head>
<body>

<div class="glass-box">
    <h2>زواج خارجي</h2>
    <div class="info-text">
        الشخص: <b><?= h($person['full_name']) ?></b>
    </div>

    <form method="POST" action="person_add_spouse_external_process.php" enctype="multipart/form-data">
        <input type="hidden" name="person_id" value="<?= (int)$person_id ?>">
        <input type="hidden" name="tree_id" value="<?= (int)$tree_id ?>">

        <label>اسم الزوج/الزوجة (خارج العائلة) <span style="color:red;">*</span></label>
        <input type="text" name="spouse_full_name" required placeholder="اكتب الاسم الكامل">

        <label>الجنس <span style="color:red;">*</span></label>
        <select name="spouse_gender" required>
            <option value="male">ذكر</option>
            <option value="female">أنثى</option>
        </select>

        <label>صورة الزوج/الزوجة <span class="optional">(اختياري)</span></label>
        <input type="file" name="spouse_photo" accept="image/*">

        <button type="submit">حفظ</button>
    </form>

    <a href="manage_people_new.php">رجوع</a>
</div>

</body>
</html>
