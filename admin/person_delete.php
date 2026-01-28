<?php
session_start();

// التحقق من تسجيل الدخول (إدمن أو عضو)
$isAdmin = isset($_SESSION['admin_id']);
$isMember = isset($_SESSION['member_id']);

if (!$isAdmin && !$isMember) {
    header("Location: auth/login.php");
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
    // محاولة الاتصال المباشر
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

$personId = (int)($_GET['id'] ?? 0);
if ($personId <= 0) { 
    header("Location: manage_people_new.php"); 
    exit(); 
}

// جلب الشخص
$stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
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

// منع حذف الجد المؤسس (root)
if (!empty($person['is_root'])) {
    $_SESSION['error_message'] = "لا يمكن حذف الجد المؤسس!";
    if ($isMember) {
        header("Location: dashboard.php");
    } else {
        header("Location: manage_people_new.php");
    }
    exit();
}

// إذا كان عضو عادي، التحقق من أنه يحاول حذف فرد من عائلته فقط
if ($isMember && !$isAdmin) {
    $memberId = (int)$_SESSION['member_id'];
    
    // جلب بيانات العضو
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // التحقق من أن الشخص هو زوج/زوجة أو ابن/ابنة للعضو
    $isFamilyMember = false;
    
    // التحقق من أنه زوج/زوجة
    if ($person['id'] == $memberId || 
        ($person['spouse_person_id'] == $memberId && empty($person['spouse_is_external'])) ||
        ($member['spouse_person_id'] == $personId && empty($member['spouse_is_external']))) {
        $isFamilyMember = true;
    }
    
    // التحقق من أنه ابن/ابنة
    if (!$isFamilyMember) {
        if (($member['gender'] === 'male' && $person['father_id'] == $memberId) ||
            ($member['gender'] === 'female' && $person['mother_id'] == $memberId)) {
            $isFamilyMember = true;
        }
    }
    
    if (!$isFamilyMember) {
        $_SESSION['error_message'] = "غير مسموح لك بحذف هذا الشخص!";
        header("Location: dashboard.php");
        exit();
    }
}

$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من التأكيد
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        try {
            $pdo->beginTransaction();
            
            // دالة حذف متتالية للأطفال (recursive delete)
            function deletePersonRecursive($pdo, $personId) {
                // جلب الأطفال
                $childrenStmt = $pdo->prepare("SELECT id FROM persons WHERE father_id=? OR mother_id=?");
                $childrenStmt->execute([$personId, $personId]);
                $children = $childrenStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // حذف الأطفال أولاً (تكرارياً)
                foreach ($children as $childId) {
                    deletePersonRecursive($pdo, $childId);
                }
                
                // إلغاء ربط الزوج/الزوجة
                $personStmt = $pdo->prepare("SELECT spouse_person_id FROM persons WHERE id=? LIMIT 1");
                $personStmt->execute([$personId]);
                $spouseId = $personStmt->fetchColumn();
                
                if ($spouseId) {
                    $updateSpouseStmt = $pdo->prepare("UPDATE persons SET spouse_person_id=NULL, spouse_is_external=0, external_tree_id=NULL WHERE id=?");
                    $updateSpouseStmt->execute([$spouseId]);
                }
                
                // البحث عن الأشخاص المرتبطين به كزوج/زوجة
                $updateRelatedStmt = $pdo->prepare("UPDATE persons SET spouse_person_id=NULL, spouse_is_external=0, external_tree_id=NULL WHERE spouse_person_id=?");
                $updateRelatedStmt->execute([$personId]);
                
                // حذف الصورة
                $photoStmt = $pdo->prepare("SELECT photo_path FROM persons WHERE id=? LIMIT 1");
                $photoStmt->execute([$personId]);
                $photoPath = $photoStmt->fetchColumn();
                
                if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)) {
                    @unlink(__DIR__ . '/../' . $photoPath);
                }
                
                // حذف الشخص
                $deleteStmt = $pdo->prepare("DELETE FROM persons WHERE id=?");
                $deleteStmt->execute([$personId]);
            }
            
            // حذف الشخص وأطفاله (تكرارياً)
            deletePersonRecursive($pdo, $personId);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "تم حذف " . htmlspecialchars($person['full_name']) . " بنجاح!";
            if ($isMember) {
                header("Location: dashboard.php");
            } else {
                header("Location: manage_people_new.php");
            }
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "حدث خطأ أثناء الحذف: " . $e->getMessage();
        }
    } else {
        $error = "يجب تأكيد الحذف";
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
<title>حذف شخص</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f5efe3] min-h-screen p-4 md:p-6">

<div class="max-w-md mx-auto mt-10">
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-center mb-6 text-red-700">حذف شخص</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= h($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded mb-4">
            <p class="font-bold mb-2">⚠️ تحذير!</p>
            <p>أنت على وشك حذف:</p>
            <p class="text-xl font-bold my-2"><?= h($person['full_name']) ?></p>
            <p class="text-sm">سيتم حذف هذا الشخص وأطفاله (إن وجدوا) من الشجرة.</p>
            <p class="text-sm mt-2 font-bold">⚠️ لا يمكن التراجع عن هذه العملية!</p>
        </div>
        
        <form method="POST" class="space-y-4">
            <div class="flex items-center">
                <input type="checkbox" id="confirm" name="confirm_delete" value="yes" required class="w-5 h-5 text-red-600">
                <label for="confirm" class="mr-2 text-gray-700">أؤكد أنني أريد الحذف</label>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-700 text-white px-4 py-3 rounded-lg font-bold hover:bg-red-800">
                    حذف
                </button>
                <a href="<?= $isMember ? 'dashboard.php' : 'manage_people_new.php' ?>" class="flex-1 bg-gray-500 text-white px-4 py-3 rounded-lg font-bold text-center hover:bg-gray-600">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>