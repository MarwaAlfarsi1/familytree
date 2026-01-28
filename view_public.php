<?php
// إخفاء الأخطاء في الإنتاج
error_reporting(0);
ini_set('display_errors', 0);

// تحديد المسار الصحيح لـ db.php
$dbPath = __DIR__ . '/../db.php';
if (!file_exists($dbPath)) {
    $dbPath = dirname(__DIR__) . '/db.php';
}

// إذا لم يوجد db.php، أنشئ الاتصال مباشرة
if (!file_exists($dbPath)) {
    try {
        $host = "localhost";
        $dbname = "u480768868_family_tree";
        $username = "u480768868_Mmm111999";
        $password = "Mmmm@@999";
        
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );
    } catch (PDOException $e) {
        die("خطأ في الاتصال بقاعدة البيانات");
    }
} else {
    // تحميل ملف قاعدة البيانات
    require_once $dbPath;
    
    // التحقق من وجود $pdo بعد التحميل
    if (!isset($pdo) || !$pdo) {
        try {
            $host = "localhost";
            $dbname = "u480768868_family_tree";
            $username = "u480768868_Mmm111999";
            $password = "Mmmm@@999";
            
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch (PDOException $e) {
            die("خطأ في الاتصال بقاعدة البيانات");
        }
    }
}

/** جلب الشجرة الرئيسية */
try {
    $main = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$main){ 
        // محاولة جلب أي شخص كجذر
        $root = $pdo->query("SELECT * FROM persons WHERE is_root=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(!$root) {
            $root = $pdo->query("SELECT * FROM persons ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if(!$root) {
            die("لا توجد بيانات في النظام");
        }
        $treeId = 0;
    } else {
        $treeId = (int)$main['id'];
        
        /** جلب الجد (الجذر) */
        $root = null;
        if (!empty($main['root_person_id'])) {
            $st = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
            $st->execute([(int)$main['root_person_id']]);
            $root = $st->fetch(PDO::FETCH_ASSOC);
        }
        if(!$root){
            $st = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
            $st->execute([$treeId]);
            $root = $st->fetch(PDO::FETCH_ASSOC);
        }
        if(!$root){
            // محاولة جلب أي شخص كجذر
            $root = $pdo->query("SELECT * FROM persons WHERE is_root=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if(!$root) {
                $root = $pdo->query("SELECT * FROM persons ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()));
}

if(!$root){ die("لم يتم تحديد الجد المؤسس"); }

/** وظيفة جلب الأبناء */
function getChildren($pdo, $treeId, $fatherId, $motherId = null){
    // جلب الأطفال من الأب
    if ($treeId > 0) {
        $st = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND father_id=? ORDER BY id ASC");
        $st->execute([$treeId, $fatherId]);
    } else {
        $st = $pdo->prepare("SELECT * FROM persons WHERE father_id=? ORDER BY id ASC");
        $st->execute([$fatherId]);
    }
    $children = $st->fetchAll(PDO::FETCH_ASSOC);
    
    // إذا كانت هناك أم محددة (للزوج الثاني)، جلب أطفالها أيضاً
    if ($motherId) {
        if ($treeId > 0) {
            $st2 = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND mother_id=? AND father_id != ? ORDER BY id ASC");
            $st2->execute([$treeId, $motherId, $fatherId]);
        } else {
            $st2 = $pdo->prepare("SELECT * FROM persons WHERE mother_id=? AND father_id != ? ORDER BY id ASC");
            $st2->execute([$motherId, $fatherId]);
        }
        $motherChildren = $st2->fetchAll(PDO::FETCH_ASSOC);
        
        // دمج النتائج
        $children = array_merge($children, $motherChildren);
    }
    
    return $children;
}

/** وظيفة جلب الزوج/الزوجة */
function getSpouse($pdo, $person) {
    if (!empty($person['spouse_person_id']) && empty($person['spouse_is_external'])) {
        $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id=? LIMIT 1");
        $stmt->execute([$person['spouse_person_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($person['spouse_is_external']) && !empty($person['external_tree_id'])) {
        $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
        $stmt->execute([$person['external_tree_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

/** وظيفة جلب الزوج الثاني */
function getSecondSpouse($pdo, $person) {
    if (!empty($person['second_spouse_person_id']) && empty($person['second_spouse_is_external'])) {
        $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id=? LIMIT 1");
        $stmt->execute([$person['second_spouse_person_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($person['second_spouse_is_external']) && !empty($person['second_external_tree_id'])) {
        $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
        $stmt->execute([$person['second_external_tree_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

/** البناء التكراري للشجرة الهرمية الكلاسيكية */
function renderTree($pdo, $treeId, $person) {
    // جلب الأطفال - إذا كانت أنثى ولها زوج ثاني، نجلب أطفالها من كلا الزوجين
    $motherId = ($person['gender'] === 'female' && !empty($person['second_spouse_person_id'])) ? (int)$person['id'] : null;
    $children = getChildren($pdo, $treeId, $person['id'], $motherId);
    $spouse = getSpouse($pdo, $person);
    $secondSpouse = getSecondSpouse($pdo, $person);
    $hasChildren = !empty($children);
    $spouseLabel = ($person['gender'] === 'male') ? 'زوجة: ' : 'زوج: ';
    
    echo '<li>';
    echo '<div class="node-box">';
    echo '<div class="card">';
    echo '<div class="name">' . htmlspecialchars($person['full_name']) . '</div>';
    if ($spouse) {
        echo '<div class="spouse">' . $spouseLabel . '<span>' . htmlspecialchars($spouse['full_name']) . '</span></div>';
    }
    if ($secondSpouse) {
        echo '<div class="spouse" style="color: #9b59b6; border-top: 1px solid #ddd; margin-top: 8px; padding-top: 8px;">زوج ثاني: <span>' . htmlspecialchars($secondSpouse['full_name']) . '</span></div>';
    }
    echo '</div>';
    echo '</div>';
    
    if ($hasChildren) {
        echo '<ul>';
        foreach ($children as $child) {
            renderTree($pdo, $treeId, $child);
        }
        echo '</ul>';
    }
    echo '</li>';
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
            --line: #c4a77d;
            --bg: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Cairo', sans-serif; 
            background: var(--bg); 
            color: var(--primary); 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* شعار العائلة - في المنتصف */
        .logo-header {
            text-align: center; padding: 40px 20px 30px; z-index: 1001;
            display: flex; justify-content: center; align-items: center;
        }
        .logo-container {
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%);
            padding: 8px; border-radius: 50%; 
            box-shadow: 0 12px 40px rgba(60, 47, 47, 0.15), 
                        0 4px 15px rgba(242, 194, 0, 0.2),
                        inset 0 2px 10px rgba(255, 255, 255, 0.8);
            border: 4px solid var(--line); 
            backdrop-filter: blur(15px);
            width: 200px; height: 200px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .logo-container:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 50px rgba(60, 47, 47, 0.2), 
                        0 6px 20px rgba(242, 194, 0, 0.3),
                        inset 0 2px 10px rgba(255, 255, 255, 0.9);
        }
        .logo-img {
            width: 100%; height: 100%; object-fit: cover;
            display: block !important;
            border-radius: 50%;
            background: transparent;
            position: relative;
            z-index: 2;
            opacity: 1;
            visibility: visible !important;
            transition: transform 0.3s ease;
        }
        .logo-container:hover .logo-img {
            transform: scale(1.02);
        }
        .logo-fallback {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--primary) 0%, #5a4a4a 50%, var(--primary) 100%);
            border-radius: 50%; color: var(--accent); font-size: 65px;
            position: absolute; top: 0; left: 0;
            z-index: 1;
            box-shadow: inset 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        /* زر الرجوع - على الجانب */
        .back-to-home {
            position: fixed; top: 20px; left: 20px; z-index: 1002;
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--primary); color: var(--accent);
            padding: 12px 25px; border-radius: 50px;
            text-decoration: none; font-weight: 700; font-size: 15px;
            box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
            transition: all 0.3s;
            border: 2px solid var(--line);
        }
        .back-to-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
            background: #2a2222;
        }

        /* منطقة الشجرة */
        .tree-wrapper { 
            padding: 30px 20px; 
            min-width: fit-content; 
            text-align: center; 
            flex: 1;
        }

        /* هيكل الشجرة */
        .tree {
            display: inline-block;
            white-space: nowrap;
            position: relative;
            padding: 5px 5px 15px;
        }

        .tree ul {
            list-style: none;
            display: flex;
            justify-content: center;
            gap: 35px;
            padding-top: 40px;
            margin: 0;
            position: relative;
        }

        .tree li {
            list-style: none;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0;
            margin: 0;
        }

        /* SVG طبقة الخطوط */
        .tree-lines {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: visible;
            z-index: 1;
        }

        /* البطاقة */
        .node-box { display: inline-block; position: relative; z-index: 2; }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 2px solid var(--line); 
            padding: 10px 15px;
            border-radius: 10px; 
            min-width: 130px; 
            box-shadow: 0 4px 10px rgba(60, 47, 47, 0.08);
            transition: 0.3s; 
            white-space: normal;
        }
        .card:hover { 
            transform: translateY(-3px); 
            border-color: var(--accent); 
            box-shadow: 0 8px 15px rgba(60, 47, 47, 0.15); 
        }
        .name { 
            font-weight: 700; 
            font-size: 14px; 
            color: var(--primary); 
            margin-bottom: 3px; 
            line-height: 1.3; 
        }
        .spouse { 
            font-size: 11px; 
            color: #7a634d; 
            border-top: 1px solid #eee; 
            margin-top: 6px; 
            padding-top: 6px; 
            font-weight: 600; 
        }
        .spouse span { 
            color: var(--primary); 
            font-weight: 700; 
        }

        /* أزرار الزوم */
        .zoom-btns { 
            position: fixed; 
            bottom: 30px; 
            left: 30px; 
            display: flex; 
            flex-direction: column; 
            gap: 12px; 
            z-index: 1000; 
        }
        .zoom-btns button {
            width: 55px; 
            height: 55px; 
            border-radius: 50%; 
            background: var(--primary); 
            color: var(--accent);
            border: none; 
            font-size: 22px; 
            cursor: pointer; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: 0.3s;
        }
        .zoom-btns button:hover {
            transform: scale(1.1);
            background: var(--accent);
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .tree-wrapper { padding: 20px 15px; }
            .tree ul { gap: 20px; padding-top: 30px; }
            .card { min-width: 110px; padding: 8px 12px; }
            .name { font-size: 12px; }
            .spouse { font-size: 10px; }
            .zoom-btns { bottom: 20px; left: 20px; }
            .zoom-btns button { width: 45px; height: 45px; font-size: 18px; }
            .logo-header {
                padding: 30px 15px 20px;
            }
            .logo-container {
                width: 150px; height: 150px;
                padding: 6px;
                border-width: 3px;
            }
            .logo-fallback {
                font-size: 50px;
            }
            .back-to-home {
                top: 10px; left: 10px;
                padding: 10px 18px; font-size: 13px;
            }
        }

        @media print {
            .zoom-btns, .logo-header, .back-to-home { display: none !important; }
            body { background: white; }
            .tree-wrapper { padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- زر الرجوع - على الجانب -->
    <a href="/familytree/index.php" class="back-to-home">
        <i class="fas fa-arrow-right"></i>
        <span>رجوع للصفحة الرئيسية</span>
    </a>

    <!-- شعار العائلة - في المنتصف -->
    <div class="logo-header">
        <div class="logo-container">
            <?php
            // تحديد المسار الصحيح للصورة
            $baseDir = dirname(__DIR__);
            $logoFile = $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png';
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            
            // التحقق من وجود الصورة محلياً أولاً
            if (file_exists($logoFile) && is_file($logoFile)) {
                // استخدام المسار النسبي
                $finalLogoPath = '../assets/logo.png';
            } else {
                // استخدام المسار الكامل من النطاق
                $finalLogoPath = $baseUrl . '/familytree/assets/logo.png';
            }
            
            // إضافة timestamp لتجنب cache issues
            $finalLogoPath .= '?v=' . time();
            ?>
            <img id="familyLogo" 
                 src="<?= htmlspecialchars($finalLogoPath, ENT_QUOTES, 'UTF-8') ?>" 
                 alt="شعار العائلة" 
                 class="logo-img"
                 style="display: block !important; visibility: visible !important; opacity: 1 !important;"
                 onload="handleLogoLoad(this);"
                 onerror="handleLogoError(this);">
            <div class="logo-fallback" id="logoFallback" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;">
                <i class="fas fa-tree"></i>
            </div>
        </div>
    </div>

    <script>
        function handleLogoLoad(img) {
            console.log('✅ تم تحميل الصورة بنجاح من:', img.src);
            img.style.display = 'block';
            img.style.visibility = 'visible';
            img.style.opacity = '1';
            var fallback = document.getElementById('logoFallback');
            if (fallback) {
                fallback.style.display = 'none';
            }
        }
        
        function handleLogoError(img) {
            console.error('❌ فشل تحميل الصورة من:', img.src);
            console.error('naturalWidth:', img.naturalWidth, 'naturalHeight:', img.naturalHeight);
            tryAlternativePath(img);
        }
        
        function tryAlternativePath(img) {
            console.log('❌ فشل تحميل الصورة من:', img.src);
            console.log('📍 الموقع الحالي:', window.location.href);
            console.log('🌐 النطاق:', window.location.origin);
            
            var baseUrl = window.location.origin;
            var currentPath = window.location.pathname;
            
            // قائمة المسارات المحتملة - مرتبة حسب الأولوية
            var timestamp = '?v=' + new Date().getTime();
            var paths = [
                '../assets/logo.png' + timestamp,                     // المسار النسبي (الأولوية الأولى)
                baseUrl + '/familytree/assets/logo.png' + timestamp,  // المسار الكامل من النطاق الحالي
                'https://roayaom.com/familytree/assets/logo.png' + timestamp,  // المسار المباشر من النطاق
                '/familytree/assets/logo.png' + timestamp,            // المسار المطلق
                'assets/logo.png' + timestamp                         // مسار بسيط
            ];
            
            var currentIndex = 0;
            var maxAttempts = paths.length;
            var attemptCount = 0;
            var loaded = false;
            
            function tryNext() {
                if (loaded) return; // إذا تم التحميل بنجاح، توقف
                
                attemptCount++;
                if (attemptCount <= maxAttempts && currentIndex < paths.length) {
                    var newPath = paths[currentIndex];
                    console.log('🔄 محاولة ' + attemptCount + '/' + maxAttempts + ': تحميل من', newPath);
                    
                    // إنشاء صورة جديدة للاختبار
                    var testImg = new Image();
                    testImg.onload = function() {
                        console.log('✅ نجح التحميل من:', newPath);
                        loaded = true;
                        img.onerror = null;
                        img.src = newPath;
                        img.style.display = 'block';
                        img.style.visibility = 'visible';
                        img.style.opacity = '1';
                        var fallback = document.getElementById('logoFallback');
                        if (fallback) {
                            fallback.style.display = 'none';
                        }
                    };
                    testImg.onerror = function() {
                        console.log('❌ فشل من:', newPath);
                        currentIndex++;
                        setTimeout(tryNext, 200);
                    };
                    testImg.src = newPath;
                } else {
                    console.error('❌ فشل تحميل الصورة من جميع المسارات بعد', attemptCount, 'محاولة');
                    console.log('📋 المسارات المحاولة:', paths);
                    img.style.display = 'none';
                    var fallback = document.getElementById('logoFallback');
                    if (fallback) {
                        fallback.style.display = 'flex';
                    }
                }
            }
            
            tryNext();
        }
        
        function loadLogoFallback(img) {
            tryAlternativePath(img);
        }
        
        // اختبار مباشر للصورة عند تحميل الصفحة
        (function() {
            var logo = document.getElementById('familyLogo');
            if (!logo) return;
            
            // اختبار مباشر بعد تحميل الصفحة
            setTimeout(function() {
                var testImg = new Image();
                testImg.onload = function() {
                    console.log('✅ الصورة متاحة من:', this.src);
                    logo.src = this.src;
                    logo.style.display = 'block';
                    logo.style.visibility = 'visible';
                    logo.style.opacity = '1';
                    var fallback = document.getElementById('logoFallback');
                    if (fallback) {
                        fallback.style.display = 'none';
                    }
                };
                testImg.onerror = function() {
                    console.log('❌ الصورة غير متاحة من:', this.src);
                    // جرب المسارات البديلة
                    if (logo.complete && logo.naturalHeight === 0) {
                        tryAlternativePath(logo);
                    }
                };
                
                // جرب المسار الحالي أولاً
                testImg.src = logo.src;
            }, 100);
        })();
        
        // محاولة فورية عند تحميل الصفحة
        window.addEventListener('load', function() {
            var logo = document.getElementById('familyLogo');
            if (logo) {
                setTimeout(function() {
                    if (logo.complete && logo.naturalHeight === 0) {
                        console.log('⚠️ الصورة لم تحمل بعد، جاري المحاولة من مسارات بديلة...');
                        tryAlternativePath(logo);
                    } else if (logo.complete && logo.naturalHeight !== 0) {
                        console.log('✅ تم تحميل الصورة بنجاح من:', logo.src);
                        logo.style.display = 'block';
                        logo.style.visibility = 'visible';
                        logo.style.opacity = '1';
                        var fallback = document.getElementById('logoFallback');
                        if (fallback) {
                            fallback.style.display = 'none';
                        }
                    }
                }, 300);
            }
        });
    </script>

    <div class="zoom-btns">
        <button onclick="applyZoom(1.1)" title="تكبير"><i class="fas fa-plus"></i></button>
        <button onclick="applyZoom(0.9)" title="تصغير"><i class="fas fa-minus"></i></button>
    </div>

    <div class="tree-wrapper">
        <div id="mainTree" style="transform-origin: top center; transition: transform 0.2s;">
            <div class="tree">
                <svg class="tree-lines" id="treeLines" aria-hidden="true"></svg>
                <ul>
                    <?php renderTree($pdo, $treeId, $root); ?>
                </ul>
            </div>
        </div>
    </div>

    <?php 
    $footerPath = __DIR__ . '/../footer.php';
    if (!file_exists($footerPath)) {
        $footerPath = dirname(__DIR__) . '/footer.php';
    }
    if (file_exists($footerPath)) {
        include $footerPath;
    }
    ?>

    <script>
        let scale = 0.7;
        const tree = document.getElementById('mainTree');
        tree.style.transform = `scale(${scale})`;

        function applyZoom(factor) {
            scale *= factor;
            if (scale < 0.2) scale = 0.2;
            if (scale > 3) scale = 3;
            tree.style.transform = `scale(${scale})`;
            drawTreeLines();
        }

        window.onload = () => {
            window.scrollTo({
                left: (document.body.scrollWidth - window.innerWidth) / 2,
                behavior: 'smooth'
            });
            setTimeout(drawTreeLines, 50);
        };

        window.addEventListener('resize', () => {
            drawTreeLines();
        });

        function drawTreeLines() {
            const treeEl = document.querySelector('#mainTree .tree');
            const svg = document.getElementById('treeLines');
            if (!treeEl || !svg) return;

            const treeRect = treeEl.getBoundingClientRect();
            svg.setAttribute('viewBox', `0 0 ${Math.max(1, treeRect.width)} ${Math.max(1, treeRect.height)}`);
            svg.setAttribute('width', treeRect.width);
            svg.setAttribute('height', treeRect.height);

            while (svg.firstChild) svg.removeChild(svg.firstChild);

            const stroke = getComputedStyle(document.documentElement).getPropertyValue('--line').trim() || '#c4a77d';
            const strokeWidth = 3;

            const parents = treeEl.querySelectorAll('li');
            parents.forEach((li) => {
                const childUl = li.querySelector(':scope > ul');
                if (!childUl) return;

                const parentCard = li.querySelector(':scope > .node-box > .card');
                if (!parentCard) return;

                const childrenCards = childUl.querySelectorAll(':scope > li > .node-box > .card');
                if (!childrenCards.length) return;

                const p = parentCard.getBoundingClientRect();
                const parentX = (p.left - treeRect.left) + (p.width / 2);
                const parentY = (p.top - treeRect.top) + p.height;

                const childPoints = Array.from(childrenCards).map((c) => {
                    const r = c.getBoundingClientRect();
                    return {
                        x: (r.left - treeRect.left) + (r.width / 2),
                        y: (r.top - treeRect.top)
                    };
                });

                const minX = Math.min(...childPoints.map(pt => pt.x));
                const maxX = Math.max(...childPoints.map(pt => pt.x));
                const topChildY = Math.min(...childPoints.map(pt => pt.y));

                const junctionY = Math.min(parentY + 20, topChildY - 20);

                addLine(svg, parentX, parentY, parentX, junctionY, stroke, strokeWidth);
                addLine(svg, minX, junctionY, maxX, junctionY, stroke, strokeWidth);

                childPoints.forEach((pt) => {
                    addLine(svg, pt.x, junctionY, pt.x, pt.y, stroke, strokeWidth);
                });
            });
        }

        function addLine(svg, x1, y1, x2, y2, stroke, strokeWidth) {
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x1.toFixed(2));
            line.setAttribute('y1', y1.toFixed(2));
            line.setAttribute('x2', x2.toFixed(2));
            line.setAttribute('y2', y2.toFixed(2));
            line.setAttribute('stroke', stroke);
            line.setAttribute('stroke-width', String(strokeWidth));
            line.setAttribute('stroke-linecap', 'round');
            svg.appendChild(line);
        }
    </script>
</body>
</html>