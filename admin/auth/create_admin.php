<?php
session_start();
require_once '../../config/db.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    if (empty($email) || empty($password) || empty($confirm_password)) {
        $message = "الرجاء إدخال جميع البيانات المطلوبة";
    } elseif ($password !== $confirm_password) {
        $message = "كلمة المرور غير متطابقة";
    } elseif (strlen($password) < 6) {
        $message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } else {
        try {
            // التحقق من وجود البريد الإلكتروني
            $checkEmail = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $message = "البريد الإلكتروني موجود بالفعل";
            } else {
                // التحقق من وجود اسم المستخدم إذا تم إدخاله
                if (!empty($username)) {
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM admins LIKE 'username'");
                    if ($checkColumn->rowCount() > 0) {
                        $checkUsername = $pdo->prepare("SELECT id FROM admins WHERE username = ? LIMIT 1");
                        $checkUsername->execute([$username]);
                        if ($checkUsername->fetch()) {
                            $message = "اسم المستخدم موجود بالفعل";
                        }
                    }
                }
                
                if (empty($message)) {
                    // تشفير كلمة المرور
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    
                    // إضافة الإدمن
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM admins LIKE 'username'");
                    if ($checkColumn->rowCount() > 0) {
                        $stmt = $pdo->prepare("INSERT INTO admins (email, username, password) VALUES (?, ?, ?)");
                        $stmt->execute([$email, $username ?: null, $passwordHash]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
                        $stmt->execute([$email, $passwordHash]);
                    }
                    
                    $success = true;
                    $message = "تم إنشاء حساب الإدمن بنجاح! يمكنك الآن تسجيل الدخول.";
                }
            }
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
<title>إنشاء حساب إدمن جديد</title>
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

label {
    display: block;
    text-align: right;
    margin-bottom: 5px;
    color: #6b543f;
    font-weight: 600;
    font-size: 14px;
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
        <h2>إنشاء حساب إدمن جديد</h2>
        <div class="tagline">
            أدخل بياناتك لإنشاء حساب إدمن جديد
        </div>

        <?php if($message): ?>
            <div class="<?= $success ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="info">
                <strong>تم إنشاء الحساب بنجاح!</strong><br>
                يمكنك الآن تسجيل الدخول باستخدام البريد الإلكتروني وكلمة المرور.
            </div>
            <a href="login_username.php" style="text-decoration:none;display:block;">
                <button type="button" class="btn-back">تسجيل الدخول</button>
            </a>
        <?php else: ?>
            <form method="POST">
                <label>البريد الإلكتروني <span style="color:red;">*</span></label>
                <input type="email" name="email" placeholder="example@email.com" required>

                <label>اسم المستخدم <span class="optional">(اختياري)</span></label>
                <input type="text" name="username" placeholder="اسم المستخدم">

                <label>كلمة المرور <span style="color:red;">*</span></label>
                <input type="password" name="password" placeholder="6 أحرف على الأقل" required minlength="6">

                <label>تأكيد كلمة المرور <span style="color:red;">*</span></label>
                <input type="password" name="confirm_password" placeholder="أعد إدخال كلمة المرور" required minlength="6">

                <button type="submit" class="btn-submit">إنشاء الحساب</button>
            </form>

            <a href="login_username.php" style="text-decoration:none;display:block;">
                <button type="button" class="btn-back">رجوع لتسجيل الدخول</button>
            </a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>