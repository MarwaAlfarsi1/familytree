<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// تحميل ملف قاعدة البيانات المركزي
try {
    $dbPath = __DIR__ . '/../config/db.php';
    if (!file_exists($dbPath)) {
        $dbPath = dirname(__DIR__) . '/config/db.php';
    }
    if (!file_exists($dbPath)) {
        die("خطأ: ملف قاعدة البيانات غير موجود. يرجى التأكد من وجود config/db.php");
    }
    require_once $dbPath;
    
    // التحقق من وجود $pdo بعد التحميل
    if (!isset($pdo) || !$pdo) {
        die("خطأ: فشل الاتصال بقاعدة البيانات");
    }
} catch (Exception $e) {
    die("خطأ في تحميل قاعدة البيانات: " . htmlspecialchars($e->getMessage()));
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}

if (!isset($pdo) || !$pdo) {
    die("خطأ: لا يوجد اتصال بقاعدة البيانات. تأكد من أن ملف db.php يعمل بشكل صحيح.");
}

$message = '';
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// معالجة الموافقة أو الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($request_id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            // جلب بيانات الطلب
            $stmt = $pdo->prepare("SELECT * FROM account_requests WHERE id = ? LIMIT 1");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                if ($action === 'approve') {
                    // تنظيف البيانات
                    $username = trim($request['requested_username']);
                    $passwordHash = trim($request['requested_password_hash']);
                    
                    // التحقق من صحة البيانات
                    if (empty($username) || empty($passwordHash)) {
                        throw new Exception("بيانات الطلب غير صحيحة: اسم المستخدم أو كلمة المرور مفقودة.");
                    }
                    
                    $person_id = null;
                    
                    // إذا كان مرتبطاً بشخص موجود
                    if (!empty($request['person_id'])) {
                        $person_id = (int)$request['person_id'];
                        
                        // التحقق من أن الشخص موجود
                        $checkPerson = $pdo->prepare("SELECT id FROM persons WHERE id = ? LIMIT 1");
                        $checkPerson->execute([$person_id]);
                        if (!$checkPerson->fetch()) {
                            throw new Exception("الشخص المحدد غير موجود في قاعدة البيانات.");
                        }
                    } else {
                        // إذا لم يكن مرتبطاً بشخص موجود، نحتاج لاختيار الأب أو الأم
                        $selected_father_id = !empty($_POST['selected_father_id']) ? (int)$_POST['selected_father_id'] : 0;
                        $selected_mother_id = !empty($_POST['selected_mother_id']) ? (int)$_POST['selected_mother_id'] : 0;
                        
                        if ($selected_father_id <= 0 && $selected_mother_id <= 0) {
                            throw new Exception("يجب اختيار الأب أو الأم لربط الطلب بشخص في شجرة العائلة.");
                        }
                        
                        // جلب معلومات الأب/الأم
                        $father = null;
                        $mother = null;
                        $tree_id = null;
                        $generation_level = 1;
                        
                        if ($selected_father_id > 0) {
                            $fatherStmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
                            $fatherStmt->execute([$selected_father_id]);
                            $father = $fatherStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$father) {
                                throw new Exception("الأب المحدد غير موجود في قاعدة البيانات.");
                            }
                            
                            $tree_id = $father['tree_id'];
                            $generation_level = ($father['generation_level'] ?? 0) + 1;
                        }
                        
                        if ($selected_mother_id > 0) {
                            $motherStmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
                            $motherStmt->execute([$selected_mother_id]);
                            $mother = $motherStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$mother) {
                                throw new Exception("الأم المحددة غير موجودة في قاعدة البيانات.");
                            }
                            
                            if (!$tree_id) {
                                $tree_id = $mother['tree_id'];
                                $generation_level = ($mother['generation_level'] ?? 0) + 1;
                            }
                        }
                        
                        // إنشاء شخص جديد
                        $pdo->beginTransaction();
                        try {
                            // تحديد الجنس (افتراضي: ذكر إذا لم يتم تحديده)
                            $gender = 'male'; // يمكن تعديله لاحقاً
                            
                            // إدراج الشخص الجديد
                            $insertStmt = $pdo->prepare("INSERT INTO persons 
                                (tree_id, full_name, gender, username, password_hash, father_id, mother_id, generation_level, is_root) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                            
                            $result = $insertStmt->execute([
                                $tree_id ?: null,
                                $request['full_name'],
                                $gender,
                                $username,
                                $passwordHash,
                                $selected_father_id ?: null,
                                $selected_mother_id ?: null,
                                $generation_level
                            ]);
                            
                            if (!$result) {
                                throw new Exception("فشل في إنشاء الشخص الجديد.");
                            }
                            
                            $person_id = $pdo->lastInsertId();
                            
                            // تحديث person_id في الطلب
                            $updateRequestPerson = $pdo->prepare("UPDATE account_requests SET person_id = ? WHERE id = ?");
                            $updateRequestPerson->execute([$person_id, $request_id]);
                            
                            $pdo->commit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    }
                    
                    // تحديث بيانات تسجيل الدخول للشخص
                    $pdo->beginTransaction();
                    
                    try {
                        $updateStmt = $pdo->prepare("UPDATE persons SET username = ?, password_hash = ? WHERE id = ?");
                        $result = $updateStmt->execute([
                            $username,
                            $passwordHash,
                            $person_id
                        ]);
                        
                        if (!$result) {
                            throw new Exception("فشل في تحديث بيانات الشخص في قاعدة البيانات.");
                        }
                        
                        // التحقق من أن البيانات تم حفظها بشكل صحيح
                        $verifyStmt = $pdo->prepare("SELECT username, password_hash FROM persons WHERE id = ? LIMIT 1");
                        $verifyStmt->execute([$person_id]);
                        $verified = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$verified) {
                            throw new Exception("فشل في التحقق من البيانات المحفوظة.");
                        }
                        
                        // التحقق من أن اسم المستخدم وكلمة المرور محفوظة بشكل صحيح
                        if ($verified['username'] !== $username) {
                            throw new Exception("اسم المستخدم لم يُحفظ بشكل صحيح.");
                        }
                        
                        if (empty($verified['password_hash']) || strlen($verified['password_hash']) < 20) {
                            throw new Exception("كلمة المرور المشفرة لم تُحفظ بشكل صحيح.");
                        }
                        
                        // التحقق من أن كلمة المرور المشفرة صحيحة (يجب أن تبدأ بـ $2y$)
                        if (strpos($verified['password_hash'], '$2y$') !== 0 && strpos($verified['password_hash'], '$2a$') !== 0) {
                            throw new Exception("تنسيق كلمة المرور المشفرة غير صحيح.");
                        }
                        
                        $pdo->commit();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    
                    // تحديث حالة الطلب
                    try {
                        $updateRequest = $pdo->prepare("UPDATE account_requests SET status = 'approved', admin_id = ?, admin_notes = ? WHERE id = ?");
                        $updateRequest->execute([$_SESSION['admin_id'], $admin_notes, $request_id]);
                    } catch (Exception $e) {
                        throw new Exception("فشل في تحديث حالة الطلب: " . $e->getMessage());
                    }
                    
                    $_SESSION['success_message'] = "تم الموافقة على الطلب بنجاح! يمكن للعضو الآن تسجيل الدخول.";
                } else {
                    // رفض الطلب
                    try {
                        $updateRequest = $pdo->prepare("UPDATE account_requests SET status = 'rejected', admin_id = ?, admin_notes = ? WHERE id = ?");
                        $updateRequest->execute([$_SESSION['admin_id'], $admin_notes, $request_id]);
                    } catch (Exception $e) {
                        throw new Exception("فشل في تحديث حالة الطلب: " . $e->getMessage());
                    }
                    
                    $_SESSION['success_message'] = "تم رفض الطلب.";
                }
                
                header("Location: manage_requests.php");
                exit();
            }
        } catch (Exception $e) {
            $message = "حدث خطأ: " . htmlspecialchars($e->getMessage());
        }
    }
}

