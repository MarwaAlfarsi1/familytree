<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// تحميل ملف قاعدة البيانات المركزي
try {
    $dbPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbPath)) {
        $dbPath = dirname(dirname(__DIR__)) . '/config/db.php';
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

// التحقق من وجود اتصال قاعدة البيانات
if (!isset($pdo) || !$pdo) {
    die("خطأ: لا يوجد اتصال بقاعدة البيانات");
}

$message = '';
$success_message = '';
$show_info = false;
$member_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'recover') {
        // استعادة اسم المستخدم
        $membership_number = trim($_POST['membership_number'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        
        if (empty($membership_number) && empty($full_name)) {
            $message = "الرجاء إدخال رقم العضوية أو الاسم الكامل";
        } else {
            try {
                $sql = "SELECT id, full_name, username, membership_number FROM persons WHERE username IS NOT NULL AND username != ''";
                $params = [];
                
                if (!empty($membership_number)) {
                    $sql .= " AND membership_number = ?";
                    $params[] = $membership_number;
                }
                
                if (!empty($full_name)) {
                    $sql .= " AND full_name LIKE ?";
                    $params[] = '%' . $full_name . '%';
                }
                
                $sql .= " LIMIT 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($member) {
                    $member_info = $member;
                    $show_info = true;
                    $success_message = "تم العثور على حسابك!";
                } else {
                    $message = "لم يتم العثور على حساب بهذه البيانات. تأكد من رقم العضوية أو الاسم الكامل.";
                }
            } catch (Exception $e) {
                $message = "حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.";
            }
        }
    } elseif ($action === 'reset_password') {
        // إعادة تعيين كلمة المرور
        $person_id = (int)($_POST['person_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        if ($person_id <= 0) {
            $message = "خطأ: معرف الشخص غير صحيح";
        } elseif (empty($new_password)) {
            $message = "الرجاء إدخال كلمة المرور الجديدة";
        } elseif (strlen($new_password) < 6) {
            $message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
        } elseif ($new_password !== $confirm_password) {
            $message = "كلمة المرور وتأكيد كلمة المرور غير متطابقين";
        } else {
            try {
                // التحقق من وجود الشخص
                $stmt = $pdo->prepare("SELECT id, full_name FROM persons WHERE id = ? AND username IS NOT NULL AND username != '' LIMIT 1");
                $stmt->execute([$person_id]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$member) {
                    $message = "لم يتم العثور على الحساب";
                } else {
                    // تشفير كلمة المرور الجديدة
                    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    
                    // تحديث كلمة المرور
                    $updateStmt = $pdo->prepare("UPDATE persons SET password_hash = ? WHERE id = ?");
                    $updateStmt->execute([$password_hash, $person_id]);
                    
                    $success_message = "تم تحديث كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.";
                    $show_info = false;
                    $member_info = null;
                }
            } catch (Exception $e) {
                $message = "حدث خطأ أثناء تحديث كلمة المرور: " . htmlspecialchars($e->getMessage());
            }
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
<title>استعادة اسم المستخدم أو كلمة المرور</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

.wrapper {
    width: 100%;
    max-width: 500px;
}

.glass-box {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    border: 1px solid rgba(255, 255, 255, 0.3);
    text-align: center;
}

h2 {
    color: #3c2f2f;
    font-size: 24px;
    margin-bottom: 8px;
    font-weight: 700;
}

.tagline {
    color: #6b543f;
    font-size: 14px;
    margin-bottom: 25px;
    line-height: 1.6;
}

input, select {
    width: 100%;
    padding: 14px 16px;
    margin-bottom: 15px;
    border-radius: 12px;
    border: 1px solid rgba(191, 169, 138, 0.5);
    font-size: 15px;
    background: rgba(255, 255, 255, 0.9);
    transition: all 0.3s;
    font-family: 'Cairo', sans-serif;
}

input:focus, select:focus {
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
    transition: all 0.3s;
    font-family: 'Cairo', sans-serif;
}

.btn-primary {
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
    box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
}

.btn-secondary {
    margin-top: 12px;
    background: rgba(255, 255, 255, 0.9);
    color: #3c2f2f;
    border: 1px solid rgba(191, 169, 138, 0.5);
}

.btn-secondary:hover {
    background: #fff;
    border-color: #c4a77d;
}

.error {
    background: rgba(255, 236, 236, 0.9);
    color: #c40000;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid rgba(196, 0, 0, 0.2);
}

.success {
    background: rgba(236, 255, 236, 0.9);
    color: #006400;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid rgba(0, 100, 0, 0.2);
}

.info-box {
    background: rgba(240, 248, 255, 0.9);
    border: 2px solid rgba(37, 99, 235, 0.3);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    text-align: right;
}

.info-box h3 {
    color: #2563eb;
    font-size: 18px;
    margin-bottom: 15px;
}

.info-item {
    margin-bottom: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 8px;
}

.info-item strong {
    color: #3c2f2f;
    display: inline-block;
    min-width: 120px;
}

.info-item span {
    color: #2563eb;
    font-weight: 600;
}

@media (max-width: 480px) {
    .glass-box {
        padding: 25px;
    }
    
    h2 {
        font-size: 20px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="glass-box">
        <h2><i class="fas fa-key"></i> استعادة اسم المستخدم أو كلمة المرور</h2>
        <div class="tagline">
            أدخل رقم العضوية أو الاسم الكامل للعثور على حسابك
        </div>

        <?php if($message): ?>
            <div class="error"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if($success_message): ?>
            <div class="success"><?= h($success_message) ?></div>
        <?php endif; ?>

        <?php if ($show_info && $member_info): ?>
            <!-- عرض معلومات الحساب -->
            <div class="info-box">
                <h3><i class="fas fa-check-circle"></i> تم العثور على حسابك</h3>
                <div class="info-item">
                    <strong>الاسم الكامل:</strong>
                    <span><?= h($member_info['full_name']) ?></span>
                </div>
                <?php if (!empty($member_info['membership_number'])): ?>
                <div class="info-item">
                    <strong>رقم العضوية:</strong>
                    <span><?= h($member_info['membership_number']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <strong>اسم المستخدم:</strong>
                    <span style="color: #2563eb; font-size: 18px; font-weight: 700;"><?= h($member_info['username']) ?></span>
                </div>
            </div>

            <!-- نموذج إعادة تعيين كلمة المرور -->
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="person_id" value="<?= (int)$member_info['id'] ?>">
                <h3 style="color: #3c2f2f; margin-bottom: 15px; font-size: 18px;">إعادة تعيين كلمة المرور</h3>
                <input type="password" name="new_password" placeholder="كلمة المرور الجديدة (6 أحرف على الأقل)" required minlength="6">
                <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور" required minlength="6">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> تحديث كلمة المرور
                </button>
            </form>
        <?php else: ?>
            <!-- نموذج البحث عن الحساب -->
            <form method="POST">
                <input type="hidden" name="action" value="recover">
                <input type="text" name="membership_number" placeholder="رقم العضوية (اختياري)" autocomplete="off">
                <div style="text-align: center; margin: 10px 0; color: #6b543f; font-size: 14px;">أو</div>
                <input type="text" name="full_name" placeholder="الاسم الكامل (اختياري)" autocomplete="off">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> البحث عن الحساب
                </button>
            </form>
        <?php endif; ?>

        <a href="login.php" style="text-decoration:none;display:block;">
            <button type="button" class="btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة لتسجيل الدخول
            </button>
        </a>
    </div>
</div>

</body>
</html>