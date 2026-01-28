<?php
session_start();
require_once '../../config/db.php';

$message = '';
$step = $_GET['step'] ?? 'request'; // request أو reset

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = "الرجاء إدخال البريد الإلكتروني";
    } else {
        // التحقق من وجود الإدمن
        $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            // حفظ في الجلسة للخطوة التالية
            $_SESSION['reset_admin_id'] = $admin['id'];
            $_SESSION['reset_admin_email'] = $admin['email'];
            header("Location: reset_password.php?step=reset");
            exit();
        } else {
            $message = "البريد الإلكتروني غير موجود";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = "الرجاء إدخال كلمة المرور الجديدة";
    } elseif ($new_password !== $confirm_password) {
        $message = "كلمة المرور غير متطابقة";
    } elseif (strlen($new_password) < 6) {
        $message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } elseif (!isset($_SESSION['reset_admin_id'])) {
        $message = "انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى";
    } else {
        // تحديث كلمة المرور
        $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $updateStmt->execute([$passwordHash, $_SESSION['reset_admin_id']]);
        
        // حذف بيانات الجلسة
        unset($_SESSION['reset_admin_id']);
        unset($_SESSION['reset_admin_email']);
        
        $_SESSION['success_message'] = "تم تحديث كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول.";
        header("Location: login_username.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= $step === 'reset' ? 'إعادة تعيين كلمة المرور' : 'نسيت كلمة المرور' ?></title>
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

.wrapper {
    width: 100%;
    max-width: 450px;
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
    font-size: 26px;
    margin-bottom: 10px;
    font-weight: 700;
}

.tagline {
    color: #6b543f;
    font-size: 14px;
    margin-bottom: 25px;
    line-height: 1.6;
}

input {
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
    transition: all 0.3s;
    font-family: 'Cairo', sans-serif;
}

.btn-submit {
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
    box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
}

.btn-back {
    margin-top: 12px;
    background: rgba(255, 255, 255, 0.9);
    color: #3c2f2f;
    border: 1px solid rgba(191, 169, 138, 0.5);
}

.btn-back:hover {
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

.info {
    background: rgba(240, 248, 255, 0.9);
    color: #2c5aa0;
    padding: 12px;
    border-radius: 10px;
    margin-top: 15px;
    font-size: 13px;
    border: 1px solid rgba(44, 90, 160, 0.2);
}

@media (max-width: 480px) {
    .glass-box {
        padding: 25px;
    }
    
    h2 {
        font-size: 22px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="glass-box">
        <?php if ($step === 'request'): ?>
            <h2>نسيت كلمة المرور؟</h2>
            <div class="tagline">
                أدخل البريد الإلكتروني للتحقق من هويتك
            </div>

            <?php if($message): ?>
                <div class="error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="email" name="email" placeholder="البريد الإلكتروني" required autocomplete="email">
                <button type="submit" class="btn-submit">التحقق</button>
            </form>

            <a href="login_username.php" style="text-decoration:none;display:block;">
                <button type="button" class="btn-back">رجوع لتسجيل الدخول</button>
            </a>

        <?php elseif ($step === 'reset'): ?>
            <?php if (!isset($_SESSION['reset_admin_id'])): ?>
                <h2>انتهت صلاحية الجلسة</h2>
                <div class="error">يرجى المحاولة مرة أخرى من البداية</div>
                <a href="reset_password.php" style="text-decoration:none;display:block;">
                    <button type="button" class="btn-back">رجوع</button>
                </a>
            <?php else: ?>
                <h2>إعادة تعيين كلمة المرور</h2>
                <div class="tagline">
                    أدخل كلمة المرور الجديدة
                </div>

                <?php if($message): ?>
                    <div class="error"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="password" name="new_password" placeholder="كلمة المرور الجديدة (6 أحرف على الأقل)" required minlength="6" autocomplete="new-password">
                    <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور" required minlength="6" autocomplete="new-password">
                    <button type="submit" class="btn-submit">تغيير كلمة المرور</button>
                </form>

                <a href="login_username.php" style="text-decoration:none;display:block;">
                    <button type="button" class="btn-back">إلغاء</button>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>