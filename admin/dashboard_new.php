<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}
require_once __DIR__ . "/../config/db.php";

/** Fetch main tree */
$mainTree = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$mainTreeId = $mainTree ? (int)$mainTree['id'] : 0;

/** Check if main root exists */
$root = null;
if ($mainTreeId) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
    $stmt->execute([$mainTreeId]);
    $root = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لوحة التحكم - شجرة العائلة</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/common.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding-bottom: 0;
}

.main-content {
    flex: 1;
    padding: 20px;
}

.glass-card {
    max-width: 1000px;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    border: 1px solid rgba(255, 255, 255, 0.3);
    text-align: center;
}

h2 {
    margin: 0 0 30px;
    color: #3c2f2f;
    font-size: 32px;
    font-weight: 700;
}

.badge {
    display: inline-block;
    background: rgba(255, 243, 205, 0.9);
    color: #7a5b00;
    padding: 15px 25px;
    border-radius: 12px;
    font-weight: 600;
    margin: 20px 0;
    border: 1px solid rgba(122, 91, 0, 0.2);
    font-size: 16px;
}

.membership-badge {
    display: inline-block;
    background: rgba(255, 193, 7, 0.2);
    color: #856404;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    margin-right: 10px;
}

.membership-badge.no-number {
    background: rgba(220, 53, 69, 0.2);
    color: #721c24;
}

.small {
    color: #6b543f;
    font-size: 14px;
    margin-top: 15px;
    line-height: 1.6;
}

.grid-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
    margin-top: 30px;
    margin-bottom: 20px;
}

a.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
    padding: 16px 20px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
    font-size: 15px;
}

a.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
}

a.btn i {
    font-size: 18px;
}

a.btn-secondary {
    background: rgba(255, 255, 255, 0.9);
    color: #3c2f2f;
    border: 1px solid rgba(191, 169, 138, 0.5);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    max-width: 300px;
    margin: 20px auto 0;
}

a.btn-secondary:hover {
    background: #fff;
    border-color: #c4a77d;
}

/* Footer Styles */
.main-footer {
    background: linear-gradient(135deg, #3c2f2f 0%, #2a2222 100%);
    color: #f2c200;
    padding: 30px 20px;
    margin-top: 40px;
    border-top: 3px solid #f2c200;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 20px;
}

.footer-section {
    text-align: right;
}

.footer-title {
    color: #f2c200;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-section p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 10px;
}

.footer-section ul {
    list-style: none;
    padding: 0;
}

.footer-section ul li {
    margin-bottom: 10px;
}

.footer-section ul li a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-section ul li a:hover {
    color: #f2c200;
    transform: translateX(-5px);
}

.footer-section ul li a i {
    width: 20px;
    text-align: center;
}

.footer-bottom {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.8);
    font-size: 13px;
}

.footer-bottom i {
    color: #e74c3c;
    margin: 0 5px;
}

@media (max-width: 768px) {
    .glass-card {
        padding: 25px;
        margin: 20px;
    }
    
    h2 {
        font-size: 24px;
    }
    
    .grid-buttons {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .footer-section {
        text-align: center;
    }
}
</style>
</head>
<body>

<div class="main-content">
    <div class="glass-card">
        <h2><i class="fas fa-tachometer-alt"></i> لوحة التحكم</h2>

        <?php if(!$root): ?>
            <div class="badge">
                <i class="fas fa-exclamation-triangle"></i> لا يوجد جد مؤسس بعد — يجب إضافته أولاً
            </div>
            <a class="btn" href="add_root_new.php">
                <i class="fas fa-user-plus"></i> إضافة الجد (المؤسس)
            </a>
            <p class="small">بعد إضافة الجد سيظهر خيار إضافة الأبناء والأحفاد.</p>
        <?php else: ?>
            <div class="badge">
                تم إعداد الجد: <?= htmlspecialchars($root['full_name']) ?>
                <?php if (!empty($root['membership_number'])): ?>
                    <span class="membership-badge">رقم العضوية: <?= htmlspecialchars($root['membership_number']) ?></span>
                <?php else: ?>
                    <span class="membership-badge no-number">رقم العضوية: غير محدد</span>
                <?php endif; ?>
            </div>
            
            <div class="grid-buttons">
                <a class="btn" href="member_profiles_list.php">
                    <i class="fas fa-id-card"></i> ملفات الأعضاء
                </a>
                <a class="btn" href="manage_people_new.php">
                    <i class="fas fa-users"></i> إدارة أفراد العائلة
                </a>
                <a class="btn" href="view_tree_classic.php">
                    <i class="fas fa-tree"></i> عرض شجرة العائلة
                </a>
                <a class="btn" href="statistics.php">
                    <i class="fas fa-chart-bar"></i> الإحصائيات
                </a>
                <a class="btn" href="search.php">
                    <i class="fas fa-search"></i> البحث عن فرد
                </a>
                <a class="btn" href="manage_requests.php">
                    <i class="fas fa-envelope"></i> طلبات الحسابات
                </a>
                <a class="btn" href="assign_membership_numbers.php">
                    <i class="fas fa-id-badge"></i> توزيع أرقام العضوية
                </a>
                <a class="btn" href="print_tree_new.php" target="_blank">
                    <i class="fas fa-print"></i> طباعة الشجرة
                </a>
            </div>
        <?php endif; ?>

        <a class="btn btn-secondary" href="auth/logout.php">
            <i class="fas fa-sign-out-alt"></i> تسجيل خروج
        </a>
    </div>
</div>

<!-- Footer -->
<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <div class="footer-title">
                    <i class="fas fa-tree"></i>
                    شجرة العائلة الكريمة
                </div>
                <p>نظام متكامل لإدارة وتوثيق شجرة العائلة الكريمة</p>
            </div>
            <div class="footer-section">
                <div class="footer-title">روابط سريعة</div>
                <ul>
                    <li><a href="../index.php"><i class="fas fa-home"></i> الصفحة الرئيسية</a></li>
                    <li><a href="../view_public.php/view_public.php"><i class="fas fa-tree"></i> عرض الشجرة</a></li>
                    <li><a href="auth/login_username.php"><i class="fas fa-user-shield"></i> تسجيل دخول الأدمن</a></li>
                    <li><a href="auth/login.php"><i class="fas fa-users"></i> تسجيل دخول الأعضاء</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <div class="footer-title">معلومات</div>
                <p>&copy; 2026 جميع الحقوق محفوظة</p>
                <p>صمم بـ <i class="fas fa-heart"></i> لشجرة العائلة</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>جميع الحقوق محفوظة &copy; 2026</p>
        </div>
    </div>
</footer>

</body>
</html>