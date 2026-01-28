<?php
session_start();
if (!isset($_SESSION['admin_id'])) { 
    header("Location: auth/login.php"); 
    exit(); 
}

require_once __DIR__ . "/../config/db.php";

$personId = (int)($_GET['person_id'] ?? 0);
if ($personId<=0) { 
    header("Location: manage_people_new.php"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$person){ 
    header("Location: manage_people_new.php"); 
    exit(); 
}

$mainTree = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$mainTreeId = $mainTree ? (int)$mainTree['id'] : 0;
if ((int)$person['tree_id'] !== $mainTreeId) { 
    header("Location: manage_people_new.php"); 
    exit(); 
}

$error="";
$spouses = $pdo->prepare("SELECT id,full_name,gender FROM persons WHERE tree_id=? AND id<>? AND spouse_person_id IS NULL AND spouse_is_external=0 ORDER BY generation_level ASC, id ASC");
$spouses->execute([$mainTreeId,$personId]);
$spouses = $spouses->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $spouseId = (int)($_POST['spouse_id'] ?? 0);
    if ($spouseId<=0) {
        $error="اختر الزوج/الزوجة";
    } else {
        // Verify spouse
        $st = $pdo->prepare("SELECT * FROM persons WHERE id=? AND tree_id=? LIMIT 1");
        $st->execute([$spouseId,$mainTreeId]);
        $sp = $st->fetch(PDO::FETCH_ASSOC);
        if(!$sp) {
            $error="الزوج/الزوجة غير صالح";
        } else {
            // Update both sides
            $pdo->prepare("UPDATE persons SET spouse_person_id=?, spouse_is_external=0, external_tree_id=NULL WHERE id=?")->execute([$spouseId,$personId]);
            $pdo->prepare("UPDATE persons SET spouse_person_id=?, spouse_is_external=0, external_tree_id=NULL WHERE id=?")->execute([$personId,$spouseId]);
            $_SESSION['success_message'] = "تم ربط الزوجين بنجاح";
            header("Location: manage_people_new.php");
            exit();
        }
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>زواج داخل العائلة</title>
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

select {
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

select:focus {
    outline: none;
    border-color: #c4a77d;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(196, 167, 125, 0.1);
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

.err {
    background: rgba(255, 236, 236, 0.9);
    color: #c40000;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid rgba(196, 0, 0, 0.2);
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
    <h2>زواج داخل العائلة</h2>
    <div class="info-text">
        ربط: <b><?= h($person['full_name']) ?></b> بزوج/زوجة من نفس الشجرة
    </div>

    <?php if($error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>اختر الزوج/الزوجة <span style="color:red;">*</span></label>
        <select name="spouse_id" required>
            <option value="" disabled selected>اختر الزوج/الزوجة</option>
            <?php foreach($spouses as $s): ?>
                <option value="<?= (int)$s['id'] ?>">
                    <?= h($s['full_name']) ?> (<?= $s['gender']==='male'?'ذكر':'أنثى' ?>)
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit">حفظ الزواج</button>
    </form>
    
    <a href="manage_people_new.php">رجوع</a>
</div>

</body>
</html>
