<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

$functionsPath = __DIR__ . "/../functions.php";
if (!file_exists($functionsPath)) {
    $functionsPath = dirname(__DIR__) . "/functions.php";
}
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

// التأكد من وجود دالة h()
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!isset($_SESSION['member_id'])) {
    header("Location: auth/login.php");
    exit();
}

$memberId = (int)$_SESSION['member_id'];

// معالجة تحديث اسم الأب أو الأم الخارجي
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_parent_name'])) {
    $parentType = $_POST['parent_type'] ?? '';
    $parentName = trim($_POST['parent_name'] ?? '');
    
    if (in_array($parentType, ['father', 'mother']) && !empty($parentName)) {
        $fieldName = $parentType === 'father' ? 'external_father_name' : 'external_mother_name';
        try {
            // التحقق من وجود العمود، وإذا لم يكن موجوداً نستخدم ALTER TABLE
            $checkColumn = $pdo->query("SHOW COLUMNS FROM persons LIKE '$fieldName'");
            if ($checkColumn->rowCount() === 0) {
                $pdo->exec("ALTER TABLE persons ADD COLUMN $fieldName VARCHAR(255) NULL");
            }
            
            $updateStmt = $pdo->prepare("UPDATE persons SET $fieldName = ? WHERE id = ?");
            $updateStmt->execute([$parentName, $memberId]);
            $_SESSION['success_message'] = "تم تحديث اسم " . ($parentType === 'father' ? 'الأب' : 'الأم') . " بنجاح.";
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $error_message = "حدث خطأ أثناء التحديث: " . htmlspecialchars($e->getMessage());
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    session_destroy();
    header("Location: auth/login.php");
    exit();
}

// جلب أطفال العضو
$children = [];
if ($member['gender'] === 'male') {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE father_id = ? ORDER BY birth_date, full_name");
    $stmt->execute([$memberId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // إذا كانت أنثى، جلب أطفالها من الزوج
    if (!empty($member['spouse_person_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE mother_id = ? ORDER BY birth_date, full_name");
        $stmt->execute([$memberId]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// جلب الأب
$father = null;
if (!empty($member['father_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$member['father_id']]);
    $father = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب الأم
$mother = null;
if (!empty($member['mother_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$member['mother_id']]);
    $mother = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب الزوج/الزوجة
$spouse = null;
if (!empty($member['spouse_person_id']) && empty($member['spouse_is_external'])) {
    // زوج/زوجة داخلي
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
    $stmt->execute([$member['spouse_person_id']]);
    $spouse = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($member['spouse_is_external']) && !empty($member['external_tree_id'])) {
    // زوج/زوجة خارجي
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
    $stmt->execute([$member['external_tree_id']]);
    $spouse = $stmt->fetch(PDO::FETCH_ASSOC);
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لوحة تحكم العضو</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* {
    font-family: 'Cairo', sans-serif;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
}

.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-bottom: 20px;
}

.max-w-4xl {
    max-width: 56rem;
}

.mx-auto {
    margin-left: auto;
    margin-right: auto;
}

.flex {
    display: flex;
}

.items-center {
    align-items: center;
}

.justify-between {
    justify-content: space-between;
}

.mb-6 {
    margin-bottom: 1.5rem;
}

.flex-wrap {
    flex-wrap: wrap;
}

.gap-3 {
    gap: 0.75rem;
}

.text-2xl {
    font-size: 1.5rem;
}

.md\:text-3xl {
    font-size: 1.875rem;
}

.font-bold {
    font-weight: 700;
}

.text-xl {
    font-size: 1.25rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.space-y-2 > * + * {
    margin-top: 0.5rem;
}

.space-y-3 > * + * {
    margin-top: 0.75rem;
}

.text-gray-600 {
    color: #4b5563;
}

.bg-white {
    background-color: #fff;
}

.bg-opacity-50 {
    opacity: 0.5;
}

.bg-gradient-to-br {
    background: linear-gradient(to bottom right, var(--tw-gradient-stops));
}

.from-\[#f5efe3\] {
    --tw-gradient-from: #f5efe3;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(245, 239, 227, 0));
}

.to-\[#e8ddd0\] {
    --tw-gradient-to: #e8ddd0;
}

.min-h-screen {
    min-height: 100vh;
}

.p-4 {
    padding: 1rem;
}

@media (min-width: 768px) {
    .md\:text-3xl {
        font-size: 1.875rem;
    }
}

.p-3 {
    padding: 0.75rem;
}

.rounded-lg {
    border-radius: 0.5rem;
}

.font-semibold {
    font-weight: 600;
}

.text-sm {
    font-size: 0.875rem;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    margin: 5px;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.9);
    color: #3c2f2f;
    border: 1px solid rgba(191, 169, 138, 0.5);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>
</head>
<body style="background: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%); min-height: 100vh; padding: 1rem; display: flex; flex-direction: column;">
    <?php 
    $navPath = __DIR__ . '/nav.php';
    if (file_exists($navPath)) {
        include $navPath;
    }
    ?>
<div class="max-w-4xl mx-auto" style="flex: 1;">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#3c2f2f]">مرحباً، <?= h($member['full_name']) ?></h1>
        <a href="auth/logout.php" class="btn btn-secondary">تسجيل الخروج</a>
    </div>

    <?php
    // بناء الاسم الكامل للعضو
    $memberFullName = buildFullArabicName($pdo, $member);
    if (empty($memberFullName)) {
        $memberFullName = $member['full_name'];
    }
    
    // جلب الصورة
    $memberPhotoPath = null;
    if (!empty($member['photo_path'])) {
        $originalPath = trim($member['photo_path']);
        $originalPath = str_replace('\\', '/', $originalPath);
        
        if (strpos($originalPath, 'http://') === 0 || strpos($originalPath, 'https://') === 0) {
            $memberPhotoPath = $originalPath;
        } else {
            $cleanPath = ltrim($originalPath, '/');
            $baseDir = dirname(__DIR__);
            $possiblePaths = [
                $baseDir . '/' . $cleanPath,
                __DIR__ . '/' . $cleanPath,
                $baseDir . '/admin/' . str_replace('admin/', '', $cleanPath),
                __DIR__ . '/uploads/persons/' . basename($cleanPath),
            ];
            
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
                $relativePath = str_replace($baseDir . '/', '', $foundPath);
                $memberPhotoPath = '/' . $relativePath;
                if (strpos($memberPhotoPath, '/admin/') === 0) {
                    $memberPhotoPath = '/familytree' . $memberPhotoPath;
                }
            } else {
                if (strpos($cleanPath, 'admin/') === 0) {
                    $memberPhotoPath = '/familytree/' . $cleanPath;
                } else {
                    $memberPhotoPath = '/' . $cleanPath;
                }
            }
        }
    }
    ?>
    <div class="glass-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-[#3c2f2f]">معلوماتي الشخصية</h2>
            <a href="edit_member.php?id=<?= $memberId ?>" class="btn btn-secondary" style="padding: 8px 15px; font-size: 12px; background: #f2c200; color: #3c2f2f;">
                <i class="fas fa-edit"></i> تعديل
            </a>
        </div>
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <?php if ($memberPhotoPath): 
                $finalPhotoPath = $memberPhotoPath;
                if (strpos($finalPhotoPath, '/familytree/') === false && strpos($finalPhotoPath, 'http') !== 0) {
                    if (strpos($finalPhotoPath, '/admin/') === 0) {
                        $finalPhotoPath = '/familytree' . $finalPhotoPath;
                    } elseif (strpos($finalPhotoPath, 'admin/') === 0) {
                        $finalPhotoPath = '/familytree/' . $finalPhotoPath;
                    } elseif (strpos($finalPhotoPath, '/') !== 0) {
                        $finalPhotoPath = '/' . $finalPhotoPath;
                    }
                }
            ?>
                <img src="<?= h($finalPhotoPath) ?>" alt="<?= h($member['full_name']) ?>" 
                     style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #c4a77d; flex-shrink: 0;"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #c4a77d 0%, #3c2f2f 100%); display: none; align-items: center; justify-content: center; color: white; font-size: 36px; flex-shrink: 0;">
                    <i class="fas fa-user"></i>
                </div>
            <?php else: ?>
                <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #c4a77d 0%, #3c2f2f 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px; flex-shrink: 0;">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            <div style="flex: 1;">
                <p style="font-size: 20px; font-weight: 700; color: #3c2f2f; margin-bottom: 15px;"><?= h($memberFullName) ?></p>
                <div class="space-y-2">
                    <?php if (!empty($member['membership_number'])): ?>
                        <p><strong>رقم العضوية:</strong> <?= h($member['membership_number']) ?></p>
                    <?php endif; ?>
                    <p><strong>الجنس:</strong> <?= $member['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></p>
                    
                    <!-- عرض الأب -->
                    <?php if ($father): 
                        $fatherFullName = buildFullArabicName($pdo, $father);
                        if (empty($fatherFullName)) {
                            $fatherFullName = $father['full_name'];
                        }
                    ?>
                        <p><strong>الأب:</strong> 
                            <a href="member_profile.php?id=<?= (int)$father['id'] ?>" style="color: #2563eb; text-decoration: underline;">
                                <?= h($fatherFullName) ?>
                            </a>
                        </p>
                    <?php elseif (!empty($member['external_father_name'])): ?>
                        <p><strong>الأب:</strong> <?= h($member['external_father_name']) ?> 
                            <a href="#" onclick="document.getElementById('edit-father-form').style.display='block'; return false;" style="color: #2563eb; text-decoration: none; font-size: 12px; margin-right: 5px;">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                        </p>
                    <?php else: ?>
                        <p><strong>الأب:</strong> 
                            <a href="#" onclick="document.getElementById('edit-father-form').style.display='block'; return false;" style="color: #2563eb; text-decoration: underline;">
                                <i class="fas fa-plus"></i> إضافة اسم الأب
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <!-- عرض الأم -->
                    <?php if ($mother): 
                        $motherFullName = buildFullArabicName($pdo, $mother);
                        if (empty($motherFullName)) {
                            $motherFullName = $mother['full_name'];
                        }
                    ?>
                        <p><strong>الأم:</strong> <?= h($motherFullName) ?></p>
                    <?php elseif (!empty($member['external_mother_name'])): ?>
                        <p><strong>الأم:</strong> <?= h($member['external_mother_name']) ?> 
                            <a href="#" onclick="document.getElementById('edit-mother-form').style.display='block'; return false;" style="color: #2563eb; text-decoration: none; font-size: 12px; margin-right: 5px;">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                        </p>
                    <?php else: ?>
                        <p><strong>الأم:</strong> 
                            <a href="#" onclick="document.getElementById('edit-mother-form').style.display='block'; return false;" style="color: #2563eb; text-decoration: underline;">
                                <i class="fas fa-plus"></i> إضافة اسم الأم
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($member['birth_date'])): ?>
                        <p><strong>تاريخ الميلاد:</strong> <?= h($member['birth_date']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($member['birth_place'])): ?>
                        <p><strong>مكان الميلاد:</strong> <?= h($member['birth_place']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($member['residence_location'])): ?>
                        <p><strong>مكان الإقامة:</strong> <?= h($member['residence_location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($member['phone_number'])): ?>
                        <p><strong>رقم الهاتف:</strong> <?= h($member['phone_number']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($member['tribe'])): ?>
                        <p><strong>القبيلة:</strong> <?= h($member['tribe']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($spouse): ?>
        <?php
        // بناء الاسم الكامل للزوج/الزوجة
        $spouseFullName = buildFullArabicName($pdo, $spouse);
        if (empty($spouseFullName)) {
            $spouseFullName = $spouse['full_name'];
        }
        ?>
    <div class="glass-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-[#3c2f2f]">الزوج/الزوجة</h2>
            <div class="flex gap-2">
                <a href="member_profile.php?id=<?= (int)$spouse['id'] ?>" class="btn btn-primary" style="padding: 8px 15px; font-size: 12px;">
                    <i class="fas fa-eye"></i> عرض الملف
                </a>
                <a href="edit_member.php?id=<?= (int)$spouse['id'] ?>" class="btn btn-secondary" style="padding: 8px 15px; font-size: 12px; background: #f2c200; color: #3c2f2f;">
                    <i class="fas fa-edit"></i> تعديل
                </a>
            </div>
        </div>
        <a href="member_profile.php?id=<?= (int)$spouse['id'] ?>" style="text-decoration: none; color: inherit;">
            <p><strong>الاسم:</strong> <?= h($spouseFullName) ?></p>
        </a>
        <?php if (!empty($spouse['residence_location'])): ?>
            <p><strong>مكان الإقامة:</strong> <?= h($spouse['residence_location']) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="glass-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-[#3c2f2f]">الأطفال</h2>
            <a href="add_family_member.php" class="btn btn-primary" style="padding: 8px 15px; font-size: 12px;">
                <i class="fas fa-plus"></i> إضافة طفل
            </a>
        </div>
        <?php if (empty($children)): ?>
            <p class="text-gray-600">لا يوجد أطفال مسجلين</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($children as $child): 
                    // بناء الاسم الكامل للابن
                    $childFullName = buildFullArabicName($pdo, $child);
                    if (empty($childFullName)) {
                        $childFullName = $child['full_name'];
                    }
                ?>
                    <div class="bg-white bg-opacity-50 p-3 rounded-lg" style="border: 1px solid rgba(196, 167, 125, 0.3);">
                        <div class="flex items-center justify-between mb-2">
                            <a href="member_profile.php?id=<?= (int)$child['id'] ?>" class="font-semibold" style="color: #2563eb; text-decoration: none; font-size: 16px;">
                                <?= h($childFullName) ?>
                            </a>
                            <div class="flex gap-2">
                                <a href="member_profile.php?id=<?= (int)$child['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 11px;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_member.php?id=<?= (int)$child['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px; background: #f2c200; color: #3c2f2f;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="person_delete.php?id=<?= (int)$child['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 11px; background: #dc2626; color: white;" onclick="return confirm('هل أنت متأكد من حذف <?= htmlspecialchars($child['full_name'], ENT_QUOTES) ?>؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php if (!empty($child['birth_date'])): ?>
                            <p class="text-sm text-gray-600">تاريخ الميلاد: <?= h($child['birth_date']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($child['residence_location'])): ?>
                            <p class="text-sm text-gray-600">مكان الإقامة: <?= h($child['residence_location']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="glass-card">
        <h2 class="text-xl font-bold mb-4 text-[#3c2f2f]">الإجراءات</h2>
        <a href="add_family_member.php" class="btn btn-primary">إضافة فرد من العائلة</a>
        <a href="add_note.php" class="btn btn-primary">إضافة ملاحظة</a>
        <a href="../../view_public.php" class="btn btn-secondary">عرض شجرة العائلة</a>
    </div>
</div>

<!-- نموذج تعديل اسم الأب الخارجي -->
<div id="edit-father-form" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;" onclick="if(event.target.id === 'edit-father-form') { this.style.display='none'; }">
    <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; position: relative;" onclick="event.stopPropagation();">
        <button onclick="document.getElementById('edit-father-form').style.display='none';" style="position: absolute; top: 10px; left: 10px; background: #dc2626; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px;">×</button>
        <h3 style="margin-bottom: 20px; color: #3c2f2f;">إضافة/تعديل اسم الأب</h3>
        <form method="POST">
            <input type="hidden" name="update_parent_name" value="1">
            <input type="hidden" name="parent_type" value="father">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">اسم الأب:</label>
                <input type="text" name="parent_name" value="<?= h($member['external_father_name'] ?? '') ?>" 
                       placeholder="أدخل اسم الأب الكامل" 
                       style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px;" required>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #3c2f2f; color: #f2c200; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    حفظ
                </button>
                <button type="button" onclick="document.getElementById('edit-father-form').style.display='none';" 
                        style="flex: 1; padding: 12px; background: #ccc; color: #333; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- نموذج تعديل اسم الأم الخارجية -->
<div id="edit-mother-form" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;" onclick="if(event.target.id === 'edit-mother-form') { this.style.display='none'; }">
    <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; position: relative;" onclick="event.stopPropagation();">
        <button onclick="document.getElementById('edit-mother-form').style.display='none';" style="position: absolute; top: 10px; left: 10px; background: #dc2626; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px;">×</button>
        <h3 style="margin-bottom: 20px; color: #3c2f2f;">إضافة/تعديل اسم الأم</h3>
        <form method="POST">
            <input type="hidden" name="update_parent_name" value="1">
            <input type="hidden" name="parent_type" value="mother">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">اسم الأم:</label>
                <input type="text" name="parent_name" value="<?= h($member['external_mother_name'] ?? '') ?>" 
                       placeholder="أدخل اسم الأم الكامل" 
                       style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px;" required>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #3c2f2f; color: #f2c200; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    حفظ
                </button>
                <button type="button" onclick="document.getElementById('edit-mother-form').style.display='none';" 
                        style="flex: 1; padding: 12px; background: #ccc; color: #333; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($error_message)): ?>
    <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; z-index: 1001; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
        <?= h($error_message) ?>
    </div>
    <script>
        setTimeout(function() {
            var msg = document.querySelector('div[style*="f8d7da"]');
            if (msg) msg.style.display = 'none';
        }, 5000);
    </script>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; z-index: 1001; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
        <?= h($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <script>
        setTimeout(function() {
            var msg = document.querySelector('div[style*="d4edda"]');
            if (msg) msg.style.display = 'none';
        }, 3000);
    </script>
<?php endif; ?>

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