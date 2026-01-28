<?php
session_start();

// التحقق من تسجيل الدخول (إدمن أو عضو)
$isAdmin = isset($_SESSION['admin_id']);
$isMember = isset($_SESSION['member_id']);

if (!$isAdmin && !$isMember) {
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        header("Location: auth/login_username.php");
    } else {
        header("Location: auth/login.php");
    }
    exit();
}

// تحديد مسار ملفات config
$dbPath = __DIR__ . "/../db.php";
if (!file_exists($dbPath)) {
    $dbPath = dirname(__DIR__) . "/db.php";
}
if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    try {
        $host = "localhost";
        $dbname = "u480768868_family_tree";
        $username = "u480768868_Mmm111999";
        $password = "Mmmm@@999";
        
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );
    } catch (PDOException $e) {
        die("خطأ في الاتصال بقاعدة البيانات");
    }
}

$functionsPath = __DIR__ . "/../functions.php";
if (!file_exists($functionsPath)) {
    $functionsPath = dirname(__DIR__) . "/functions.php";
}
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$personId = (int)($_GET['id'] ?? 0);
if ($personId <= 0) {
    if ($isMember) {
        header("Location: dashboard.php");
    } else {
        header("Location: manage_people_new.php");
    }
    exit();
}

// جلب بيانات الشخص
$stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$person) {
    if ($isMember) {
        header("Location: dashboard.php");
    } else {
        header("Location: manage_people_new.php");
    }
    exit();
}

// إذا كان عضو عادي، التحقق من أنه يحاول تعديل فرد من عائلته فقط
if ($isMember && !$isAdmin) {
    $memberId = (int)$_SESSION['member_id'];
    
    // جلب بيانات العضو
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        header("Location: dashboard.php");
        exit();
    }
    
    // التحقق من أن الشخص هو العضو نفسه أو فرد من عائلته
    $isFamilyMember = false;
    
    // إذا كان الشخص نفسه
    if ($personId == $memberId) {
        $isFamilyMember = true;
    }
    
    // التحقق من أنه زوج/زوجة
    if (!$isFamilyMember) {
        if (($person['spouse_person_id'] == $memberId && empty($person['spouse_is_external'])) ||
            ($member['spouse_person_id'] == $personId && empty($member['spouse_is_external']))) {
            $isFamilyMember = true;
        }
    }
    
    // التحقق من أنه ابن/ابنة
    if (!$isFamilyMember) {
        if (($member['gender'] === 'male' && $person['father_id'] == $memberId) ||
            ($member['gender'] === 'female' && $person['mother_id'] == $memberId)) {
            $isFamilyMember = true;
        }
    }
    
    if (!$isFamilyMember) {
        $_SESSION['error_message'] = "غير مسموح لك بتعديل هذا الشخص!";
        header("Location: dashboard.php");
        exit();
    }
}

$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $birthDate = trim($_POST['birth_date'] ?? '') ?: null;
    $residence = trim($_POST['residence_location'] ?? '') ?: null;
    $phoneNumber = trim($_POST['phone_number'] ?? '') ?: null;
    $birthPlace = trim($_POST['birth_place'] ?? '') ?: null;
    $deathDate = trim($_POST['death_date'] ?? '') ?: null;
    $tribe = trim($_POST['tribe'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;
    
    if (empty($fullName)) {
        $error = "الاسم مطلوب";
    } else {
        try {
            // معالجة رفع الصورة
            $photoPath = $person['photo_path'];
            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/persons/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExt, $allowedExts)) {
                    // حذف الصورة القديمة إن وجدت
                    if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)) {
                        @unlink(__DIR__ . '/../' . $photoPath);
                    }
                    
                    $fileName = uniqid('person_', true) . '.' . $fileExt;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                        $photoPath = 'admin/uploads/persons/' . $fileName;
                    }
                }
            }
            
            // تحديث البيانات
            $updateStmt = $pdo->prepare("UPDATE persons SET 
                full_name = ?, 
                gender = ?, 
                birth_date = ?, 
                residence_location = ?,
                phone_number = ?,
                birth_place = ?,
                death_date = ?,
                tribe = ?,
                notes = ?,
                photo_path = ?
                WHERE id = ?");
            
            $updateStmt->execute([
                $fullName,
                $gender,
                $birthDate,
                $residence,
                $phoneNumber,
                $birthPlace,
                $deathDate,
                $tribe,
                $notes,
                $photoPath,
                $personId
            ]);
            
            $success = true;
            $_SESSION['success_message'] = "تم تحديث البيانات بنجاح!";
            
            if ($isMember) {
                header("Location: member_profile.php?id=" . $personId);
            } else {
                header("Location: member_profile.php?id=" . $personId);
            }
            exit();
            
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء التحديث: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل بيانات <?= h($person['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #3c2f2f;
            --accent: #f2c200;
            --line: #c4a77d;
            --bg: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        input[type="date"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--line);
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(242, 194, 0, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--accent);
            box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary);
            border: 1px solid rgba(191, 169, 138, 0.5);
        }

        .btn-secondary:hover {
            background: #fff;
            border-color: var(--line);
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }

        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <h1><i class="fas fa-edit"></i> تعديل بيانات <?= h($person['full_name']) ?></h1>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>الاسم الكامل <span style="color:red;">*</span></label>
                    <input type="text" name="full_name" value="<?= h($person['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>الجنس <span style="color:red;">*</span></label>
                    <select name="gender" required>
                        <option value="male" <?= $person['gender'] === 'male' ? 'selected' : '' ?>>ذكر</option>
                        <option value="female" <?= $person['gender'] === 'female' ? 'selected' : '' ?>>أنثى</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>تاريخ الميلاد</label>
                    <input type="date" name="birth_date" value="<?= h($person['birth_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>مكان الميلاد</label>
                    <input type="text" name="birth_place" value="<?= h($person['birth_place'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>تاريخ الوفاة</label>
                    <input type="date" name="death_date" value="<?= h($person['death_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>مكان الإقامة</label>
                    <input type="text" name="residence_location" value="<?= h($person['residence_location'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="tel" name="phone_number" value="<?= h($person['phone_number'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>القبيلة</label>
                    <input type="text" name="tribe" value="<?= h($person['tribe'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>الصورة (اختياري)</label>
                    <input type="file" name="photo" accept="image/*">
                    <?php if (!empty($person['photo_path'])): ?>
                        <p style="margin-top: 8px; font-size: 13px; color: #666;">
                            <i class="fas fa-image"></i> صورة حالية موجودة
                        </p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes"><?= h($person['notes'] ?? '') ?></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                    <a href="member_profile.php?id=<?= $personId ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>