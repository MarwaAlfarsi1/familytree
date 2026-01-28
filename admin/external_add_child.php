<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

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

$treeId = (int)($_GET['tree_id'] ?? 0);
$fatherId = (int)($_GET['father_id'] ?? 0);

if ($treeId <= 0 || $fatherId <= 0) {
    header("Location: manage_people_new.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? AND tree_id=? LIMIT 1");
$stmt->execute([$fatherId, $treeId]);
$father = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$father) {
    header("Location: manage_people_new.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $birth = !empty(trim($_POST['birth_date'] ?? '')) ? trim($_POST['birth_date']) : null;
    $deathDate = !empty(trim($_POST['death_date'] ?? '')) ? trim($_POST['death_date']) : null;
    $residence = !empty(trim($_POST['residence_location'] ?? '')) ? trim($_POST['residence_location']) : null;
    $username = !empty(trim($_POST['username'] ?? '')) ? trim($_POST['username']) : null;
    $password = trim($_POST['password'] ?? '');
    $notes = !empty(trim($_POST['notes'] ?? '')) ? trim($_POST['notes']) : null;

    if ($name === '') {
        $error = "الاسم مطلوب";
    } else {
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
                $treeId, $membershipNumber, $name, $gender, $birth, $deathDate, $residence,
                $username, $passwordHash, $notes, $fatherId, null, $gen, $photoPath
            ]);

            header("Location: external_family.php?tree_id=" . $treeId);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة ابن/ابنة للعائلة الخارجية</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    margin: 0;
    background: #f5e9d5;
    font-family: 'Cairo', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
}

.box {
    background: #fff;
    padding: 26px;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,.12);
    width: 100%;
    max-width: 440px;
    text-align: center;
}

input, select, textarea, button {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border-radius: 10px;
    border: 1px solid #c48d59;
    font-size: 15px;
    box-sizing: border-box;
    font-family: inherit;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

button {
    background: #2f4b3c;
    color: #fff;
    font-weight: 900;
    border: none;
    cursor: pointer;
}

button:hover {
    opacity: .93;
}

.err {
    background: #ffe5e5;
    color: #b30000;
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 10px;
}

.small {
    color: #6b543f;
    font-size: 13px;
    margin-bottom: 15px;
}

label {
    display: block;
    margin: 10px 0 5px;
    color: #6b543f;
    font-size: 13px;
    text-align: right;
}
</style>
</head>
<body>
<div class="box">
  <h2 style="margin:0 0 10px;color:#4b2e1e">إضافة ابن/ابنة للعائلة الخارجية</h2>
  <div class="small">سيتم ربط الطفل تلقائيًا بالأب: <b><?= htmlspecialchars($father['full_name']) ?></b></div>

  <?php if($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <label>الاسم الكامل <span style="color:red;">*</span></label>
    <input name="full_name" placeholder="اسم الابن/الابنة" required>
    
    <label>الجنس <span style="color:red;">*</span></label>
    <select name="gender" required>
      <option value="male">ذكر</option>
      <option value="female">أنثى</option>
    </select>
    
    <label>تاريخ الميلاد <span style="color:#999;font-size:12px;">(اختياري)</span></label>
    <input type="date" name="birth_date">
    
    <label>تاريخ الوفاة <span style="color:#999;font-size:12px;">(اختياري - للإدمن فقط)</span></label>
    <input type="date" name="death_date">
    
    <label>مكان الإقامة <span style="color:#999;font-size:12px;">(اختياري)</span></label>
    <input name="residence_location" placeholder="مكان الإقامة">
    
    <label>اسم المستخدم <span style="color:#999;font-size:12px;">(اختياري - للعضو)</span></label>
    <input name="username" placeholder="اسم المستخدم">
    
    <label>كلمة المرور <span style="color:#999;font-size:12px;">(اختياري - للعضو)</span></label>
    <input type="password" name="password" placeholder="كلمة المرور">
    
    <label>ملاحظات <span style="color:#999;font-size:12px;">(اختياري)</span></label>
    <textarea name="notes" placeholder="ملاحظات عن الشخص"></textarea>
    
    <label>الصورة <span style="color:#999;font-size:12px;">(اختياري)</span></label>
    <input type="file" name="photo" accept="image/*" style="padding:8px;">
    <button type="submit">حفظ</button>
  </form>
  
  <a href="external_family.php?tree_id=<?= $treeId ?>" style="display:block;margin-top:15px;color:#6b543f;text-decoration:none;">رجوع</a>
</div>
</body>
</html>


