<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// تحميل ملفات النظام - استخدام طرق متعددة للعثور على الملف
try {
    // طريقة 1: استخدام $_SERVER['DOCUMENT_ROOT']
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $dbPath1 = $docRoot . '/familytree/db.php';
    
    // طريقة 2: استخدام dirname مع __DIR__
    $rootDir = dirname(dirname(__DIR__));
    $dbPath2 = $rootDir . '/db.php';
    
    // طريقة 3: استخدام $_SERVER['SCRIPT_FILENAME']
    $scriptDir = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $dbPath3 = $scriptDir . '/db.php';
    
    // محاولة العثور على الملف
    $dbPath = null;
    if (file_exists($dbPath1)) {
        $dbPath = $dbPath1;
    } elseif (file_exists($dbPath2)) {
        $dbPath = $dbPath2;
    } elseif (file_exists($dbPath3)) {
        $dbPath = $dbPath3;
    } else {
        // محاولة البحث في المجلد الحالي والمجلدات الأب
        $currentDir = __DIR__;
        for ($i = 0; $i < 5; $i++) {
            $testPath = $currentDir . str_repeat('/..', $i) . '/db.php';
            $realPath = realpath($testPath);
            if ($realPath && file_exists($realPath)) {
                $dbPath = $realPath;
                break;
            }
        }
    }
    
    if (!$dbPath || !file_exists($dbPath)) {
        // إذا لم نجد الملف، نحاول الاتصال مباشرة بقاعدة البيانات
        // قراءة ملف .env أولاً إن وجد
        $envFile = null;
        $envPaths = [
            $docRoot . '/familytree/.env',
            $rootDir . '/.env',
            dirname(__DIR__) . '/../.env',
            dirname(dirname(__DIR__)) . '/.env'
        ];
        
        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $envFile = $envPath;
                break;
            }
        }
        
        $env = [];
        if ($envFile) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    $env[$key] = $value;
                }
            }
        }
        
        // استخدام القيم من .env أو القيم الافتراضية
        $host = $env['DB_HOST'] ?? 'localhost';
        $dbname = $env['DB_NAME'] ?? 'u480768868_family_tree';
        $username = $env['DB_USER'] ?? 'u480768868_Mmm111999';
        $password = $env['DB_PASS'] ?? 'Mmmm@@999';
        $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
        
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=$charset",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci"
                ]
            );
        } catch (PDOException $e) {
            die("خطأ في الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage()));
        }
    } else {
        require_once $dbPath;
        // التأكد من أن $pdo معرف بعد تحميل db.php
        if (!isset($pdo) || !$pdo) {
            // محاولة الاتصال مباشرة
            $host = 'localhost';
            $dbname = 'u480768868_family_tree';
            $username = 'u480768868_Mmm111999';
            $password = 'Mmmm@@999';
            $charset = 'utf8mb4';
            
            try {
                $pdo = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=$charset",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci"
                    ]
                );
            } catch (PDOException $e) {
                die("خطأ في الاتصال بقاعدة البيانات");
            }
        }
    }
    
    // تحميل ملف functions.php
    $functionsPath = null;
    if ($dbPath) {
        $functionsPath = dirname($dbPath) . '/functions.php';
    } else {
        $functionsPath1 = $docRoot . '/familytree/functions.php';
        $functionsPath2 = $rootDir . '/functions.php';
        $functionsPath3 = $scriptDir . '/functions.php';
        
        if (file_exists($functionsPath1)) {
            $functionsPath = $functionsPath1;
        } elseif (file_exists($functionsPath2)) {
            $functionsPath = $functionsPath2;
        } elseif (file_exists($functionsPath3)) {
            $functionsPath = $functionsPath3;
        }
    }
    
    if ($functionsPath && file_exists($functionsPath)) {
        require_once $functionsPath;
    } else {
        // إذا لم نجد ملف functions.php، نعرف الدوال الأساسية هنا
        if (!function_exists('h')) {
            function h($v) {
                return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            }
        }
        
        if (!function_exists('verifyMemberLogin')) {
            function verifyMemberLogin($pdo, $username, $password) {
                $username = trim($username);
                $password = trim($password);
                
                if (empty($username) || empty($password)) {
                    return false;
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT * FROM persons WHERE username = ? AND username IS NOT NULL AND username != '' LIMIT 1");
                    $stmt->execute([$username]);
                    $member = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$member || empty($member['password_hash'])) {
                        return false;
                    }
                    
                    if (password_verify($password, $member['password_hash'])) {
                        return $member;
                    }
                } catch (Exception $e) {
                    return false;
                }
                
                return false;
            }
        }
    }
} catch (Exception $e) {
    die("خطأ في تحميل الملفات: " . htmlspecialchars($e->getMessage()));
}

