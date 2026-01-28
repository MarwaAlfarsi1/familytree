<?php
// الصفحة الرئيسية - شجرة العائلة الكريمة
// ضع شعار العائلة في: assets/logo.png

$logoPath = '';
$logoExists = false;

// البحث المباشر في assets/logo.png
$logoFile = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png';

if (file_exists($logoFile) && is_file($logoFile)) {
    $logoPath = 'assets/logo.png';
    $logoExists = true;
} else {
    // البحث في صيغ أخرى
    $extensions = ['jpg', 'jpeg', 'webp', 'svg'];
    foreach ($extensions as $ext) {
        $testFile = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.' . $ext;
        if (file_exists($testFile) && is_file($testFile)) {
            $logoPath = 'assets/logo.' . $ext;
            $logoExists = true;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شجرة العائلة الكريمة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #3c2f2f;
            --accent: #f2c200;
            --bg-gradient: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
            --line-color: #c4a77d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: var(--primary);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;
        }

        /* خلفية متحركة */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(242, 194, 0, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(196, 167, 125, 0.06) 0%, transparent 50%);
            animation: backgroundMove 25s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-5%, -5%) rotate(5deg); }
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: clamp(30px, 5vw, 60px) clamp(20px, 3vw, 30px);
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .hero-section {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: clamp(25px, 4vw, 40px);
            padding: clamp(30px, 5vw, 60px) clamp(25px, 4vw, 50px);
            box-shadow: 
                0 25px 80px rgba(60, 47, 47, 0.12),
                0 0 0 1px rgba(60, 47, 47, 0.15) inset;
            border: 2px solid rgba(60, 47, 47, 0.25);
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: clamp(30px, 4vw, 50px);
            align-items: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hero-section:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 35px 100px rgba(60, 47, 47, 0.18),
                0 0 0 1px rgba(242, 194, 0, 0.3) inset;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(242, 194, 0, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 4s ease-in-out infinite;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(196, 167, 125, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 5s ease-in-out infinite 1s;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.2); opacity: 0.3; }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            order: 1;
        }

        .hero-title {
            font-size: clamp(36px, 5vw, 56px);
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--primary);
            line-height: 1.2;
            background: linear-gradient(135deg, var(--primary) 0%, #5a4a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 0.8s ease-out;
        }

        .hero-subtitle {
            font-size: clamp(18px, 2.2vw, 22px);
            color: #6b543f;
            line-height: 1.9;
            margin-bottom: 40px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .action-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(60, 47, 47, 0.25);
            border-radius: 24px;
            padding: 28px 24px;
            text-decoration: none;
            color: var(--primary);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 
                0 10px 30px rgba(60, 47, 47, 0.08),
                0 0 0 0 rgba(242, 194, 0, 0) inset;
            display: flex;
            flex-direction: column;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), rgba(242, 194, 0, 0.5));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .action-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--accent);
            box-shadow: 
                0 20px 50px rgba(60, 47, 47, 0.15),
                0 0 0 3px rgba(242, 194, 0, 0.1) inset;
        }

        .action-card:hover::before {
            transform: scaleX(1);
        }

        .action-card i {
            font-size: 36px;
            background: linear-gradient(135deg, var(--accent) 0%, rgba(242, 194, 0, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            transition: transform 0.3s ease;
        }

        .action-card:hover i {
            transform: scale(1.15) rotate(5deg);
        }

        .action-card-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--primary);
        }

        .action-card-desc {
            font-size: 14px;
            color: #6b543f;
            line-height: 1.7;
        }

        .logo-container {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeInUp 0.8s ease-out 0.6s both;
            order: 2;
        }

        .logo-circle {
            width: clamp(180px, 25vw, 280px);
            height: clamp(180px, 25vw, 280px);
            border-radius: 50%;
            background: linear-gradient(135deg, 
                rgba(242, 194, 0, 0.2) 0%, 
                rgba(242, 194, 0, 0.1) 50%,
                rgba(196, 167, 125, 0.15) 100%);
            border: clamp(3px, 0.5vw, 5px) solid var(--accent);
            box-shadow: 
                0 25px 60px rgba(60, 47, 47, 0.25),
                0 0 0 clamp(5px, 0.8vw, 8px) rgba(255, 255, 255, 0.9),
                0 0 0 clamp(8px, 1.2vw, 12px) rgba(196, 167, 125, 0.3),
                inset 0 0 40px rgba(242, 194, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin: 0 auto;
        }

        .logo-circle:hover {
            transform: scale(1.05);
            box-shadow: 
                0 30px 80px rgba(60, 47, 47, 0.3),
                0 0 0 8px rgba(255, 255, 255, 0.95),
                0 0 0 12px rgba(242, 194, 0, 0.5),
                inset 0 0 50px rgba(242, 194, 0, 0.15);
        }

        .logo-circle::before {
            content: '';
            position: absolute;
            inset: -30px;
            background: conic-gradient(
                from 0deg,
                transparent 0deg,
                rgba(242, 194, 0, 0.2) 90deg,
                transparent 180deg,
                rgba(196, 167, 125, 0.15) 270deg,
                transparent 360deg
            );
            animation: rotate 12s linear infinite;
            border-radius: 50%;
        }

        .logo-circle::after {
            content: '';
            position: absolute;
            inset: 10px;
            border-radius: 50%;
            background: radial-gradient(circle, 
                rgba(255, 255, 255, 0.3) 0%, 
                transparent 70%);
            pointer-events: none;
        }

        @keyframes rotate {
            to { transform: rotate(360deg); }
        }

        .logo-img {
            width: 80%;
            height: 80%;
            object-fit: contain;
            position: relative;
            z-index: 3;
            filter: drop-shadow(0 8px 20px rgba(60, 47, 47, 0.4));
            transition: transform 0.3s ease;
        }

        .logo-circle:hover .logo-img {
            transform: scale(1.1);
        }

        .logo-fallback {
            position: relative;
            z-index: 3;
            font-size: 90px;
            background: linear-gradient(135deg, var(--primary) 0%, #5a4a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: transform 0.3s ease;
        }

        .logo-circle:hover .logo-fallback {
            transform: scale(1.1) rotate(5deg);
        }

        @media (max-width: 1024px) {
            .hero-section {
                grid-template-columns: 1fr;
                text-align: center;
                padding: clamp(35px, 6vw, 50px) clamp(25px, 4vw, 40px);
            }

            .logo-container {
                order: 1;
                margin-bottom: 30px;
            }

            .hero-content {
                order: 2;
            }

            .action-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .hero-title {
                font-size: clamp(28px, 6vw, 42px);
            }

            .hero-subtitle {
                font-size: clamp(16px, 3vw, 20px);
                margin-bottom: 30px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: clamp(25px, 4vw, 40px) clamp(15px, 3vw, 20px);
            }

            .hero-section {
                padding: clamp(25px, 5vw, 40px) clamp(20px, 4vw, 30px);
                border-radius: clamp(20px, 4vw, 30px);
            }

            .logo-container {
                margin-bottom: 25px;
            }

            .action-card {
                padding: clamp(20px, 4vw, 24px) clamp(18px, 3vw, 20px);
            }

            .action-card i {
                font-size: clamp(28px, 5vw, 36px);
            }

            .action-card-title {
                font-size: clamp(16px, 3vw, 18px);
            }

            .action-card-desc {
                font-size: clamp(13px, 2.5vw, 14px);
            }
        }

        @media (max-width: 480px) {
            .hero-section {
                padding: 25px 20px;
            }

            .logo-circle {
                width: 160px;
                height: 160px;
            }

            .action-cards {
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <section class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">عائلة مرهون بن سعيدالكريمة</h1>
                <p class="hero-subtitle">هنا تُوثّق الأنساب وتُحفظ الذكريات للأجيال القادمة<br>اكتشف تاريخ عائلتك واتصل بجذورك</p>

                <div class="action-cards">
                    <a href="view_public.php" class="action-card">
                        <i class="fas fa-network-wired"></i>
                        <div class="action-card-title">عرض الشجرة</div>
                        <div class="action-card-desc">استعرض شجرة العائلة الكاملة مع جميع الأفراد والعلاقات</div>
                    </a>

                    <a href="admin/auth/login_username.php" class="action-card">
                        <i class="fas fa-user-shield"></i>
                        <div class="action-card-title">تسجيل دخول الأدمن</div>
                        <div class="action-card-desc">للمسؤولين: إدارة الشجرة وإضافة الأفراد وتحديث المعلومات</div>
                    </a>

                    <a href="admin/auth/login.php" class="action-card">
                        <i class="fas fa-user"></i>
                        <div class="action-card-title">تسجيل دخول العضو</div>
                        <div class="action-card-desc">للأعضاء: الوصول إلى حسابك الشخصي ومعلوماتك في الشجرة</div>
                    </a>
                </div>
            </div>

            <div class="logo-container">
                <div class="logo-circle">
                    <?php if ($logoExists && !empty($logoPath)): 
                        // إضافة timestamp لتجنب مشاكل الـ cache
                        $logoUrl = $logoPath . '?v=' . time();
                    ?>
                        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" 
                             alt="شعار العائلة" 
                             class="logo-img"
                             onerror="console.error('❌ خطأ في تحميل الشعار من:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';"
                             onload="console.log('✅ تم تحميل الشعار بنجاح من:', this.src);">
                        <i class="fas fa-tree logo-fallback" style="display:none;"></i>
                    <?php else: ?>
                        <i class="fas fa-tree logo-fallback"></i>
                        <?php if (isset($_GET['debug'])): ?>
                            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px; max-width: 500px; text-align: right; direction: rtl;">
                                <strong>⚠️ لم يتم العثور على الشعار</strong><br>
                                <small>المسار المطلوب: <code>assets/logo.png</code></small><br>
                                <small>المسار الكامل: <code><?= htmlspecialchars(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png') ?></code></small><br>
                                <small>الملف موجود: <?= file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png') ? '✅ نعم' : '❌ لا' ?></small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>