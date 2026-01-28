<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: auth/login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

// محاولة تحميل functions.php إذا كان موجوداً
$functionsPath = __DIR__ . '/../config/functions.php';
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

// تعريف الدالة إذا لم تكن موجودة
if (!function_exists('generateMembershipNumber')) {
    function generateMembershipNumber($pdo) {
        try {
            $stmt = $pdo->query("SELECT membership_number FROM persons WHERE membership_number IS NOT NULL AND membership_number != '' ORDER BY CAST(membership_number AS UNSIGNED) DESC LIMIT 1");
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($last && !empty($last['membership_number'])) {
                $lastNumber = (int)$last['membership_number'];
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }
            return str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            return '0001';
        }
    }
}

$personId = (int)($_GET['father_id'] ?? $_GET['mother_id'] ?? 0);
if ($personId <= 0) { header("Location: manage_people_new.php"); exit(); }

$stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$person) { header("Location: manage_people_new.php"); exit(); }

// ملاحظة: نسمح بإضافة الأطفال لأي شخص في الشجرة الرئيسية بغض النظر عن الجيل
// التحقق من الشجرة الرئيسية (للأمان فقط، لكن لا نمنع الإضافة)
$mainTree = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$mainTreeId = $mainTree ? (int)$mainTree['id'] : 0;

// نسمح بإضافة الأطفال لأي شخص - لا نمنع بناءً على tree_id
// (يمكن إضافة أطفال لأي جيل في الشجرة الرئيسية)

// Determine father_id and mother_id
$fatherId = null;
$motherId = null;
if (isset($_GET['father_id'])) {
    // Adding child to father
    $fatherId = (int)$person['id'];
    if (!empty($person['spouse_person_id'])) {
        $motherId = (int)$person['spouse_person_id'];
    }
} elseif (isset($_GET['mother_id'])) {
    // Adding child to mother - find her spouse (the father)
    if (!empty($person['spouse_person_id'])) {
        $fatherId = (int)$person['spouse_person_id'];
        $motherId = (int)$person['id'];
    } else {
        // Mother has no spouse - this shouldn't happen in internal marriage, but handle it
        header("Location: manage_people_new.php");
        exit();
    }
}

// Get father's data for display
$father = $person;
if ($fatherId && $fatherId != $personId) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
    $stmt->execute([$fatherId]);
    $father = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$father) { header("Location: manage_people_new.php"); exit(); }
}

$error = "";
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $birth = !empty(trim($_POST['birth_date'] ?? '')) ? trim($_POST['birth_date']) : null;
    $deathDate = !empty(trim($_POST['death_date'] ?? '')) ? trim($_POST['death_date']) : null;
    $residence = !empty(trim($_POST['residence_location'] ?? '')) ? trim($_POST['residence_location']) : null;
    $username = !empty(trim($_POST['username'] ?? '')) ? trim($_POST['username']) : null;
    $password = trim($_POST['password'] ?? '');
    $notes = !empty(trim($_POST['notes'] ?? '')) ? trim($_POST['notes']) : null;

    if ($name==='') $error="الاسم مطلوب";
    elseif ($fatherId === null) $error="لا يمكن إضافة طفل بدون أب";
    else {
        // التحقق من اسم المستخدم إذا تم إدخاله
        if (!empty($username)) {
            $checkUser = $pdo->prepare("SELECT id FROM persons WHERE username = ? LIMIT 1");
            $checkUser->execute([$username]);
            if ($checkUser->fetch()) {
                $error = "اسم المستخدم موجود بالفعل";
            }
        }
        
        if (empty($error)) {
            // generation = father + 1
            $gen = (int)$father['generation_level'] + 1;
            
            // توليد رقم العضوية
            $membershipNumber = generateMembershipNumber($pdo);

            // معالجة رفع الصورة
            $photoPath = null;
            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/persons/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExt, $allowedExts)) {
                    $fileName = uniqid('person_', true) . '.' . $fileExt;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                        $photoPath = 'admin/uploads/persons/' . $fileName;
                    }
                }
            }
            
            // تشفير كلمة المرور إذا تم إدخالها
            $passwordHash = null;
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            }

            $ins = $pdo->prepare("INSERT INTO persons(tree_id,membership_number,full_name,gender,birth_date,death_date,residence_location,username,password_hash,notes,father_id,mother_id,generation_level,is_root,photo_path)
                                  VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)");
            $ins->execute([
                (int)$father['tree_id'], $membershipNumber, $name, $gender, $birth, $deathDate, $residence,
                $username, $passwordHash, $notes, $fatherId, $motherId, $gen, $photoPath
            ]);

            header("Location: manage_people_new.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة ابن/ابنة</title>
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

input, select, textarea {
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

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #c4a77d;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(196, 167, 125, 0.1);
}

textarea {
    resize: vertical;
    min-height: 80px;
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

.optional {
    color: #999;
    font-size: 12px;
    font-weight: normal;
}
</style>
</head>
<body>
<div class="glass-box">
  <h2>إضافة ابن/ابنة</h2>
  <div class="info-text">
    سيتم ربط الطفل تلقائيًا بالأب: <b><?= htmlspecialchars($father['full_name']) ?></b> (جيل <?= (int)$father['generation_level'] ?>)
  </div>

  <?php if($error): ?>
    <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <label>الاسم الكامل <span style="color:red;">*</span></label>
    <input name="full_name" placeholder="اسم الابن/الابنة" required>
    
    <label>الجنس <span style="color:red;">*</span></label>
    <select name="gender" required>
      <option value="male">ذكر</option>
      <option value="female">أنثى</option>
    </select>
    
    <label>تاريخ الميلاد <span class="optional">(اختياري)</span></label>
    <input type="date" name="birth_date">
    
    <label>تاريخ الوفاة <span class="optional">(اختياري - للإدمن فقط)</span></label>
    <input type="date" name="death_date">
    
    <label>مكان الإقامة <span class="optional">(اختياري)</span></label>
    <input name="residence_location" placeholder="مكان الإقامة">
    
    <label>اسم المستخدم <span class="optional">(اختياري - للعضو)</span></label>
    <input name="username" placeholder="اسم المستخدم">
    
    <label>كلمة المرور <span class="optional">(اختياري - للعضو)</span></label>
    <input type="password" name="password" placeholder="كلمة المرور">
    
    <label>ملاحظات <span class="optional">(اختياري)</span></label>
    <textarea name="notes" placeholder="ملاحظات عن الشخص"></textarea>
    
    <label>الصورة <span class="optional">(اختياري)</span></label>
    <input type="file" name="photo" accept="image/*">
    
    <button type="submit">حفظ</button>
  </form>
</div>
</body>
</html>
