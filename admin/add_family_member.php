<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: auth/login.php");
    exit();
}

$memberId = (int)$_SESSION['member_id'];
$stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    session_destroy();
    header("Location: auth/login.php");
    exit();
}

$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $birthDate = trim($_POST['birth_date'] ?? '') ?: null;
    $residence = trim($_POST['residence_location'] ?? '') ?: null;
    $relation = $_POST['relation'] ?? ''; // child, spouse, sibling
    
    if (empty($name)) {
        $error = "الاسم مطلوب";
    } elseif (empty($relation)) {
        $error = "الرجاء اختيار صلة القرابة";
    } else {
        // توليد رقم العضوية
        $membershipNumber = generateMembershipNumber($pdo);
        
        // تحديد الأب والأم حسب صلة القرابة
        $fatherId = null;
        $motherId = null;
        $gen = 0;
        
        if ($relation === 'child') {
            // إضافة طفل
            if ($member['gender'] === 'male') {
                $fatherId = $memberId;
                if (!empty($member['spouse_person_id'])) {
                    $motherId = (int)$member['spouse_person_id'];
                }
            } else {
                // إذا كانت أنثى، نحتاج زوجها
                if (!empty($member['spouse_person_id'])) {
                    $fatherId = (int)$member['spouse_person_id'];
                    $motherId = $memberId;
                } else {
                    $error = "يجب أن يكون لديك زوج لإضافة طفل";
                }
            }
            $gen = (int)$member['generation_level'] + 1;
        } elseif ($relation === 'spouse') {
            // إضافة زوج/زوجة (للعضو فقط)
            if ($member['gender'] === 'male' && $gender === 'female') {
                // رجل يضيف زوجة
                $fatherId = $memberId;
                $gen = (int)$member['generation_level'];
            } elseif ($member['gender'] === 'female' && $gender === 'male') {
                // امرأة تضيف زوج
                $fatherId = null; // سيتم ربطه لاحقاً
                $gen = (int)$member['generation_level'];
            } else {
                $error = "الجنس غير متطابق";
            }
        } else {
            $error = "صلة القرابة غير صحيحة";
        }
        
        if (empty($error)) {
            // معالجة رفع الصورة
            $photoPath = null;
            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../admin/uploads/persons/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExt, $allowedExts)) {
                    $fileName = uniqid('person_', true) . '.' . $fileExt;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                        $photoPath = 'admin/uploads/persons/' . $fileName;
                    }
                }
            }
            
            $ins = $pdo->prepare("INSERT INTO persons(tree_id, membership_number, full_name, gender, birth_date, residence_location, 
                                                      father_id, mother_id, generation_level, is_root, photo_path)
                                  VALUES(?,?,?,?,?,?,?,?,?,0,?)");
            $ins->execute([
                (int)$member['tree_id'], $membershipNumber, $name, $gender, $birthDate, $residence,
                $fatherId, $motherId, $gen, $photoPath
            ]);
            
            $newPersonId = $pdo->lastInsertId();
            
            // إذا كان زوج/زوجة، ربطه
            if ($relation === 'spouse' && $member['gender'] === 'female' && $gender === 'male') {
                $updateStmt = $pdo->prepare("UPDATE persons SET spouse_person_id = ? WHERE id = ?");
                $updateStmt->execute([$newPersonId, $memberId]);
            } elseif ($relation === 'spouse' && $member['gender'] === 'male' && $gender === 'female') {
                $updateStmt = $pdo->prepare("UPDATE persons SET spouse_person_id = ? WHERE id = ?");
                $updateStmt->execute([$memberId, $newPersonId]);
            }
            
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة فرد من العائلة</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* {
    font-family: 'Cairo', sans-serif;
}

.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-bottom: 20px;
}
</style>
</head>
<body class="bg-gradient-to-br from-[#f5efe3] to-[#e8ddd0] min-h-screen p-4">

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#3c2f2f]">إضافة فرد من العائلة</h1>
        <a href="dashboard.php" class="bg-gray-700 text-white px-4 py-2 rounded">رجوع</a>
    </div>

    <div class="glass-card">
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                تم إضافة الفرد بنجاح!
            </div>
            <a href="dashboard.php" class="block text-center bg-blue-600 text-white px-4 py-2 rounded">
                العودة للوحة التحكم
            </a>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <label class="block mb-2 font-semibold text-[#6b543f]">صلة القرابة <span style="color:red;">*</span>:</label>
                <select name="relation" class="w-full p-3 border rounded-lg mb-4" required>
                    <option value="">اختر صلة القرابة</option>
                    <option value="child">ابن/ابنة</option>
                    <option value="spouse">زوج/زوجة</option>
                </select>

                <label class="block mb-2 font-semibold text-[#6b543f]">الاسم الكامل <span style="color:red;">*</span>:</label>
                <input name="full_name" class="w-full p-3 border rounded-lg mb-4" placeholder="الاسم الكامل" required>

                <label class="block mb-2 font-semibold text-[#6b543f]">الجنس <span style="color:red;">*</span>:</label>
                <select name="gender" class="w-full p-3 border rounded-lg mb-4" required>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>

                <label class="block mb-2 font-semibold text-[#6b543f]">تاريخ الميلاد:</label>
                <input type="date" name="birth_date" class="w-full p-3 border rounded-lg mb-4">

                <label class="block mb-2 font-semibold text-[#6b543f]">مكان الإقامة:</label>
                <input name="residence_location" class="w-full p-3 border rounded-lg mb-4" placeholder="مكان الإقامة">

                <label class="block mb-2 font-semibold text-[#6b543f]">الصورة (اختياري):</label>
                <input type="file" name="photo" accept="image/*" class="w-full p-3 border rounded-lg mb-4">

                <button type="submit" class="w-full bg-gradient-to-r from-[#3c2f2f] to-[#2a2222] text-[#f2c200] 
                                           px-4 py-3 rounded-lg font-semibold hover:shadow-lg transition">
                    حفظ
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