// جلب جميع الطلبات
$pendingRequests = [];
$approvedRequests = [];
$rejectedRequests = [];

// جلب قائمة جميع الأشخاص لاختيار الأب أو الأم (استبعاد الأزواج الخارجيين فقط)
$allPersons = [];
try {
    // استبعاد الأزواج الخارجيين (external spouses)
    // الأزواج الخارجيون هم الأشخاص الموجودون في شجرة خارجية (external tree)
    // لكن لا نستبعد الأشخاص من العائلة الرئيسية حتى لو كان لديهم زوج خارجي
    $personsStmt = $pdo->query("SELECT p.id, p.full_name, p.gender, p.membership_number 
                                FROM persons p
                                LEFT JOIN trees t ON p.tree_id = t.id
                                WHERE (t.tree_type IS NULL OR t.tree_type != 'external')
                                AND p.is_root = 0
                                ORDER BY p.full_name ASC");
    $allPersons = $personsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // تجاهل الخطأ
}

try {
    // التحقق من وجود جدول account_requests
    $checkTable = $pdo->query("SHOW TABLES LIKE 'account_requests'");
    if ($checkTable->rowCount() === 0) {
        $message = "تحذير: جدول account_requests غير موجود في قاعدة البيانات.";
    } else {
        $stmt = $pdo->query("SELECT ar.*, p.full_name as person_name, p.membership_number as person_membership
                             FROM account_requests ar
                             LEFT JOIN persons p ON p.id = ar.person_id
                             ORDER BY ar.created_at DESC");
        $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allRequests as $req) {
            if (isset($req['status'])) {
                if ($req['status'] === 'pending') {
                    $pendingRequests[] = $req;
                } elseif ($req['status'] === 'approved') {
                    $approvedRequests[] = $req;
                } else {
                    $rejectedRequests[] = $req;
                }
            }
        }
    }
} catch (PDOException $e) {
    $message = "حدث خطأ في جلب الطلبات: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    $message = "حدث خطأ: " . htmlspecialchars($e->getMessage());
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة طلبات الحسابات</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* {
    font-family: 'Cairo', sans-serif;
}

.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-bottom: 20px;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}
</style>
</head>
<body class="bg-gradient-to-br from-[#f5efe3] to-[#e8ddd0] min-h-screen" style="display: flex; flex-direction: column;">
    <?php 
    $navPath = __DIR__ . '/nav.php';
    if (file_exists($navPath)) {
        include $navPath;
    }
    ?>

<div class="max-w-6xl mx-auto" style="flex: 1; padding: 30px 20px;">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-3xl font-bold text-[#3c2f2f]"><i class="fas fa-user-plus"></i> إدارة طلبات الحسابات</h1>
        <a href="dashboard_new.php" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800 transition" style="display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <?php if($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <!-- الطلبات قيد الانتظار -->
    <div class="glass-card">
        <h2 class="text-2xl font-bold mb-4 text-[#3c2f2f]">
            الطلبات قيد الانتظار 
            <span class="text-lg font-normal text-gray-600">(<?= count($pendingRequests) ?>)</span>
        </h2>
        
        <?php if (empty($pendingRequests)): ?>
            <p class="text-gray-600">لا توجد طلبات قيد الانتظار</p>
        <?php else: ?>
            <?php foreach ($pendingRequests as $req): ?>
                <div class="bg-white bg-opacity-50 p-4 rounded-lg mb-4">
                    <div class="flex items-start justify-between flex-wrap gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-bold text-lg"><?= h($req['full_name']) ?></span>
                                <span class="status-badge status-pending">قيد الانتظار</span>
                            </div>
                            <div class="text-sm text-gray-600 space-y-1">
                                <?php if (!empty($req['membership_number'])): ?>
                                    <p>رقم العضوية: <?= h($req['membership_number']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($req['person_name'])): ?>
                                    <p>مرتبط بالشخص: <?= h($req['person_name']) ?> (<?= h($req['person_membership']) ?>)</p>
                                <?php endif; ?>
                                <p>اسم المستخدم المطلوب: <strong><?= h($req['requested_username']) ?></strong></p>
                                <?php if (!empty($req['email'])): ?>
                                    <p>البريد: <?= h($req['email']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($req['phone'])): ?>
                                    <p>الهاتف: <?= h($req['phone']) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500">تاريخ الطلب: <?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" class="inline" style="min-width: 300px;">
                                <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                
                                <?php if (empty($req['person_id'])): ?>
                                    <div class="mb-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                        <p class="text-sm text-yellow-800 mb-2"><strong>⚠ الطلب غير مرتبط بشخص:</strong></p>
                                        <label class="block text-sm font-semibold mb-1">اختر الأب (اختياري):</label>
                                        <select name="selected_father_id" class="w-full p-2 border rounded mb-2 text-sm">
                                            <option value="">-- اختر الأب --</option>
                                            <?php foreach ($allPersons as $person): ?>
                                                <?php if ($person['gender'] === 'male'): ?>
                                                    <option value="<?= (int)$person['id'] ?>">
                                                        <?= h($person['full_name']) ?>
                                                        <?php if (!empty($person['membership_number'])): ?>
                                                            (<?= h($person['membership_number']) ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <label class="block text-sm font-semibold mb-1">اختر الأم (اختياري):</label>
                                        <select name="selected_mother_id" class="w-full p-2 border rounded mb-2 text-sm">
                                            <option value="">-- اختر الأم --</option>
                                            <?php foreach ($allPersons as $person): ?>
                                                <?php if ($person['gender'] === 'female'): ?>
                                                    <option value="<?= (int)$person['id'] ?>">
                                                        <?= h($person['full_name']) ?>
                                                        <?php if (!empty($person['membership_number'])): ?>
                                                            (<?= h($person['membership_number']) ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="text-xs text-yellow-700 mt-1">يجب اختيار الأب أو الأم على الأقل</p>
                                    </div>
                                <?php endif; ?>
                                
                                <textarea name="admin_notes" placeholder="ملاحظات (اختياري)" class="w-full p-2 border rounded mb-2" rows="2"></textarea>
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 transition w-full" style="display: inline-flex; align-items: center; justify-content: center; gap: 5px;">
                                    <i class="fas fa-check"></i> موافقة
                                </button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <textarea name="admin_notes" placeholder="سبب الرفض (اختياري)" class="w-full p-2 border rounded mb-2" rows="2"></textarea>
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded text-sm hover:bg-red-700 transition" style="display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-times"></i> رفض
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- الطلبات الموافق عليها -->
    <?php if (!empty($approvedRequests)): ?>
    <div class="glass-card">
        <h2 class="text-2xl font-bold mb-4 text-[#3c2f2f]">
            الطلبات الموافق عليها 
            <span class="text-lg font-normal text-gray-600">(<?= count($approvedRequests) ?>)</span>
        </h2>
        <?php foreach ($approvedRequests as $req): ?>
            <div class="bg-white bg-opacity-50 p-3 rounded-lg mb-2">
                <span class="font-semibold"><?= h($req['full_name']) ?></span>
                <span class="status-badge status-approved">موافق</span>
                <span class="text-sm text-gray-600">- <?= h($req['requested_username']) ?></span>
                <span class="text-xs text-gray-500">(<?= !empty($req['updated_at']) ? date('Y-m-d', strtotime($req['updated_at'])) : (!empty($req['created_at']) ? date('Y-m-d', strtotime($req['created_at'])) : '') ?>)</span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- الطلبات المرفوضة -->
    <?php if (!empty($rejectedRequests)): ?>
    <div class="glass-card">
        <h2 class="text-2xl font-bold mb-4 text-[#3c2f2f]">
            الطلبات المرفوضة 
            <span class="text-lg font-normal text-gray-600">(<?= count($rejectedRequests) ?>)</span>
        </h2>
        <?php foreach ($rejectedRequests as $req): ?>
            <div class="bg-white bg-opacity-50 p-3 rounded-lg mb-2">
                <span class="font-semibold"><?= h($req['full_name']) ?></span>
                <span class="status-badge status-rejected">مرفوض</span>
                <span class="text-sm text-gray-600">- <?= h($req['requested_username']) ?></span>
                <?php if (!empty($req['admin_notes'])): ?>
                    <p class="text-xs text-gray-600 mt-1">ملاحظة: <?= h($req['admin_notes']) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

    <?php 
    $footerPath = __DIR__ . '/../footer.php';
    if (!file_exists($footerPath)) {
        $footerPath = dirname(__DIR__) . '/footer.php';
    }
    if (file_exists($footerPath)) {
        include $footerPath;
    }
    ?>
</body>
</html>