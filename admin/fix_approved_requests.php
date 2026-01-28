<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id'])) {
    die('يجب تسجيل الدخول كإدمن');
}

$messages = [];
$fixed = 0;
$errors = 0;
$created = 0;

try {
    // جلب الشجرة الرئيسية
    $mainTree = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $mainTreeId = $mainTree ? (int)$mainTree['id'] : 1;
    
    // جلب جميع الطلبات الموافق عليها التي لم يتم ربطها
    $stmt = $pdo->query("SELECT * FROM account_requests WHERE status = 'approved' ORDER BY id DESC");
    $approvedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($approvedRequests as $request) {
        $personUpdated = false;
        
        // التحقق من أن الحساب موجود في persons
        $checkStmt = $pdo->prepare("SELECT id, username FROM persons WHERE username = ? LIMIT 1");
        $checkStmt->execute([$request['requested_username']]);
        $existingPerson = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingPerson && $existingPerson['username'] === $request['requested_username']) {
            // الحساب موجود بالفعل
            continue;
        }
        
        // محاولة البحث عن الشخص
        if (!empty($request['person_id'])) {
            $updateStmt = $pdo->prepare("UPDATE persons SET username = ?, password_hash = ? WHERE id = ?");
            $updateStmt->execute([
                $request['requested_username'],
                $request['requested_password_hash'],
                $request['person_id']
            ]);
            $personUpdated = true;
            $fixed++;
            $messages[] = "✓ تم إصلاح حساب: " . htmlspecialchars($request['full_name']) . " (username: " . htmlspecialchars($request['requested_username']) . ")";
        } elseif (!empty($request['membership_number'])) {
            // البحث برقم العضوية
            $findPerson = $pdo->prepare("SELECT id FROM persons WHERE membership_number = ? LIMIT 1");
            $findPerson->execute([$request['membership_number']]);
            $foundPerson = $findPerson->fetch(PDO::FETCH_ASSOC);
            
            if ($foundPerson) {
                $updateStmt = $pdo->prepare("UPDATE persons SET username = ?, password_hash = ? WHERE id = ?");
                $updateStmt->execute([
                    $request['requested_username'],
                    $request['requested_password_hash'],
                    $foundPerson['id']
                ]);
                $personUpdated = true;
                $fixed++;
                $messages[] = "✓ تم إصلاح حساب: " . htmlspecialchars($request['full_name']) . " (username: " . htmlspecialchars($request['requested_username']) . ")";
            }
        }
        
        // البحث بالاسم الكامل (مطابقة تامة)
        if (!$personUpdated && !empty($request['full_name'])) {
            $findPerson = $pdo->prepare("SELECT id FROM persons WHERE full_name = ? LIMIT 1");
            $findPerson->execute([$request['full_name']]);
            $foundPerson = $findPerson->fetch(PDO::FETCH_ASSOC);
            
            if ($foundPerson) {
                $updateStmt = $pdo->prepare("UPDATE persons SET username = ?, password_hash = ? WHERE id = ?");
                $updateStmt->execute([
                    $request['requested_username'],
                    $request['requested_password_hash'],
                    $foundPerson['id']
                ]);
                $personUpdated = true;
                $fixed++;
                $messages[] = "✓ تم إصلاح حساب: " . htmlspecialchars($request['full_name']) . " (username: " . htmlspecialchars($request['requested_username']) . ")";
            }
        }
        
        // البحث الجزئي بالاسم (إذا لم نجد مطابقة تامة)
        if (!$personUpdated && !empty($request['full_name'])) {
            // استخراج الاسم الأول من الاسم الكامل (مثلاً "جمانه" من "جمانه بنت ناصر")
            $nameParts = explode(' ', trim($request['full_name']));
            $firstName = $nameParts[0];
            
            if (!empty($firstName)) {
                $findPerson = $pdo->prepare("SELECT id, full_name FROM persons WHERE full_name LIKE ? LIMIT 5");
                $findPerson->execute(['%' . $firstName . '%']);
                $foundPersons = $findPerson->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($foundPersons) === 1) {
                    // وجدنا شخص واحد فقط، نستخدمه
                    $foundPerson = $foundPersons[0];
                    $updateStmt = $pdo->prepare("UPDATE persons SET username = ?, password_hash = ? WHERE id = ?");
                    $updateStmt->execute([
                        $request['requested_username'],
                        $request['requested_password_hash'],
                        $foundPerson['id']
                    ]);
                    $personUpdated = true;
                    $fixed++;
                    $messages[] = "✓ تم إصلاح حساب: " . htmlspecialchars($request['full_name']) . " (ربط بـ: " . htmlspecialchars($foundPerson['full_name']) . ", username: " . htmlspecialchars($request['requested_username']) . ")";
                } elseif (count($foundPersons) > 1) {
                    // وجدنا أكثر من شخص، نحفظهم للعرض
                    $errors++;
                    $messages[] = "⚠ وجدنا عدة أشخاص باسم مشابه لـ: " . htmlspecialchars($request['full_name']) . " - يرجى الربط يدوياً";
                }
            }
        }
        
        // إذا لم نجد الشخص، ننشئ حساب جديد (بدون ربط بشخص في persons)
        if (!$personUpdated) {
            // إنشاء سجل جديد في persons مع البيانات الأساسية
            try {
                // جلب أعلى مستوى جيل
                $maxGenStmt = $pdo->query("SELECT MAX(generation_level) as max_gen FROM persons");
                $maxGen = $maxGenStmt->fetch(PDO::FETCH_ASSOC);
                $nextGen = ($maxGen['max_gen'] ?? 0) + 1;
                
                // إنشاء رقم عضوية إذا لم يكن موجوداً
                $membershipNumber = $request['membership_number'];
                if (empty($membershipNumber)) {
                    $lastNumStmt = $pdo->query("SELECT membership_number FROM persons WHERE membership_number IS NOT NULL AND membership_number != '' ORDER BY CAST(membership_number AS UNSIGNED) DESC LIMIT 1");
                    $lastNum = $lastNumStmt->fetch(PDO::FETCH_ASSOC);
                    if ($lastNum && !empty($lastNum['membership_number'])) {
                        $nextNum = (int)$lastNum['membership_number'] + 1;
                        $membershipNumber = str_pad($nextNum, 4, '0', STR_PAD_LEFT);
                    } else {
                        $membershipNumber = '0001';
                    }
                }
                
                // إدراج الشخص الجديد
                $insertStmt = $pdo->prepare("INSERT INTO persons 
                    (tree_id, full_name, membership_number, username, password_hash, generation_level, is_root) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)");
                $insertStmt->execute([
                    $mainTreeId,
                    $request['full_name'],
                    $membershipNumber,
                    $request['requested_username'],
                    $request['requested_password_hash'],
                    $nextGen
                ]);
                
                $personUpdated = true;
                $created++;
                $messages[] = "✓ تم إنشاء حساب جديد: " . htmlspecialchars($request['full_name']) . " (username: " . htmlspecialchars($request['requested_username']) . ", رقم عضوية: " . $membershipNumber . ")";
            } catch (Exception $e) {
                $errors++;
                $messages[] = "✗ خطأ في إنشاء حساب: " . htmlspecialchars($request['full_name']) . " - " . $e->getMessage();
            }
        }
    }
    
    if (empty($approvedRequests)) {
        $messages[] = "لا توجد طلبات موافق عليها";
    }
    
} catch (Exception $e) {
    $messages[] = "✗ خطأ عام: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إصلاح الطلبات الموافق عليها</title>
<style>
body { 
    font-family: 'Cairo', sans-serif; 
    padding: 20px; 
    background: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
    min-height: 100vh;
}
.container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    max-width: 800px;
    margin: 0 auto;
}
h1 {
    color: #3c2f2f;
    margin-bottom: 20px;
    text-align: center;
}
.message {
    padding: 12px;
    margin: 10px 0;
    border-radius: 8px;
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.error-msg {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.warning-msg {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}
.summary {
    background: #e7f3ff;
    color: #004085;
    border: 1px solid #b8daff;
    font-weight: 600;
    text-align: center;
    padding: 15px;
    margin-top: 20px;
    border-radius: 8px;
}
a {
    display: inline-block;
    margin: 10px 5px 0 5px;
    padding: 12px 20px;
    background: #3c2f2f;
    color: #f2c200;
    text-decoration: none;
    border-radius: 8px;
    text-align: center;
    transition: all 0.3s;
}
a:hover {
    background: #2a2222;
    transform: translateY(-2px);
}
.buttons {
    text-align: center;
    margin-top: 20px;
}
</style>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>إصلاح الطلبات الموافق عليها</h1>
    
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message <?= strpos($msg, '✗') !== false ? 'error-msg' : (strpos($msg, '⚠') !== false ? 'warning-msg' : '') ?>">
                <?= $msg ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="summary">
        تم إصلاح: <?= $fixed ?> حساب | تم إنشاء: <?= $created ?> حساب جديد | أخطاء: <?= $errors ?>
    </div>
    
    <div class="buttons">
        <a href="test_member_account.php">عرض حسابات الأعضاء</a>
        <a href="manage_requests.php">إدارة الطلبات</a>
        <a href="dashboard_new.php">رجوع للوحة التحكم</a>
    </div>
</div>
</body>
</html>