<?php
// تحديد المسار النسبي بناءً على موقع الصفحة الحالية
$currentPath = $_SERVER['PHP_SELF'];
$basePath = '';

// إذا كانت الصفحة في admin/auth/
if (strpos($currentPath, '/admin/auth/') !== false) {
    $basePath = '../../';
} 
// إذا كانت الصفحة في admin/
elseif (strpos($currentPath, '/admin/') !== false) {
    $basePath = '../';
}
// إذا كانت الصفحة في الجذر
else {
    $basePath = '';
}
?>
<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <div class="footer-title">
                    <i class="fas fa-tree"></i>
                    شجرة العائلة
                </div>
                <p class="footer-desc">نظام متكامل لإدارة وتوثيق شجرة العائلة الكريمة</p>
            </div>
            
            <div class="footer-section">
                <div class="footer-title">روابط سريعة</div>
                <div class="footer-links">
                    <a href="<?= $basePath ?>index.php">
                        <i class="fas fa-home"></i>
                        الصفحة الرئيسية
                    </a>
                    <a href="<?= $basePath ?>view_public.php/view_public.php">
                        <i class="fas fa-tree"></i>
                        عرض الشجرة
                    </a>
                    <a href="<?= $basePath ?>admin/auth/login_username.php">
                        <i class="fas fa-lock"></i>
                        تسجيل دخول الأدمن
                    </a>
                </div>
            </div>
            
            <div class="footer-section">
                <div class="footer-title">معلومات</div>
                <p class="footer-copyright">© 2026 جميع الحقوق محفوظة</p>
                <p class="footer-name">شجرة العائلة الكريمة</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>صمم بـ <i class="fas fa-heart"></i> لشجرة العائلة</p>
        </div>
    </div>
</footer>

<style>
.main-footer {
    margin-top: auto;
    padding: 40px 20px 20px;
    background: rgba(60, 47, 47, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-top: 2px solid rgba(242, 194, 0, 0.3);
    position: relative;
    z-index: 1;
    width: 100%;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

.footer-section {
    text-align: center;
}

.footer-title {
    color: #f2c200;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.footer-title i {
    font-size: 20px;
}

.footer-desc {
    color: rgba(255, 255, 255, 0.85);
    font-size: 14px;
    line-height: 1.7;
    font-weight: 300;
}

.footer-links {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px;
    border-radius: 8px;
}

.footer-links a:hover {
    color: #f2c200;
    background: rgba(242, 194, 0, 0.1);
    transform: translateX(-3px);
}

.footer-links a i {
    font-size: 16px;
}

.footer-copyright {
    color: rgba(255, 255, 255, 0.75);
    font-size: 13px;
    margin-bottom: 8px;
}

.footer-name {
    color: rgba(255, 255, 255, 0.85);
    font-size: 14px;
    font-weight: 500;
}

.footer-bottom {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(242, 194, 0, 0.2);
    margin-top: 20px;
}

.footer-bottom p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 13px;
}

.footer-bottom i {
    color: #f2c200;
    margin: 0 5px;
}

@media (max-width: 768px) {
    .main-footer {
        padding: 30px 15px 15px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .footer-title {
        font-size: 16px;
    }
    
    .footer-desc,
    .footer-links a {
        font-size: 13px;
    }
}
</style>