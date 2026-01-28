<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// دعم POST/GET (احتياط)
$personId = (int)($_POST['person_id'] ?? $_GET['person_id'] ?? 0);
$treeId   = (int)($_POST['tree_id'] ?? $_GET['tree_id'] ?? 0);

$spouseName = trim((string)($_POST['spouse_full_name'] ?? $_POST['spouse_name'] ?? $_GET['spouse_name'] ?? ''));
$spouseGender = trim((string)($_POST['spouse_gender'] ?? $_GET['spouse_gender'] ?? ''));
$spouseBirth  = trim((string)($_POST['spouse_birth_date'] ?? $_GET['spouse_birth_date'] ?? ''));

if ($personId <= 0 || $treeId <= 0 || $spouseName === '' || ($spouseGender !== 'male' && $spouseGender !== 'female')) {
    die("بيانات غير مكتملة.");
}

// جلب الشخص الداخلي
$stmt = $pdo->prepare("SELECT id, tree_id, full_name, spouse_person_id, spouse_is_external, external_tree_id, generation_level
                       FROM persons WHERE id=? LIMIT 1");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$person) die("الشخص غير موجود.");

if ((int)$person['tree_id'] !== $treeId) {
    // حماية: tree_id القادم من الفورم لازم يطابق فعليًا
    die("tree_id غير مطابق لهذا الشخص.");
}

// منع تكرار الزواج
if (!empty($person['spouse_person_id'])) {
    $_SESSION['flash'] = "هذا الشخص لديه زوج/زوجة بالفعل.";
    header("Location: view_tree_classic.php?highlight_id=".(int)$personId);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1) إنشاء شجرة خارجية
    $title = "عائلة خارجية لـ " . $person['full_name'];
    $stmt = $pdo->prepare("INSERT INTO trees (root_person_id, tree_type, title, created_at)
                           VALUES (NULL, 'external', ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$title]);
    $externalTreeId = (int)$pdo->lastInsertId();

    // 2) معالجة رفع صورة الزوج/الزوجة
    $photoPath = null;
    if (!empty($_FILES['spouse_photo']['name']) && $_FILES['spouse_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/persons/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['spouse_photo']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedExts)) {
            $fileName = uniqid('person_', true) . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['spouse_photo']['tmp_name'], $targetPath)) {
                $photoPath = 'admin/uploads/persons/' . $fileName;
            }
        }
    }

    // 3) إنشاء الزوج/الزوجة كـ Root داخل persons لكن في tree_id = externalTreeId
    $birthDate = ($spouseBirth !== '') ? $spouseBirth : null;
    
    // استخدام نفس جيل الشخص المرتبط به (الزوج/الزوجة يجب أن يكون في نفس الجيل)
    $spouseGenerationLevel = (int)($person['generation_level'] ?? 1);

    $stmt = $pdo->prepare("INSERT INTO persons
        (tree_id, full_name, gender, birth_date, father_id, mother_id, generation_level, is_root,
         spouse_person_id, spouse_is_external, external_tree_id, photo_path, created_at)
        VALUES
        (?, ?, ?, ?, NULL, NULL, ?, 1, ?, 1, NULL, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([
        $externalTreeId,
        $spouseName,
        $spouseGender,
        $birthDate,
        $spouseGenerationLevel,
        $personId,
        $photoPath
    ]);
    $externalSpouseId = (int)$pdo->lastInsertId();

    // 4) تحديث trees.root_person_id
    $stmt = $pdo->prepare("UPDATE trees SET root_person_id=? WHERE id=? LIMIT 1");
    $stmt->execute([$externalSpouseId, $externalTreeId]);

    // 5) ربط الشخص الداخلي بالزوج/الزوجة الخارجي
    $stmt = $pdo->prepare("UPDATE persons
                           SET spouse_person_id=?, spouse_is_external=1, external_tree_id=?
                           WHERE id=? LIMIT 1");
    $stmt->execute([$externalSpouseId, $externalTreeId, $personId]);

    $pdo->commit();

    $_SESSION['flash'] = "تم حفظ الزواج الخارجي بنجاح.";
    
    // تحديد نوع المعامل بناءً على جنس الشخص
    $personStmt = $pdo->prepare("SELECT gender FROM persons WHERE id=? LIMIT 1");
    $personStmt->execute([$personId]);
    $personData = $personStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($personData && $personData['gender'] === 'male') {
        header("Location: external_family.php?tree_id=".(int)$externalTreeId."&husband_id=".(int)$personId);
    } else {
        header("Location: external_family.php?tree_id=".(int)$externalTreeId."&wife_id=".(int)$personId);
    }
    exit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("حدث خطأ أثناء الحفظ: " . $e->getMessage());
}
