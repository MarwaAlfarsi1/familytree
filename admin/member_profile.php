<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// التحقق من تسجيل الدخول (إدمن أو عضو)
$isAdmin = isset($_SESSION['admin_id']);
$isMember = isset($_SESSION['member_id']);

if (!$isAdmin && !$isMember) {
    // إذا لم يكن مسجل دخول، إعادة التوجيه حسب نوع الصفحة
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        header("Location: auth/login_username.php");
    } else {
        header("Location: auth/login.php");
    }
    exit();
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
    } catch (PDOException $e) {
        die("خطأ في الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage()));
    }
}

$functionsPath = __DIR__ . "/../functions.php";
if (!file_exists($functionsPath)) {
    $functionsPath = dirname(__DIR__) . "/functions.php";
}
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$personId = (int)($_GET['id'] ?? 0);
if ($personId <= 0) {
    // إذا كان عضو عادي، إعادة التوجيه إلى dashboard
    if ($isMember) {
        header("Location: dashboard.php");
    } else {
        header("Location: manage_people_new.php");
    }
    exit();
}

// جلب بيانات الشخص
$stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$person) {
    // إذا كان عضو عادي، إعادة التوجيه إلى dashboard
    if ($isMember) {
        header("Location: dashboard.php");
    } else {
        header("Location: manage_people_new.php");
    }
    exit();
}

// إذا كان عضو عادي (وليس إدمن)، التحقق من أنه يحاول عرض ملفه الشخصي أو ملف فرد من عائلته
$isFamilyMember = false;
if ($isMember && !$isAdmin) {
    $memberId = (int)$_SESSION['member_id'];
    
    // جلب بيانات العضو
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        header("Location: dashboard.php");
        exit();
    }
    
    // التحقق من أن الشخص هو العضو نفسه أو فرد من عائلته
    // إذا كان الشخص نفسه
    if ($personId == $memberId) {
        $isFamilyMember = true;
    }
    
    // التحقق من أنه زوج/زوجة
    if (!$isFamilyMember) {
        if (($person['spouse_person_id'] == $memberId && empty($person['spouse_is_external'])) ||
            ($member['spouse_person_id'] == $personId && empty($member['spouse_is_external']))) {
            $isFamilyMember = true;
        }
    }
    
    // التحقق من أنه ابن/ابنة
    if (!$isFamilyMember) {
        if (($member['gender'] === 'male' && $person['father_id'] == $memberId) ||
            ($member['gender'] === 'female' && $person['mother_id'] == $memberId)) {
            $isFamilyMember = true;
        }
    }
    
    if (!$isFamilyMember) {
        // العضو يحاول عرض ملف شخص ليس من عائلته - غير مسموح
        header("Location: dashboard.php");
        exit();
    }
} else {
    // إذا كان إدمن، يعتبر جميع الأشخاص من عائلته
    $isFamilyMember = true;
}

