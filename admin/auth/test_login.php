<?php
// ملف اختبار تسجيل الدخول
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';

echo "<h2>اختبار تسجيل الدخول</h2>";

// جلب جميع حسابات الإدمن
try {
    $stmt = $pdo->query("SELECT id, email, username, password FROM admins");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>الحسابات الموجودة:</h3>";
    if (empty($admins)) {
        echo "<p style='color:red;'>لا توجد حسابات إدمن!</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>البريد</th><th>اسم المستخدم</th><th>كلمة المرور (مشفرة)</th></tr>";
        foreach ($admins as $admin) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($admin['id']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['username'] ?? 'لا يوجد') . "</td>";
            echo "<td>" . substr(htmlspecialchars($admin['password']), 0, 30) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // اختبار تسجيل الدخول
    echo "<h3>اختبار تسجيل الدخول:</h3>";
    $testPassword = 'admin123';
    $testEmail = 'admin@familytree.com';
    
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$testEmail]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<p>تم العثور على الحساب: " . htmlspecialchars($admin['email']) . "</p>";
        
        if (password_verify($testPassword, $admin['password'])) {
            echo "<p style='color:green;'>✓ كلمة المرور صحيحة!</p>";
        } else {
            echo "<p style='color:red;'>✗ كلمة المرور غير صحيحة!</p>";
            echo "<p>كلمة المرور المشفرة في قاعدة البيانات: " . substr($admin['password'], 0, 30) . "...</p>";
            echo "<p>كلمة المرور الجديدة المشفرة: " . substr(password_hash($testPassword, PASSWORD_BCRYPT), 0, 30) . "...</p>";
            
            // تحديث كلمة المرور
            echo "<h4>تحديث كلمة المرور...</h4>";
            $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
            $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $updateStmt->execute([$newHash, $testEmail]);
            echo "<p style='color:green;'>✓ تم تحديث كلمة المرور!</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ الحساب غير موجود!</p>";
        echo "<p>جارٍ إنشاء الحساب...</p>";
        
        $passwordHash = password_hash($testPassword, PASSWORD_BCRYPT);
        $insertStmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
        $insertStmt->execute([$testEmail, $passwordHash]);
        echo "<p style='color:green;'>✓ تم إنشاء الحساب!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
}
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

