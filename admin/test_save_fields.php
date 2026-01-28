<?php
// ملف اختبار حفظ الحقول
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("يجب تسجيل الدخول أولاً");
}

require_once __DIR__ . '/../config/db.php';

echo "<h2>اختبار حفظ الحقول</h2>";

// جلب آخر شخص تم إضافته
$stmt = $pdo->query("SELECT * FROM persons ORDER BY id DESC LIMIT 1");
$lastPerson = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lastPerson) {
    echo "<h3>آخر شخص تم إضافته:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr><th>الحقل</th><th>القيمة</th></tr>";
    
    $fields = [
        'full_name' => 'الاسم',
        'birth_date' => 'تاريخ الميلاد',
        'death_date' => 'تاريخ الوفاة',
        'residence_location' => 'مكان الإقامة',
        'notes' => 'ملاحظات',
        'photo_path' => 'مسار الصورة',
        'membership_number' => 'رقم العضوية'
    ];
    
    foreach ($fields as $field => $label) {
        $value = $lastPerson[$field] ?? 'NULL';
        if ($value === null || $value === '') {
            $value = '<span style="color:red;">فارغ</span>';
        }
        echo "<tr><td><b>$label</b></td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    
    echo "</table>";
    
    // التحقق من الصورة
    if (!empty($lastPerson['photo_path'])) {
        $photoPath = __DIR__ . '/../' . $lastPerson['photo_path'];
        if (file_exists($photoPath)) {
            echo "<p style='color:green;'>✓ الصورة موجودة في: " . htmlspecialchars($photoPath) . "</p>";
        } else {
            echo "<p style='color:red;'>✗ الصورة غير موجودة في: " . htmlspecialchars($photoPath) . "</p>";
        }
    }
} else {
    echo "<p>لا يوجد أشخاص في قاعدة البيانات</p>";
}

echo "<hr>";
echo "<p><a href='manage_people_new.php'>رجوع</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    direction: rtl;
}
table {
    margin: 20px 0;
}
th {
    background: #f0f0f0;
    padding: 10px;
}
td {
    padding: 10px;
}
</style>

