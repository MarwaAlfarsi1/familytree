<?php
// إظهار الأخطاء لو في مشكلة
ini_set('display_errors', 1);
error_reporting(E_ALL);

// غيّري الكلمة هنا إذا تريدين كلمة مرور أخرى
$plain = "Admin1234";

$hash = password_hash($plain, PASSWORD_BCRYPT);

echo "<h2>Plain Password:</h2>";
echo "<pre>$plain</pre>";

echo "<h2>Generated Hash:</h2>";
echo "<textarea style='width:100%;height:120px;'>$hash</textarea>";
