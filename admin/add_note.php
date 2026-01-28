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
    $personId = (int)($_POST['person_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    
    if (empty($personId)) {
        $error = "الرجاء اختيار الشخص";
    } elseif (empty($note)) {
        $error = "الرجاء إدخال الملاحظة";
    } else {
        // التحقق من أن الشخص موجود
        $personStmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? LIMIT 1");
        $personStmt->execute([$personId]);
        $person = $personStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$person) {
            $error = "الشخص غير موجود";
        } else {
            // إضافة الملاحظة (تحديث أو إضافة)
            $currentNotes = $person['notes'] ?? '';
            $newNote = date('Y-m-d H:i') . ' - ' . $member['full_name'] . ': ' . $note;
            
            if (!empty($currentNotes)) {
                $updatedNotes = $currentNotes . "\n" . $newNote;
            } else {
                $updatedNotes = $newNote;
            }
            
            $updateStmt = $pdo->prepare("UPDATE persons SET notes = ? WHERE id = ?");
            $updateStmt->execute([$updatedNotes, $personId]);
            
            $success = true;
        }
    }
}

// جلب جميع أفراد العائلة للاختيار
$familyStmt = $pdo->query("SELECT id, full_name, membership_number FROM persons ORDER BY generation_level, full_name");
$familyMembers = $familyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة ملاحظة</title>
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
        <h1 class="text-2xl md:text-3xl font-bold text-[#3c2f2f]">إضافة ملاحظة</h1>
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
                تم إضافة الملاحظة بنجاح!
            </div>
            <a href="dashboard.php" class="block text-center bg-blue-600 text-white px-4 py-2 rounded">
                العودة للوحة التحكم
            </a>
        <?php else: ?>
            <form method="POST">
                <label class="block mb-2 font-semibold text-[#6b543f]">اختر الشخص:</label>
                <select name="person_id" class="w-full p-3 border rounded-lg mb-4" required>
                    <option value="">اختر الشخص</option>
                    <?php foreach ($familyMembers as $fm): ?>
                        <option value="<?= (int)$fm['id'] ?>">
                            <?= htmlspecialchars($fm['full_name']) ?>
                            <?php if (!empty($fm['membership_number'])): ?>
                                (<?= htmlspecialchars($fm['membership_number']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="block mb-2 font-semibold text-[#6b543f]">الملاحظة:</label>
                <textarea name="note" class="w-full p-3 border rounded-lg mb-4" rows="5" 
                          placeholder="اكتب ملاحظتك هنا..." required></textarea>

                <button type="submit" class="w-full bg-gradient-to-r from-[#3c2f2f] to-[#2a2222] text-[#f2c200] 
                                           px-4 py-3 rounded-lg font-semibold hover:shadow-lg transition">
                    حفظ الملاحظة
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

