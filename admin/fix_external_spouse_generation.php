<?php
/**
 * سكريبت لإصلاح جيل الأزواج والزوجات من خارج العائلة
 * يجعلهم في نفس جيل أزواجهم/زوجاتهم
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_id'])) {
    die("يجب تسجيل الدخول كإدمن أولاً");
}

// تحميل ملف قاعدة البيانات المركزي
$dbPath = __DIR__ . "/../config/db.php";
if (!file_exists($dbPath)) {
    $dbPath = dirname(__DIR__) . "/config/db.php";
}
if (!file_exists($dbPath)) {
    die("خطأ: ملف قاعدة البيانات غير موجود. يرجى التأكد من وجود config/db.php");
}
require_once $dbPath;

// التحقق من وجود $pdo بعد التحميل
if (!isset($pdo) || !$pdo) {
    die("خطأ: فشل الاتصال بقاعدة البيانات");
}

// التحقق النهائي من وجود $pdo
if (!isset($pdo) || !$pdo || !($pdo instanceof PDO)) {
    die("خطأ: لا يوجد اتصال بقاعدة البيانات. يرجى التحقق من إعدادات قاعدة البيانات.");
}

$message = '';
$fixed = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. إصلاح الأزواج الخارجيين (spouse_is_external = 1)
        // جلب جميع الأشخاص الذين لديهم زوج/زوجة خارجي
        $stmt = $pdo->query("SELECT id, generation_level, spouse_person_id, external_tree_id 
                            FROM persons 
                            WHERE spouse_is_external = 1 
                            AND external_tree_id IS NOT NULL");
        $personsWithExternalSpouse = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($personsWithExternalSpouse as $person) {
            $personId = (int)$person['id'];
            $personGeneration = (int)$person['generation_level'];
            $externalTreeId = (int)$person['external_tree_id'];
            
            // جلب الزوج/الزوجة الخارجي
            $spouseStmt = $pdo->prepare("SELECT id, generation_level FROM persons 
                                        WHERE tree_id = ? AND is_root = 1 
                                        AND spouse_person_id = ? 
                                        LIMIT 1");
            $spouseStmt->execute([$externalTreeId, $personId]);
            $externalSpouse = $spouseStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($externalSpouse) {
                $spouseId = (int)$externalSpouse['id'];
                $spouseGeneration = (int)$externalSpouse['generation_level'];
                
                // إذا كان الجيل مختلفاً، قم بتحديثه
                if ($spouseGeneration !== $personGeneration) {
                    $updateStmt = $pdo->prepare("UPDATE persons SET generation_level = ? WHERE id = ?");
                    $updateStmt->execute([$personGeneration, $spouseId]);
                    $fixed++;
                }
            }
        }
        
        // 2. إصلاح الأزواج الثانيين الخارجيين (second_spouse_is_external = 1)
        $stmt2 = $pdo->query("SELECT id, generation_level, second_spouse_person_id, second_external_tree_id 
                             FROM persons 
                             WHERE second_spouse_is_external = 1 
                             AND second_external_tree_id IS NOT NULL");
        $personsWithSecondExternalSpouse = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($personsWithSecondExternalSpouse as $person) {
            $personId = (int)$person['id'];
            $personGeneration = (int)$person['generation_level'];
            $externalTreeId = (int)$person['second_external_tree_id'];
            
            // جلب الزوج الثاني الخارجي
            $spouseStmt = $pdo->prepare("SELECT id, generation_level FROM persons 
                                        WHERE tree_id = ? AND is_root = 1 
                                        LIMIT 1");
            $spouseStmt->execute([$externalTreeId]);
            $externalSpouse = $spouseStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($externalSpouse) {
                $spouseId = (int)$externalSpouse['id'];
                $spouseGeneration = (int)$externalSpouse['generation_level'];
                
                // إذا كان الجيل مختلفاً، قم بتحديثه
                if ($spouseGeneration !== $personGeneration) {
                    $updateStmt = $pdo->prepare("UPDATE persons SET generation_level = ? WHERE id = ?");
                    $updateStmt->execute([$personGeneration, $spouseId]);
                    $fixed++;
                }
            }
        }
        
        $pdo->commit();
        $message = "تم إصلاح $fixed سجل بنجاح!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "حدث خطأ: " . htmlspecialchars($e->getMessage());
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
<title>إصلاح جيل الأزواج الخارجيين</title>
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
    width: 100%;
    max-width: 600px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

h2 {
    color: #3c2f2f;
    font-size: 24px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 700;
}

.info {
    background: rgba(240, 248, 255, 0.9);
    color: #2c5aa0;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
    border: 1px solid rgba(44, 90, 160, 0.2);
}

.success {
    background: rgba(236, 255, 236, 0.9);
    color: #006400;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid rgba(0, 100, 0, 0.2);
}

.error {
    background: rgba(255, 236, 236, 0.9);
    color: #c40000;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid rgba(196, 0, 0, 0.2);
}

.warning {
    background: rgba(255, 245, 230, 0.9);
    color: #856404;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid rgba(133, 100, 4, 0.2);
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

a {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #6b543f;
    text-decoration: none;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s;
}

a:hover {
    background: rgba(196, 167, 125, 0.1);
}
</style>
</head>
<body>
<div class="glass-box">
    <h2>🔧 إصلاح جيل الأزواج الخارجيين</h2>
    
    <div class="info">
        <strong>الوظيفة:</strong> هذا السكريبت يقوم بإصلاح جيل الأزواج والزوجات من خارج العائلة ليكونوا في نفس جيل أزواجهم/زوجاتهم.
    </div>
    
    <?php if($message): ?>
        <div class="<?= strpos($message, 'خطأ') !== false ? 'error' : 'success' ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>
    
    <div class="warning">
        <strong>⚠️ تحذير:</strong> تأكد من عمل نسخة احتياطية من قاعدة البيانات قبل تشغيل هذا السكريبت.
    </div>
    
    <form method="POST">
        <button type="submit" name="fix" value="1">إصلاح البيانات</button>
    </form>
    
    <a href="manage_people_new.php">العودة لإدارة الأفراد</a>
</div>
</body>
</html>