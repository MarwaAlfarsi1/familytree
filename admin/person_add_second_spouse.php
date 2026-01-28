<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit();
}

$error = "";
$pdo = null;

// محاولة الاتصال بقاعدة البيانات
try {
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
} catch (Exception $e) {
    $error = "خطأ في الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage());
}

$personId = (int)($_GET['person_id'] ?? 0);
if ($personId <= 0) {
    header("Location: manage_people_new.php");
    exit();
}

$person = null;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
        $stmt->execute([$personId]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "خطأ في جلب بيانات الشخص: " . htmlspecialchars($e->getMessage());
    }
}

if (!$person || $person['gender'] !== 'female') {
    header("Location: manage_people_new.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $spouseName = trim($_POST['spouse_name'] ?? '');
    $isExternal = isset($_POST['is_external']) ? 1 : 0;
    $spousePersonId = null;
    $externalTreeId = null;
    
    if (empty($spouseName)) {
        $error = "اسم الزوج مطلوب";
    } else {
        try {
            if ($isExternal) {
                // زوج خارجي - إنشاء شجرة خارجية جديدة
                // التحقق من بنية جدول trees أولاً
                $checkTreeColumns = $pdo->query("SHOW COLUMNS FROM trees");
                $treeColumns = [];
                while ($col = $checkTreeColumns->fetch(PDO::FETCH_ASSOC)) {
                    $treeColumns[] = $col['Field'];
                }
                
                if (in_array('name', $treeColumns)) {
                    $treeStmt = $pdo->prepare("INSERT INTO trees (tree_type, name) VALUES ('external', ?)");
                    $treeStmt->execute([$spouseName . ' - عائلة خارجية']);
                } elseif (in_array('title', $treeColumns)) {
                    $treeStmt = $pdo->prepare("INSERT INTO trees (tree_type, title) VALUES ('external', ?)");
                    $treeStmt->execute([$spouseName . ' - عائلة خارجية']);
                } else {
                    // إذا لم يكن هناك عمود name أو title، استخدم tree_type فقط
                    $treeStmt = $pdo->prepare("INSERT INTO trees (tree_type) VALUES ('external')");
                    $treeStmt->execute();
                }
                $externalTreeId = $pdo->lastInsertId();
                
                // إنشاء شخص الزوج الخارجي
                // استخدام نفس جيل المرأة (الزوج الثاني يجب أن يكون في نفس الجيل)
                $spouseGenerationLevel = (int)($person['generation_level'] ?? 1);
                $membershipNumber = generateMembershipNumber($pdo);
                $spouseStmt = $pdo->prepare("INSERT INTO persons (tree_id, membership_number, full_name, gender, is_root, generation_level) 
                                           VALUES (?, ?, ?, 'male', 1, ?)");
                $spouseStmt->execute([$externalTreeId, $membershipNumber, $spouseName, $spouseGenerationLevel]);
                $spousePersonId = $pdo->lastInsertId();
            } else {
                // زوج داخلي - البحث عن الشخص
                $findStmt = $pdo->prepare("SELECT id FROM persons WHERE full_name = ? AND gender = 'male' LIMIT 1");
                $findStmt->execute([$spouseName]);
                $found = $findStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$found) {
                    $error = "لم يتم العثور على شخص بهذا الاسم في العائلة";
                } else {
                    $spousePersonId = (int)$found['id'];
                }
            }
            
            if (empty($error)) {
                // التحقق من وجود الأعمدة
                try {
                    $checkColumns = $pdo->query("SHOW COLUMNS FROM persons LIKE 'second_spouse_person_id'");
                    if ($checkColumns->rowCount() == 0) {
                        $error = "يجب تحديث قاعدة البيانات أولاً. شغلي ملف database_upgrade_safe.sql في phpMyAdmin";
                    } else {
                        // تحديث المرأة بإضافة الزوج الثاني
                        $updateStmt = $pdo->prepare("UPDATE persons SET second_spouse_person_id = ?, 
                                                    second_spouse_is_external = ?, 
                                                    second_external_tree_id = ? 
                                                    WHERE id = ?");
                        $updateStmt->execute([$spousePersonId, $isExternal, $externalTreeId, $personId]);
                        
                        $_SESSION['success_message'] = "تم إضافة الزوج الثاني بنجاح";
                        header("Location: manage_people_new.php");
                        exit();
                    }
                } catch (Exception $e) {
                    $error = "خطأ في التحديث: " . htmlspecialchars($e->getMessage());
                }
            }
        } catch (Exception $e) {
            $error = "حدث خطأ: " . htmlspecialchars($e->getMessage());
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
<title>إضافة زوج ثاني</title>
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

input {
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

input:focus {
    outline: none;
    border-color: #c4a77d;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(196, 167, 125, 0.1);
}

.checkbox-label {
    display: flex;
    align-items: center;
    margin: 15px 0;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin-left: 10px;
    margin-bottom: 0;
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

.info-text {
    color: #6b543f;
    font-size: 13px;
    margin-bottom: 20px;
    text-align: center;
    padding: 10px;
    background: rgba(196, 167, 125, 0.1);
    border-radius: 8px;
}

a {
    display: block;
    text-align: center;
    margin-top: 10px;
    color: #6b543f;
    text-decoration: none;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.3s;
}

a:hover {
    background: rgba(196, 167, 125, 0.1);
}
</style>
</head>
<body>
<div class="glass-box">
    <h2>إضافة زوج ثاني</h2>
    <?php if ($person): ?>
    <div class="info-text">
        للمرأة: <b><?= h($person['full_name']) ?></b>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$pdo): ?>
        <div class="err">لا يمكن الاتصال بقاعدة البيانات. تحققي من ملف config/db.php</div>
    <?php else: ?>
        <form method="POST">
            <label>اسم الزوج <span style="color:red;">*</span></label>
            <input name="spouse_name" placeholder="اسم الزوج الكامل" required>
            
            <label class="checkbox-label">
                <input type="checkbox" name="is_external" value="1">
                <span>زواج من خارج العائلة</span>
            </label>
            
            <button type="submit">حفظ</button>
        </form>
    <?php endif; ?>
    
    <a href="manage_people_new.php">إلغاء والعودة</a>
</div>
</body>
</html>
