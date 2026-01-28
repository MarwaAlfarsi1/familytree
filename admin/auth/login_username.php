<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

$message = '';

try {
    require_once '../../config/db.php';
} catch (Exception $e) {
    $message = "خطأ في الاتصال بقاعدة البيانات";
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $message = "الرجاء إدخال جميع البيانات";
    } else {
        try {
            $checkColumn = $pdo->query("SHOW COLUMNS FROM admins LIKE 'username'");
            if ($checkColumn->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? OR username = ? LIMIT 1");
                $stmt->execute([$username, $username]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
                $stmt->execute([$username]);
            }
            
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                header("Location: ../dashboard_new.php");
                exit;
            } else {
                $message = "اسم المستخدم أو كلمة المرور غير صحيحة";
            }
        } catch (Exception $e) {
            $message = "حدث خطأ أثناء تسجيل الدخول";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تسجيل الدخول - شجرة العائلة</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-dark: #3c2f2f;
    --primary-gold: #f2c200;
    --secondary-brown: #6b543f;
    --light-gold: #c7a56b;
    --bg-light: #f5efe3;
    --bg-lighter: #e8ddd0;
    --white: #ffffff;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-lighter) 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(242, 194, 0, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(199, 165, 107, 0.08) 0%, transparent 50%);
    z-index: 0;
    pointer-events: none;
}

.main-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.login-container {
    width: 100%;
    max-width: 480px;
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 45px 40px;
    box-shadow: 0 15px 50px rgba(60, 47, 47, 0.12),
                0 0 0 1px rgba(255, 255, 255, 0.6) inset;
    border: 1px solid rgba(255, 255, 255, 0.4);
    text-align: center;
    transition: transform 0.3s ease;
}

.login-card:hover {
    transform: translateY(-2px);
}

.logo-section {
    margin-bottom: 35px;
}

.logo-icon {
    width: 90px;
    height: 90px;
    background: linear-gradient(135deg, var(--primary-gold) 0%, var(--light-gold) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 8px 25px rgba(242, 194, 0, 0.25);
    transition: transform 0.3s ease;
}

.logo-icon:hover {
    transform: scale(1.05);
}

.logo-icon i {
    font-size: 42px;
    color: var(--primary-dark);
}

h1 {
    color: var(--primary-dark);
    font-size: clamp(24px, 4vw, 28px);
    margin-bottom: 12px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.subtitle {
    color: var(--secondary-brown);
    font-size: 15px;
    margin-bottom: 30px;
    line-height: 1.7;
    font-weight: 300;
}

.form-group {
    margin-bottom: 20px;
    position: relative;
}

.form-group i {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary-brown);
    font-size: 18px;
    opacity: 0.7;
}

.form-group input {
    width: 100%;
    padding: 16px 50px 16px 20px;
    border-radius: 14px;
    border: 1.5px solid rgba(191, 169, 138, 0.4);
    font-size: 16px;
    background: rgba(255, 255, 255, 0.95);
    transition: all 0.3s ease;
    font-family: 'Cairo', sans-serif;
    color: var(--primary-dark);
}

.form-group input::placeholder {
    color: rgba(107, 84, 63, 0.5);
}

.form-group input:focus {
    outline: none;
    border-color: var(--light-gold);
    background: var(--white);
    box-shadow: 0 0 0 4px rgba(199, 165, 107, 0.08);
    transform: translateY(-1px);
}

.btn-login {
    width: 100%;
    padding: 16px;
    border-radius: 14px;
    font-size: 17px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: linear-gradient(135deg, var(--primary-dark) 0%, #2a2222 100%);
    color: var(--primary-gold);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.25);
    transition: all 0.3s ease;
    font-family: 'Cairo', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(60, 47, 47, 0.35);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-public {
    width: 100%;
    margin-top: 15px;
    padding: 14px;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    border: 2px solid var(--light-gold);
    background: rgba(255, 255, 255, 0.95);
    color: var(--primary-dark);
    transition: all 0.3s ease;
    font-family: 'Cairo', sans-serif;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-public:hover {
    background: var(--white);
    border-color: var(--primary-gold);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(242, 194, 0, 0.2);
}

.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-error {
    background: rgba(255, 236, 236, 0.95);
    color: #c40000;
    border: 1px solid rgba(196, 0, 0, 0.2);
}

.alert-info {
    background: rgba(240, 248, 255, 0.95);
    color: #2c5aa0;
    border: 1px solid rgba(44, 90, 160, 0.2);
}

.links-section {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid rgba(191, 169, 138, 0.25);
}

.link-item {
    display: block;
    text-align: center;
    color: var(--primary-dark);
    text-decoration: none;
    font-size: 14px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 10px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 500;
}

.link-item:hover {
    background: rgba(60, 47, 47, 0.08);
    transform: translateY(-2px);
}

@media (max-width: 480px) {
    body {
        padding: 15px;
    }
    
    .login-card {
        padding: 35px 25px;
    }
    
    .logo-icon {
        width: 75px;
        height: 75px;
    }
    
    .logo-icon i {
        font-size: 36px;
    }
    
    h1 {
        font-size: 22px;
    }
    
    .subtitle {
        font-size: 14px;
    }
}

@media (min-width: 481px) and (max-width: 768px) {
    .login-card {
        padding: 40px 30px;
    }
}
</style>
</head>
<body>

<div class="main-wrapper">
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-tree"></i>
                </div>
                <h1>شجرة عائلة العائلة الكريمة</h1>
                <div class="subtitle">
                    هنا تُوثّق الأنساب وتُحفظ الذكريات للأجيال القادمة
                </div>
            </div>

            <?php if($message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="اسم المستخدم أو البريد الإلكتروني" required autocomplete="username">
                </div>
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="كلمة المرور" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </button>
            </form>

            <a href="../../view_public.php/view_public.php" class="btn-public">
                <i class="fas fa-tree"></i>
                عرض شجرة العائلة
            </a>

            <div class="links-section">
                <a href="create_admin.php" class="link-item">
                    <i class="fas fa-user-plus"></i>
                    إنشاء حساب إدمن جديد
                </a>
                <a href="reset_password.php" class="link-item">
                    <i class="fas fa-key"></i>
                    إعادة تعيين كلمة المرور
                </a>
            </div>

            <div class="alert alert-info" style="margin-top: 20px;">
                <i class="fas fa-info-circle"></i>
                يمكنك استخدام اسم المستخدم أو البريد الإلكتروني
            </div>
        </div>
    </div>
</div>

<?php 
$footerPath = __DIR__ . '/../../footer.php';
if (file_exists($footerPath)) {
    include $footerPath;
}
?>

</body>
</html>