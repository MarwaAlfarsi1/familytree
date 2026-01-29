<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
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

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $membership_number = trim($_POST['membership_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $requested_username = trim($_POST['requested_username'] ?? '');
    $requested_password = trim($_POST['requested_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    if (empty($full_name) || empty($requested_username) || empty($requested_password)) {
        $message = "الرجاء إدخال جميع البيانات المطلوبة";
    } elseif ($requested_password !== $confirm_password) {
        $message = "كلمة المرور غير متطابقة";
    } elseif (strlen($requested_password) < 6) {
        $message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } else {
        try {
            // التحقق من وجود جدول account_requests
            $checkTable = $pdo->query("SHOW TABLES LIKE 'account_requests'");
            if ($checkTable->rowCount() === 0) {
                throw new Exception("جدول account_requests غير موجود في قاعدة البيانات. يرجى إنشاء الجدول أولاً.");
            }
            
            // التحقق من أن اسم المستخدم غير مستخدم
            $checkUsername = $pdo->prepare("SELECT id FROM account_requests WHERE requested_username = ? AND status != 'rejected' LIMIT 1");
            $checkUsername->execute([$requested_username]);
            if ($checkUsername->fetch()) {
                $message = "اسم المستخدم موجود بالفعل في طلب قيد الانتظار";
            } else {
                // التحقق من وجود اسم المستخدم في جدول persons
                $checkPerson = $pdo->prepare("SELECT id FROM persons WHERE username = ? LIMIT 1");
                $checkPerson->execute([$requested_username]);
                if ($checkPerson->fetch()) {
                    $message = "اسم المستخدم موجود بالفعل";
                } else {
                    // البحث عن الشخص برقم العضوية إذا تم إدخاله
                    $person_id = null;
                    if (!empty($membership_number)) {
                        $findPerson = $pdo->prepare("SELECT id FROM persons WHERE membership_number = ? LIMIT 1");
                        $findPerson->execute([$membership_number]);
                        $person = $findPerson->fetch(PDO::FETCH_ASSOC);
                        if ($person) {
                            $person_id = (int)$person['id'];
                        }
                    }
                    
                    // تشفير كلمة المرور
                    $passwordHash = password_hash($requested_password, PASSWORD_BCRYPT);
                    
                    if (empty($passwordHash) || strlen($passwordHash) < 20) {
                        throw new Exception("فشل في تشفير كلمة المرور");
                    }
                    
                    // إضافة الطلب
                    $stmt = $pdo->prepare("INSERT INTO account_requests 
                                          (person_id, full_name, membership_number, email, phone, requested_username, requested_password_hash, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $result = $stmt->execute([
                        $person_id ?: null, 
                        $full_name, 
                        $membership_number ?: null, 
                        $email ?: null, 
                        $phone ?: null, 
                        $requested_username, 
                        $passwordHash
                    ]);
                    
                    if (!$result) {
                        throw new Exception("فشل في إضافة الطلب إلى قاعدة البيانات");
                    }
                    
                    $success = true;
                    $message = "تم إرسال طلبك بنجاح! سيتم مراجعته من قبل الإدمن قريباً.";
                }
            }
        } catch (PDOException $e) {
            $message = "حدث خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage());
        } catch (Exception $e) {
            $message = "حدث خطأ: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طلب حساب جديد</title>
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
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
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
    max-width: 500px;
    width: 100%;
}

h1 {
    color: #3c2f2f;
    font-size: 24px;
    margin-bottom: 10px;
    text-align: center;
}

.subtitle {
    color: #6b543f;
    font-size: 14px;
    margin-bottom: 25px;
    text-align: center;
    line-height: 1.6;
}

label {
    display: block;
    margin-bottom: 5px;
    color: #6b543f;
    font-weight: 600;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 15px;
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
    color: #006600;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid rgba(0, 102, 0, 0.2);
}

.info {
    background: rgba(240, 248, 255, 0.9);
    color: #2c5aa0;
    padding: 12px;
    border-radius: 10px;
    margin-top: 15px;
    font-size: 13px;
    border: 1px solid rgba(44, 90, 160, 0.2);
}

.optional {
    color: #999;
    font-size: 12px;
    font-weight: normal;
}

a {
    color: #2c5aa0;
    text-decoration: none;
    display: block;
    text-align: center;
    margin-top: 15px;
}
</style>
</head>
<body>

<div class="glass-box">
    <h1>طلب حساب جديد</h1>
    <div class="subtitle">
        املأ النموذج أدناه وانتظر موافقة الإدمن على طلبك
    </div>

    <?php if($message): ?>
        <div class="<?= $success ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="info">
            <strong>تم إرسال طلبك بنجاح!</strong><br>
            سيتم مراجعة طلبك من قبل الإدمن. ستصلك رسالة عند الموافقة على طلبك.
        </div>
        <a href="auth/login.php">العودة لتسجيل الدخول</a>
    <?php else: ?>
        <form method="POST">
            <label>الاسم الكامل <span style="color:red;">*</span></label>
            <input type="text" name="full_name" placeholder="الاسم الكامل" required>

            <label>رقم العضوية <span class="optional">(اختياري - إذا كنت عضواً في العائلة)</span></label>
            <input type="text" name="membership_number" placeholder="رقم العضوية">

            <label>البريد الإلكتروني <span class="optional">(اختياري)</span></label>
            <input type="email" name="email" placeholder="example@email.com">

            <label>رقم الهاتف <span class="optional">(اختياري)</span></label>
            <input type="text" name="phone" placeholder="رقم الهاتف">

            <label>اسم المستخدم المطلوب <span style="color:red;">*</span></label>
            <input type="text" name="requested_username" placeholder="اسم المستخدم" required minlength="3">

            <label>كلمة المرور <span style="color:red;">*</span></label>
            <input type="password" name="requested_password" placeholder="6 أحرف على الأقل" required minlength="6">

            <label>تأكيد كلمة المرور <span style="color:red;">*</span></label>
            <input type="password" name="confirm_password" placeholder="أعد إدخال كلمة المرور" required minlength="6">

            <button type="submit">إرسال الطلب</button>
        </form>

        <div class="info">
            <strong>ملاحظة:</strong> بعد إرسال الطلب، سيتم مراجعته من قبل الإدمن. ستصلك إشعار عند الموافقة على طلبك.
        </div>

        <a href="auth/login.php">لدي حساب بالفعل - تسجيل الدخول</a>
    <?php endif; ?>
</div>

</body>
</html>