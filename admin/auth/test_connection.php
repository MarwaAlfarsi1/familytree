<?php
// ملف اختبار الاتصال بقاعدة البيانات
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>اختبار الاتصال بقاعدة البيانات</h2>";

try {
    require_once '../../config/db.php';
    echo "<p style='color:green;'>✓ الاتصال بقاعدة البيانات نجح!</p>";
    
    // اختبار جدول admins
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>عدد المشرفين: " . $result['count'] . "</p>";
    
    // اختبار جدول persons
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM persons");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>عدد الأفراد: " . $result['count'] . "</p>";
    
    echo "<p style='color:green;'>✓ جميع الاختبارات نجحت!</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>تحققي من ملف config/db.php</p>";
}
?>

