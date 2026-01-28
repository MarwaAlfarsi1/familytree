<?php
// ملف اختبار صفحة إضافة زوج ثاني
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['admin_id'])) {
    die("يجب تسجيل الدخول أولاً");
}

require_once __DIR__ . '/../config/db.php';

echo "<h2>اختبار صفحة إضافة زوج ثاني</h2>";

// التحقق من وجود الأعمدة
$columns = ['second_spouse_person_id', 'second_spouse_is_external', 'second_external_tree_id'];

foreach ($columns as $column) {
    $stmt = $pdo->query("SHOW COLUMNS FROM persons LIKE '$column'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✓ العمود '$column' موجود</p>";
    } else {
        echo "<p style='color:red;'>✗ العمود '$column' غير موجود - يجب تشغيل database_upgrade_safe.sql</p>";
    }
}

// التحقق من جدول trees
$stmt = $pdo->query("SHOW TABLES LIKE 'trees'");
if ($stmt->rowCount() > 0) {
    echo "<p style='color:green;'>✓ جدول trees موجود</p>";
} else {
    echo "<p style='color:red;'>✗ جدول trees غير موجود</p>";
}

// اختبار الاتصال
try {
    $test = $pdo->query("SELECT 1");
    echo "<p style='color:green;'>✓ الاتصال بقاعدة البيانات يعمل</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ خطأ في الاتصال: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='person_add_second_spouse.php?person_id=3'>جرب صفحة إضافة زوج ثاني</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    direction: rtl;
}
</style>

