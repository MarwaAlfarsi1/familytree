<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_id'])) {
  header("Location: auth/login.php");
  exit();
}

require_once __DIR__ . '/../config/db.php';

// الشجرة الرئيسية
$mainTree = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$mainTreeId = $mainTree ? (int)$mainTree['id'] : 0;

// البنات فقط من persons داخل الشجرة الرئيسية
$girlsStmt = $pdo->prepare("SELECT id, full_name FROM persons WHERE tree_id=? AND gender='female' ORDER BY id ASC");
$girlsStmt->execute([$mainTreeId]);
$girls = $girlsStmt->fetchAll(PDO::FETCH_ASSOC);

// كل أفراد العائلة (للزواج الداخلي)
$peopleStmt = $pdo->prepare("SELECT id, full_name FROM persons WHERE tree_id=? ORDER BY id ASC");
$peopleStmt->execute([$mainTreeId]);
$people = $peopleStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>زواج البنات</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;800&display=swap" rel="stylesheet">
<style>
body{margin:0;background:#f5e9d5;font-family:'Cairo',sans-serif;padding:24px}
.card{max-width:760px;margin:auto;background:#fff;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,.12);padding:22px}
select,input,textarea{width:100%;padding:12px;border-radius:12px;border:1.5px solid #d8c4a2;margin:10px 0;font-size:16px}
button{width:100%;padding:12px;border-radius:12px;border:none;background:#3c2f2f;color:#f2c200;font-weight:800;font-size:18px;cursor:pointer}
a{display:block;text-align:center;margin-top:12px;color:#3c2f2f;font-weight:800;text-decoration:none}
.small{color:#6b543f;font-weight:800;font-size:13px}
</style>
</head>
<body>
<div class="card">
  <h2 style="margin:0 0 8px">زواج البنات (داخلي/خارجي)</h2>
  <div class="small">إذا كان الزوج من العائلة اختاريه من القائمة. إذا كان من خارج العائلة اكتبي الاسم فقط.</div>

  <form method="post" action="add_spouse_process.php">
    <label>اختيار البنت</label>
    <select name="girl_id" required>
      <option value="">— اختيار —</option>
      <?php foreach($girls as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['full_name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>زوج من العائلة (اختياري)</label>
    <select name="spouse_person_id">
      <option value="">— ليس من العائلة —</option>
      <?php foreach($people as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>اسم الزوج (إذا كان من خارج العائلة)</label>
    <input name="spouse_name" placeholder="اكتب الاسم هنا عند الزواج الخارجي">

    <label>أسماء الأبناء (اختياري - للعرض فقط)</label>
    <textarea name="external_children" rows="3" placeholder="مثال: أحمد، محمد، ..."></textarea>

    <button type="submit">حفظ</button>
  </form>

  <a href="dashboard_new.php">عودة للوحة التحكم</a>
</div>
</body>
</html>