/** وظيفة بناء الاسم الكامل بالصيغة العربية التقليدية */
function buildFullArabicName($pdo, $person) {
    $name = trim($person['full_name'] ?? '');
    if (empty($name)) return '';
    
    $parts = [];
    
    // إضافة الاسم الأساسي
    $parts[] = $name;
    
    // جلب اسم الأب
    if (!empty($person['father_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id=? LIMIT 1");
            $stmt->execute([(int)$person['father_id']]);
            $father = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($father && !empty($father['full_name'])) {
                $gender = $person['gender'] ?? 'male';
                $connector = ($gender === 'female') ? 'بنت' : 'بن';
                $parts[] = $connector . ' ' . trim($father['full_name']);
                
                // جلب اسم الجد من الأب
                $stmt2 = $pdo->prepare("SELECT father_id FROM persons WHERE id=? LIMIT 1");
                $stmt2->execute([(int)$person['father_id']]);
                $fatherData = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($fatherData && !empty($fatherData['father_id'])) {
                    $stmt3 = $pdo->prepare("SELECT full_name FROM persons WHERE id=? LIMIT 1");
                    $stmt3->execute([(int)$fatherData['father_id']]);
                    $grandfather = $stmt3->fetch(PDO::FETCH_ASSOC);
                    if ($grandfather && !empty($grandfather['full_name'])) {
                        $parts[] = 'بن ' . trim($grandfather['full_name']);
                    }
                }
            }
        } catch (PDOException $e) {
            // تجاهل الأخطاء
        }
    }
    
    // إضافة القبيلة إذا كانت موجودة
    if (!empty($person['tribe'])) {
        $parts[] = 'والقبيلة ' . trim($person['tribe']);
    }
    
    return implode(' ', $parts);
}

// جلب الأب
$father = null;
if (!empty($person['father_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$person['father_id']]);
    $father = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب الأم
$mother = null;
if (!empty($person['mother_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$person['mother_id']]);
    $mother = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب الزوج/الزوجة
$spouse = null;
if (!empty($person['spouse_person_id']) && empty($person['spouse_is_external'])) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([$person['spouse_person_id']]);
    $spouse = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($person['spouse_is_external']) && !empty($person['external_tree_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
    $stmt->execute([$person['external_tree_id']]);
    $spouse = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب الزوج الثاني
$secondSpouse = null;
if (!empty($person['second_spouse_person_id']) && empty($person['second_spouse_is_external'])) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([$person['second_spouse_person_id']]);
    $secondSpouse = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($person['second_spouse_is_external']) && !empty($person['second_external_tree_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
    $stmt->execute([$person['second_external_tree_id']]);
    $secondSpouse = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب الأبناء
$children = [];
if ($person['gender'] === 'male') {
    // إذا كان ذكر، جلب جميع أطفاله
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE father_id = ? ORDER BY birth_date, full_name");
    $stmt->execute([$personId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إذا كان لديه زوجة خارجية، جلب أطفاله من الشجرة الخارجية أيضاً
    if (!empty($person['spouse_is_external']) && !empty($person['external_tree_id'])) {
        $stmt2 = $pdo->prepare("SELECT id FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
        $stmt2->execute([$person['external_tree_id']]);
        $externalSpouseRoot = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($externalSpouseRoot) {
            $stmt3 = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND father_id = ? ORDER BY birth_date, full_name");
            $stmt3->execute([$person['external_tree_id'], $externalSpouseRoot['id']]);
            $externalChildren = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            $children = array_merge($children, $externalChildren);
        }
    }
} else {
    // إذا كانت أنثى، جلب جميع أطفالها (من الزوج الأول والثاني)
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE mother_id = ? ORDER BY birth_date, full_name");
    $stmt->execute([$personId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إذا كان الزوج الثاني خارجي، جلب أبناءه من الشجرة الخارجية
    if (!empty($person['second_spouse_is_external']) && !empty($person['second_external_tree_id'])) {
        $stmt2 = $pdo->prepare("SELECT id FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
        $stmt2->execute([$person['second_external_tree_id']]);
        $externalSpouseRoot = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($externalSpouseRoot) {
            $stmt3 = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND father_id = ? ORDER BY birth_date, full_name");
            $stmt3->execute([$person['second_external_tree_id'], $externalSpouseRoot['id']]);
            $externalChildren = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            $children = array_merge($children, $externalChildren);
        }
    }
}

// جلب الصورة
$photoPath = null;
if (!empty($person['photo_path'])) {
    $originalPath = trim($person['photo_path']);
    
    // تنظيف المسار
    $originalPath = str_replace('\\', '/', $originalPath);
    
    // إذا كان المسار يبدأ بـ http أو https، استخدمه مباشرة
    if (strpos($originalPath, 'http://') === 0 || strpos($originalPath, 'https://') === 0) {
        $photoPath = $originalPath;
    } else {
        // تنظيف المسار
        $cleanPath = ltrim($originalPath, '/');
        
        // قائمة المسارات المحتملة للتحقق من وجود الملف
        $baseDir = dirname(__DIR__); // مجلد familytree
        $possiblePaths = [
            $baseDir . '/' . $cleanPath,  // من الجذر
            __DIR__ . '/' . $cleanPath,    // من مجلد admin
            $baseDir . '/admin/' . str_replace('admin/', '', $cleanPath), // إذا كان المسار يحتوي على admin/
            __DIR__ . '/uploads/persons/' . basename($cleanPath), // مباشرة من مجلد uploads
        ];
        
        // البحث عن الملف
        $found = false;
        $foundPath = null;
        foreach ($possiblePaths as $testPath) {
            $testPath = str_replace('\\', '/', $testPath);
            if (file_exists($testPath) && is_file($testPath)) {
                $foundPath = $testPath;
                $found = true;
                break;
            }
        }
        
        if ($found && $foundPath) {
            // تحويل المسار إلى مسار URL للعرض
            // المسار النسبي من الجذر
            $relativePath = str_replace($baseDir . '/', '', $foundPath);
            $photoPath = '/' . $relativePath;
            
            // إذا كان المسار يبدأ بـ admin/، أضف /familytree/ قبلها
            if (strpos($photoPath, '/admin/') === 0) {
                $photoPath = '/familytree' . $photoPath;
            }
        } else {
            // إذا لم نجد الملف، استخدم المسار الأصلي
            // إذا كان المسار يبدأ بـ admin/، أضف /familytree/ قبلها
            if (strpos($cleanPath, 'admin/') === 0) {
                $photoPath = '/familytree/' . $cleanPath;
            } else {
                $photoPath = '/' . $cleanPath;
            }
        }
    }
}

// بناء الاسم الكامل
$fullArabicName = buildFullArabicName($pdo, $person);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملف <?= h($person['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #3c2f2f;
            --accent: #f2c200;
            --line: #c4a77d;
            --bg: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--primary);
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
            flex: 1;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--accent);
            box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary);
            border: 1px solid rgba(191, 169, 138, 0.5);
        }

        .btn-secondary:hover {
            background: #fff;
            border-color: var(--line);
        }

        .profile-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                        0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .profile-header {
            display: flex;
            gap: 25px;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--line);
            box-shadow: 0 8px 20px rgba(60, 47, 47, 0.2);
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .detail-item i {
            color: var(--accent);
            font-size: 18px;
            margin-top: 2px;
            width: 20px;
        }

        .detail-item strong {
            color: var(--primary);
            font-weight: 700;
            min-width: 120px;
        }

        .detail-item span {
            color: #6b543f;
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--line);
        }

        .spouse-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .spouse-second {
            color: #9b59b6;
        }

        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .child-card {
            background: rgba(255, 255, 255, 0.6);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(196, 167, 125, 0.3);
            transition: all 0.3s;
        }

        .child-card:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 47, 47, 0.1);
        }

        .child-name {
            font-size: 16px;
            font-weight: 700;
            color: #2563eb;
            text-decoration: none;
            display: block;
            margin-bottom: 5px;
        }

        .child-name:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .child-info {
            font-size: 13px;
            color: #6b543f;
            margin-top: 5px;
        }

        .notes-section {
            background: rgba(255, 255, 255, 0.6);
            padding: 20px;
            border-radius: 12px;
            border-right: 4px solid var(--accent);
            margin-top: 15px;
        }

        .notes-text {
            color: #6b543f;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        /* شجرة العائلة المصغرة */
        .mini-family-tree {
            padding: 30px 20px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            border: 2px solid rgba(196, 167, 125, 0.2);
        }

        .tree-root {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .tree-person-card {
            background: linear-gradient(135deg, var(--primary) 0%, #2a2222 100%);
            color: var(--accent);
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
            min-width: 150px;
            box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
            transition: all 0.3s;
            position: relative;
        }

        .tree-person-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
        }

        .tree-person-card.tree-spouse {
            background: linear-gradient(135deg, var(--line) 0%, #a68b5a 100%);
            color: var(--primary);
        }

        .tree-person-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .tree-person-gender {
            font-size: 18px;
            opacity: 0.9;
        }

        .tree-connector {
            width: 40px;
            height: 3px;
            background: var(--line);
            position: relative;
        }

        .tree-connector::before {
            content: '';
            position: absolute;
            right: -5px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-right: 12px solid var(--line);
        }

        .tree-children-line {
            width: 3px;
            height: 30px;
            background: var(--line);
            margin: 0 auto 20px;
            position: relative;
        }

        .tree-children-line::before {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            transform: translateX(50%);
            width: 200px;
            height: 3px;
            background: var(--line);
        }

        .tree-children {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .tree-child-card {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid var(--line);
            border-radius: 10px;
            padding: 12px 18px;
            transition: all 0.3s;
            min-width: 140px;
        }

        .tree-child-card:hover {
            background: rgba(255, 255, 255, 1);
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 47, 47, 0.15);
        }

        .tree-child-link {
            text-decoration: none;
            color: inherit;
            display: block;
            text-align: center;
        }

        .tree-child-link .tree-person-name {
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .tree-child-link .tree-person-gender {
            color: var(--accent);
            font-size: 14px;
        }

        .tree-child-link:hover .tree-person-name {
            color: #2563eb;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .profile-details {
                grid-template-columns: 1fr;
            }

            .children-grid {
                grid-template-columns: 1fr;
            }

            .tree-root {
                flex-direction: column;
            }

            .tree-connector {
                width: 3px;
                height: 30px;
            }

            .tree-connector::before {
                display: none;
            }

            .tree-children-line::before {
                width: 100px;
            }
        }
    </style>
</head>
<body>
    <?php 
    $navPath = __DIR__ . '/nav.php';
    if (file_exists($navPath)) {
        include $navPath;
    }
    ?>
    <div class="container">
        <div class="header">
            <h1>ملف <?= h($person['full_name']) ?></h1>
                     <div class="header-actions">
                <?php if ($isAdmin || ($isMember && $isFamilyMember)): ?>
                <a href="edit_member.php?id=<?= $personId ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> تعديل
                </a>
                <?php endif; ?>
                <?php if (isset($_SESSION['admin_id']) && $_SESSION['admin_id']): ?>
                <a href="fix_missing_person_in_tree.php?id=<?= $personId ?>" class="btn" style="background: #f59e0b; color: white; margin-right: 10px;">
                    <i class="fas fa-wrench"></i> إصلاح في الشجرة
                </a>
                <?php endif; ?>
                <a href="<?= $isMember ? 'dashboard.php' : 'search.php' ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>

        <div class="profile-card">
            <div class="profile-header">
                <?php if ($photoPath): 
                    // محاولة إصلاح المسار إذا لم يبدأ بـ /familytree/
                    $finalPhotoPath = $photoPath;
                    if (strpos($finalPhotoPath, '/familytree/') === false && strpos($finalPhotoPath, 'http') !== 0) {
                        // إذا كان المسار يبدأ بـ /admin/، أضف /familytree/ قبلها
                        if (strpos($finalPhotoPath, '/admin/') === 0) {
                            $finalPhotoPath = '/familytree' . $finalPhotoPath;
                        } elseif (strpos($finalPhotoPath, 'admin/') === 0) {
                            $finalPhotoPath = '/familytree/' . $finalPhotoPath;
                        } elseif (strpos($finalPhotoPath, '/') !== 0) {
                            // إذا لم يبدأ بـ /، أضفه
                            $finalPhotoPath = '/' . $finalPhotoPath;
                        }
                    }
                ?>
                    <img src="<?= h($finalPhotoPath) ?>" alt="<?= h($person['full_name']) ?>" class="profile-photo" 
                         onerror="console.error('فشل تحميل الصورة:', '<?= h($finalPhotoPath) ?>', 'المسار الأصلي:', '<?= h($person['photo_path'] ?? '') ?>'); this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="profile-photo" style="background: linear-gradient(135deg, var(--line) 0%, var(--primary) 100%); display: none; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: bold; position: absolute; top: 0; left: 0;">
                        <i class="fas fa-user"></i>
                    </div>
                <?php else: ?>
                    <!-- صورة افتراضية إذا لم توجد صورة -->
                    <div class="profile-photo" style="background: linear-gradient(135deg, var(--line) 0%, var(--primary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: bold;">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div class="profile-info">
                    <div class="profile-name"><?= h($fullArabicName) ?></div>
                    <div class="profile-details">
                        <?php if (!empty($person['membership_number'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-id-card"></i>
                                <strong>رقم العضوية:</strong>
                                <span><?= h($person['membership_number']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <i class="fas fa-<?= $person['gender'] === 'male' ? 'mars' : 'venus' ?>"></i>
                            <strong>الجنس:</strong>
                            <span><?= $person['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></span>
                        </div>
                        <?php if ($father): ?>
                            <div class="detail-item">
                                <i class="fas fa-male"></i>
                                <strong>الأب:</strong>
                                <span><?= h($father['full_name']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($mother): ?>
                            <div class="detail-item">
                                <i class="fas fa-female"></i>
                                <strong>الأم:</strong>
                                <span><?= h($mother['full_name']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($person['birth_date'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-birthday-cake"></i>
                                <strong>تاريخ الميلاد:</strong>
                                <span><?= h($person['birth_date']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($person['birth_place'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <strong>مكان الميلاد:</strong>
                                <span><?= h($person['birth_place']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($person['death_date'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-heart-broken"></i>
                                <strong>تاريخ الوفاة:</strong>
                                <span><?= h($person['death_date']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($person['residence_location'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-home"></i>
                                <strong>مكان الإقامة:</strong>
                                <span><?= h($person['residence_location']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($person['phone_number'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <strong>رقم الهاتف:</strong>
                                <span><?= h($person['phone_number']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        // بناء الاسم الكامل للزوج الأول (للاستخدام في الشجرة أيضاً)
        $spouseFullName = null;
        if ($spouse) {
            $spouseFullName = buildFullArabicName($pdo, $spouse);
        }
        ?>
        
        <?php if ($spouse): ?>
            <div class="profile-card">
                <h2 class="section-title">الزوج الأول</h2>
                <div class="spouse-name"><?= h($spouseFullName) ?></div>
                <?php if (!empty($spouse['residence_location'])): ?>
                    <div class="detail-item">
                        <i class="fas fa-home"></i>
                        <strong>مكان الإقامة:</strong>
                        <span><?= h($spouse['residence_location']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($secondSpouse): ?>
            <?php
            // بناء الاسم الكامل للزوج الثاني
            $secondSpouseFullName = buildFullArabicName($pdo, $secondSpouse);
            ?>
            <div class="profile-card">
                <h2 class="section-title spouse-second">الزوج الثاني</h2>
                <div class="spouse-name spouse-second"><?= h($secondSpouseFullName) ?></div>
                <?php if (!empty($secondSpouse['residence_location'])): ?>
                    <div class="detail-item">
                        <i class="fas fa-home"></i>
                        <strong>مكان الإقامة:</strong>
                        <span><?= h($secondSpouse['residence_location']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($children)): ?>
            <div class="profile-card">
                <h2 class="section-title">الأبناء (<?= count($children) ?>)</h2>
                <div class="children-grid">
                    <?php foreach ($children as $child): ?>
                        <?php
                        // بناء الاسم الكامل للابن
                        $childFullName = buildFullArabicName($pdo, $child);
                        
                        // جلب زوجة/زوج الابن
                        $childSpouse = null;
                        if (!empty($child['spouse_person_id']) && empty($child['spouse_is_external'])) {
                            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id = ? LIMIT 1");
                            $stmt->execute([$child['spouse_person_id']]);
                            $childSpouse = $stmt->fetch(PDO::FETCH_ASSOC);
                        } elseif (!empty($child['spouse_is_external']) && !empty($child['external_tree_id'])) {
                            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
                            $stmt->execute([$child['external_tree_id']]);
                            $childSpouse = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                        ?>
                        <div class="child-card">
                            <a href="member_profile.php?id=<?= (int)$child['id'] ?>" class="child-name">
                                <?= h($childFullName) ?>
                            </a>
                            <?php if ($childSpouse): ?>
                                <div class="child-info" style="margin-top: 8px;">
                                    <i class="fas fa-<?= $child['gender'] === 'male' ? 'venus' : 'mars' ?>"></i>
                                    <?= ($child['gender'] === 'male') ? 'زوجة: ' : 'زوج: ' ?>
                                    <strong><?= h($childSpouse['full_name']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($child['birth_date'])): ?>
                                <div class="child-info">
                                    <i class="fas fa-calendar"></i> <?= h($child['birth_date']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($spouse || !empty($children)): ?>
            <div class="profile-card">
                <h2 class="section-title"><i class="fas fa-sitemap"></i> شجرة العائلة</h2>
                <div class="mini-family-tree">
                    <div class="tree-root">
                        <div class="tree-person-card">
                            <div class="tree-person-name"><?= h($person['full_name']) ?></div>
                            <div class="tree-person-gender">
                                <i class="fas fa-<?= $person['gender'] === 'male' ? 'mars' : 'venus' ?>"></i>
                            </div>
                        </div>
                        <?php if ($spouse): ?>
                            <div class="tree-connector"></div>
                            <div class="tree-person-card tree-spouse">
                                <div class="tree-person-name"><?= h($spouseFullName ?? $spouse['full_name']) ?></div>
                                <div class="tree-person-gender">
                                    <i class="fas fa-<?= $spouse['gender'] === 'male' ? 'mars' : 'venus' ?>"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($children)): ?>
                        <div class="tree-children-line"></div>
                        <div class="tree-children">
                            <?php foreach ($children as $child): ?>
                                <div class="tree-child-card">
                                    <a href="member_profile.php?id=<?= (int)$child['id'] ?>" class="tree-child-link">
                                        <div class="tree-person-name"><?= h(buildFullArabicName($pdo, $child)) ?></div>
                                        <div class="tree-person-gender">
                                            <i class="fas fa-<?= $child['gender'] === 'male' ? 'mars' : 'venus' ?>"></i>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($person['notes'])): ?>
            <div class="profile-card">
                <h2 class="section-title">ملاحظات</h2>
                <div class="notes-section">
                    <div class="notes-text"><?= h($person['notes']) ?></div>
                </div>
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