// التحقق من وجود اتصال قاعدة البيانات
if (!isset($pdo) || !$pdo) {
    die("خطأ: لا يوجد اتصال بقاعدة البيانات");
}

// إذا كان مسجل دخول كعضو، إعادة التوجيه
if (isset($_SESSION['member_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $message = "الرجاء إدخال اسم المستخدم وكلمة المرور";
    } else {
        try {
            if (!function_exists('verifyMemberLogin')) {
                throw new Exception("دالة التحقق غير موجودة");
            }
            
            $member = verifyMemberLogin($pdo, $username, $password);
            
            if ($member) {
                $_SESSION['member_id'] = $member['id'];
                $_SESSION['member_name'] = $member['full_name'];
                header("Location: ../dashboard.php");
                exit();
            } else {
                $message = "اسم المستخدم أو كلمة المرور غير صحيحة";
            }
        } catch (Exception $e) {
            $message = "حدث خطأ أثناء تسجيل الدخول. يرجى المحاولة مرة أخرى.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تسجيل دخول العضو</title>
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
    max-width: 420px;
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

.btn-login {
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
    box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
}

.btn-public {
    margin-top: 12px;
    background: rgba(255, 255, 255, 0.9);
    color: #3c2f2f;
    border: 1px solid rgba(191, 169, 138, 0.5);
}

.btn-public:hover {
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
        font-size: 20px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="glass-box">
        <h2>تسجيل دخول العضو</h2>
        <div class="tagline">
            أدخل بياناتك للوصول إلى حسابك الشخصي
        </div>

        <?php if($message): ?>
            <div class="error"><?= function_exists('h') ? h($message) : htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="اسم المستخدم" required autocomplete="username">
            <input type="password" name="password" placeholder="كلمة المرور" required autocomplete="current-password">
            <button type="submit" class="btn-login">تسجيل الدخول</button>
        </form>

        <div style="margin-top: 15px; text-align: center;">
            <a href="forgot_password.php" style="color: #2563eb; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fas fa-key"></i> نسيت اسم المستخدم أو كلمة المرور؟
            </a>
        </div>

        <a href="../../view_public.php" style="text-decoration:none;display:block;">
            <button type="button" class="btn-public">عرض شجرة العائلة</button>
        </a>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(191, 169, 138, 0.3);">
            <a href="/familytree/admin/request_account.php" style="display: block; text-align: center; color: #3c2f2f; text-decoration: none; font-size: 14px; padding: 8px; background: rgba(255, 255, 255, 0.5); border-radius: 8px; transition: all 0.3s;" 
               onmouseover="this.style.background='rgba(60, 47, 47, 0.1)'" 
               onmouseout="this.style.background='rgba(255, 255, 255, 0.5)'">
                📝 طلب حساب جديد
            </a>
        </div>

        <div class="info" style="margin-top: 15px;">
            ليس لديك حساب؟ أرسل طلب حساب جديد وانتظر موافقة الإدمن
        </div>
    </div>
</div>

</body>
</html>