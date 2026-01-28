<?php
require_once '../config/db.php';

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head><meta charset='UTF-8'><title>إضافة الحقول</title>";
echo "<style>body{font-family:'Cairo',sans-serif;padding:2rem;background:#f5f5f5;}";
echo ".container{max-width:800px;margin:0 auto;background:white;padding:2rem;border-radius:8px;}";
echo ".success{color:green;padding:1rem;background:#d4edda;border-radius:8px;margin:1rem 0;}";
echo ".error{color:red;padding:1rem;background:#f8d7da;border-radius:8px;margin:1rem 0;}";
echo "</style></head><body><div class='container'>";
echo "<h1>إضافة حقول رقم الهاتف ومكان الميلاد</h1>";

try {
    // إضافة حقل رقم الهاتف
    try {
        $pdo->exec("ALTER TABLE `persons` 
                    ADD COLUMN `phone_number` VARCHAR(50) NULL DEFAULT NULL 
                    AFTER `residence_location`");
        echo "<div class='success'>✓ تم إضافة حقل رقم الهاتف (phone_number)</div>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<div class='success'>ℹ حقل رقم الهاتف موجود مسبقاً</div>";
        } else {
            echo "<div class='error'>✗ خطأ في إضافة حقل رقم الهاتف: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // إضافة حقل مكان الميلاد
    try {
        $pdo->exec("ALTER TABLE `persons` 
                    ADD COLUMN `birth_place` VARCHAR(255) NULL DEFAULT NULL 
                    AFTER `birth_date`");
        echo "<div class='success'>✓ تم إضافة حقل مكان الميلاد (birth_place)</div>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<div class='success'>ℹ حقل مكان الميلاد موجود مسبقاً</div>";
        } else {
            echo "<div class='error'>✗ خطأ في إضافة حقل مكان الميلاد: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ خطأ عام: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr><a href='dashboard_new.php' style='display:inline-block;padding:0.75rem 1.5rem;background:#8B4513;color:white;text-decoration:none;border-radius:5px;'>العودة للوحة التحكم</a>";
echo "</div></body></html>";
?>