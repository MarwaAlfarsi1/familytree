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

$tree = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch();
if(!$tree) die("لا توجد شجرة");

$tree_id = (int)$tree['id'];

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birthDate = !empty(trim($_POST['birth_date'] ?? '')) ? trim($_POST['birth_date']) : null;
    $deathDate = !empty(trim($_POST['death_date'] ?? '')) ? trim($_POST['death_date']) : null;
    $residence = !empty(trim($_POST['residence_location'] ?? '')) ? trim($_POST['residence_location']) : null;
    $username = !empty(trim($_POST['username'] ?? '')) ? trim($_POST['username']) : null;
    $password = trim($_POST['password'] ?? '');
    $notes = !empty(trim($_POST['notes'] ?? '')) ? trim($_POST['notes']) : null;

    if ($name === '' || !in_array($gender, ['male','female'])) {
        $error = "الرجاء إدخال جميع البيانات المطلوبة";
    } else {
        // تأكد أنه لا يوجد جد سابق
        $check = $pdo->prepare("SELECT id FROM persons WHERE tree_id=? AND is_root=1");
        $check->execute([$tree_id]);
        if ($check->fetch()) {
            $error = "تم إدخال الجد مسبقًا";
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
                
                $stmt = $pdo->prepare("
                    INSERT INTO persons
                    (tree_id, membership_number, full_name, gender, birth_date, death_date, residence_location, 
                     username, password_hash, notes, is_root, generation_level, photo_path)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?)
                ");
                $stmt->execute([
                    $tree_id, $membershipNumber, $name, $gender, $birthDate, $deathDate, $residence,
                    $username, $passwordHash, $notes, 1, $photoPath
                ]);

                header("Location: dashboard_new.php");
                exit();
            }
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>إضافة الجد المؤسس</title>
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
    margin-bottom: 20px;
    text-align: center;
    font-weight: 700;
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
<h2>إدخال الجد المؤسس</h2>

<?php if($error): ?>
<div class="err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<label>الاسم الكامل <span style="color:red;">*</span></label>
<input name="full_name" placeholder="اسم الجد الكامل" required>

<label>الجنس <span style="color:red;">*</span></label>
<select name="gender" required>
<option value="">اختر الجنس</option>
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
