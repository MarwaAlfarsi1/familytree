<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_id'])) { 
    header("Location: auth/login_username.php"); 
    exit(); 
}

// تحديد مسار ملفات config
$dbPath = null;
$possiblePaths = [
    __DIR__ . "/../db.php",
    dirname(__DIR__) . "/db.php",
    $_SERVER['DOCUMENT_ROOT'] . "/familytree/db.php",
    dirname(dirname(__FILE__)) . "/db.php"
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $dbPath = $path;
        break;
    }
}

if ($dbPath && file_exists($dbPath)) {
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
        die("خطأ في الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage()));
    }
}

if (!isset($pdo) || !$pdo) {
    // محاولة الاتصال المباشر مرة أخرى
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
        die("خطأ في الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage()));
    }
}

$message = '';
$personInfo = null;
$fixed = false;

// جلب person_id من POST أو GET
$personId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['person_id'])) {
    $personId = (int)$_POST['person_id'];
} elseif (isset($_GET['id'])) {
    $personId = (int)$_GET['id'];
}

if ($personId && $personId > 0) {
    // جلب بيانات الشخص أولاً للعرض
    try {
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
        $stmt->execute([$personId]);
        $personInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $message = "خطأ في جلب البيانات: " . htmlspecialchars($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['person_id'])) {
    $personId = (int)$_POST['person_id'];
    
    try {
        // جلب بيانات الشخص
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
        $stmt->execute([$personId]);
        $personInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$personInfo) {
            $message = "خطأ: لم يتم العثور على الشخص";
        } else {
            $updates = [];
            
            // التحقق من father_id
            if (empty($personInfo['father_id']) || $personInfo['father_id'] == 0 || $personInfo['father_id'] == NULL) {
                // البحث عن الأب بالاسم
                $fatherName = null;
                $fullName = $personInfo['full_name'];
                
                // محاولة استخراج اسم الأب من الاسم الكامل
                // مثال: "سالم بن مبارك بن مرهون" -> الأب هو "مبارك"
                // محاولة عدة أنماط
                if (preg_match('/بن\s+([^بن]+?)(?:\s+بن|\s+ال|$)/u', $fullName, $matches)) {
                    $fatherName = trim($matches[1]);
                } elseif (preg_match('/بن\s+([^\s]+)/u', $fullName, $matches)) {
                    $fatherName = trim($matches[1]);
                }
                
                if ($fatherName) {
                    // البحث عن الأب في قاعدة البيانات - محاولة عدة طرق
                    // 1. البحث بالاسم الكامل (يبدأ بـ)
                    $fatherStmt = $pdo->prepare("SELECT id, full_name FROM persons WHERE full_name LIKE ? AND id != ? LIMIT 10");
                    $fatherStmt->execute([$fatherName . '%', $personId]);
                    $fathers = $fatherStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 2. إذا لم يتم العثور، البحث في أي مكان في الاسم
                    if (empty($fathers)) {
                        $fatherStmt = $pdo->prepare("SELECT id, full_name FROM persons WHERE full_name LIKE ? AND id != ? LIMIT 10");
                        $fatherStmt->execute(['%' . $fatherName . '%', $personId]);
                        $fathers = $fatherStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    $foundFather = null;
                    
                    // البحث عن الأب الذي يبدأ اسمه بـ "مبارك" أو يحتوي عليه في البداية
                    foreach ($fathers as $f) {
                        // إذا كان الاسم يبدأ بـ اسم الأب المستخرج
                        if (strpos($f['full_name'], $fatherName) === 0 || preg_match('/^' . preg_quote($fatherName, '/') . '/u', $f['full_name'])) {
                            $foundFather = $f;
                            break;
                        }
                    }
                    
                    // إذا لم يتم العثور، خذ الأول الذي يحتوي على الاسم
                    if (!$foundFather && !empty($fathers)) {
                        $foundFather = $fathers[0];
                    }
                    
                    if ($foundFather) {
                        $updates['father_id'] = (int)$foundFather['id'];
                        $message .= "✓ تم العثور على الأب: " . htmlspecialchars($foundFather['full_name']) . " (ID: " . $foundFather['id'] . ")<br>";
                    } else {
                        $message .= "⚠ لم يتم العثور على الأب بالاسم: " . htmlspecialchars($fatherName) . "<br>";
                        $message .= "<small>يمكنك ربطه يدوياً من صفحة التعديل</small><br>";
                    }
                } else {
                    $message .= "⚠ لم يتم استخراج اسم الأب من الاسم الكامل: " . htmlspecialchars($fullName) . "<br>";
                }
            } else {
                $message .= "✓ father_id موجود: " . (int)$personInfo['father_id'] . "<br>";
            }
            
            // التحقق من tree_id
            if (empty($personInfo['tree_id']) || $personInfo['tree_id'] == 0) {
                // جلب tree_id من الأب إذا كان موجوداً
                if (!empty($updates['father_id'])) {
                    $fatherStmt = $pdo->prepare("SELECT tree_id FROM persons WHERE id = ? LIMIT 1");
                    $fatherStmt->execute([$updates['father_id']]);
                    $fatherData = $fatherStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fatherData && !empty($fatherData['tree_id'])) {
                        $updates['tree_id'] = (int)$fatherData['tree_id'];
                        $message .= "✓ تم تعيين tree_id من الأب: " . $fatherData['tree_id'] . "<br>";
                    }
                }
                
                // إذا لم يتم العثور على tree_id، استخدم الشجرة الرئيسية
                if (empty($updates['tree_id'])) {
                    $mainTreeStmt = $pdo->query("SELECT id FROM trees WHERE tree_type='main' LIMIT 1");
                    $mainTree = $mainTreeStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($mainTree) {
                        $updates['tree_id'] = (int)$mainTree['id'];
                        $message .= "✓ تم تعيين tree_id للشجرة الرئيسية: " . $mainTree['id'] . "<br>";
                    }
                }
            }
            
            // التحقق من generation_level
            $fatherIdForGen = !empty($updates['father_id']) ? $updates['father_id'] : (!empty($personInfo['father_id']) ? $personInfo['father_id'] : null);
            
            if (empty($personInfo['generation_level']) && $personInfo['generation_level'] !== '0' && $personInfo['generation_level'] !== 0) {
                if ($fatherIdForGen) {
                    $fatherStmt = $pdo->prepare("SELECT generation_level FROM persons WHERE id = ? LIMIT 1");
                    $fatherStmt->execute([$fatherIdForGen]);
                    $fatherData = $fatherStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fatherData && isset($fatherData['generation_level']) && $fatherData['generation_level'] !== null) {
                        $updates['generation_level'] = (int)$fatherData['generation_level'] + 1;
                        $message .= "✓ تم تعيين generation_level: " . $updates['generation_level'] . "<br>";
                    }
                }
            } elseif ($fatherIdForGen && isset($personInfo['generation_level'])) {
                // التحقق من أن generation_level صحيح مقارنة بالأب
                $fatherStmt = $pdo->prepare("SELECT generation_level FROM persons WHERE id = ? LIMIT 1");
                $fatherStmt->execute([$fatherIdForGen]);
                $fatherData = $fatherStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($fatherData && isset($fatherData['generation_level']) && $fatherData['generation_level'] !== null) {
                    $expectedGen = (int)$fatherData['generation_level'] + 1;
                    $currentGen = (int)$personInfo['generation_level'];
                    
                    if ($currentGen != $expectedGen) {
                        $updates['generation_level'] = $expectedGen;
                        $message .= "✓ تم تصحيح generation_level من " . $currentGen . " إلى " . $expectedGen . "<br>";
                    }
                }
            }
            
            // تطبيق التحديثات
            if (!empty($updates)) {
                $pdo->beginTransaction();
                try {
                    $setParts = [];
                    $values = [];
                    
                    foreach ($updates as $field => $value) {
                        $setParts[] = "$field = ?";
                        $values[] = $value;
                    }
                    
                    $values[] = $personId;
                    $updateSql = "UPDATE persons SET " . implode(", ", $setParts) . " WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute($values);
                    
                    $pdo->commit();
                    $fixed = true;
                    $message = "<strong style='color: green;'>تم إصلاح البيانات بنجاح!</strong><br>" . $message;
                    
                    // إعادة جلب البيانات المحدثة
                    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
                    $stmt->execute([$personId]);
                    $personInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "خطأ في التحديث: " . htmlspecialchars($e->getMessage());
                }
            } else {
                // التحقق مرة أخرى من البيانات
                $hasIssues = false;
                if (empty($personInfo['father_id']) || $personInfo['father_id'] == 0 || $personInfo['father_id'] == NULL) {
                    $hasIssues = true;
                    $message .= "⚠ father_id غير مرتبط<br>";
                }
                if (empty($personInfo['mother_id']) || $personInfo['mother_id'] == 0 || $personInfo['mother_id'] == NULL) {
                    // mother_id غير مطلوب دائماً، لكن نذكره
                    if (!$hasIssues) {
                        $message .= "ℹ mother_id غير مرتبط (هذا طبيعي إذا كانت الأم من خارج العائلة)<br>";
                    }
                }
                
                if (!$hasIssues) {
                    $message = "✓ لا توجد مشاكل في البيانات. الشخص مرتبط بشكل صحيح.";
                } else {
                    $message = "<strong style='color: orange;'>تحذير:</strong> هناك مشاكل في البيانات لكن لم يتم إصلاحها تلقائياً. يرجى المحاولة مرة أخرى أو الربط يدوياً.<br>" . $message;
                }
            }
        }
    } catch (Exception $e) {
        $message = "خطأ: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح شخص مفقود من الشجرة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #3c2f2f;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #3c2f2f;
        }
        input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        button {
            background: #3c2f2f;
            color: #f2c200;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background: #2a2222;
        }
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            background: #f0f0f0;
            border-right: 4px solid #3c2f2f;
        }
        .person-info {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .person-info h3 {
            color: #3c2f2f;
            margin-bottom: 15px;
        }
        .person-info p {
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 5px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3c2f2f;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="member_profile.php?id=<?= isset($_GET['id']) ? (int)$_GET['id'] : '' ?>" class="back-link">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
        
        <h1><i class="fas fa-wrench"></i> إصلاح شخص مفقود من الشجرة</h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="person_id">رقم معرف الشخص (ID):</label>
                <input type="number" id="person_id" name="person_id" 
                       value="<?= isset($_GET['id']) ? (int)$_GET['id'] : '' ?>" 
                       required>
            </div>
            <button type="submit">
                <i class="fas fa-search"></i> فحص وإصلاح
            </button>
        </form>
        
        <?php if ($message): ?>
            <div class="message">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($personInfo): ?>
            <div class="person-info">
                <h3>بيانات الشخص:</h3>
                <p><strong>الاسم:</strong> <?= htmlspecialchars($personInfo['full_name']) ?></p>
                <p><strong>ID:</strong> <?= (int)$personInfo['id'] ?></p>
                <p><strong>father_id:</strong> <?= !empty($personInfo['father_id']) ? (int)$personInfo['father_id'] : '<span style="color: red;">غير مرتبط</span>' ?></p>
                <p><strong>mother_id:</strong> <?= !empty($personInfo['mother_id']) ? (int)$personInfo['mother_id'] : '<span style="color: red;">غير مرتبط</span>' ?></p>
                <p><strong>tree_id:</strong> <?= !empty($personInfo['tree_id']) ? (int)$personInfo['tree_id'] : '<span style="color: red;">غير مرتبط</span>' ?></p>
                <p><strong>generation_level:</strong> <?= isset($personInfo['generation_level']) ? (int)$personInfo['generation_level'] : '<span style="color: red;">غير محدد</span>' ?></p>
                <p><strong>is_root:</strong> <?= !empty($personInfo['is_root']) ? 'نعم' : 'لا' ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($fixed): ?>
            <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724;">
                <strong>✓ تم الإصلاح بنجاح!</strong> يرجى تحديث صفحة الشجرة للتحقق من ظهور الشخص.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>