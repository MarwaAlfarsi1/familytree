<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}

// تحميل ملف قاعدة البيانات المركزي
$dbPath = __DIR__ . "/../config/db.php";
if (!file_exists($dbPath)) {
    $dbPath = dirname(__DIR__) . "/config/db.php";
}
if (!file_exists($dbPath)) {
    die("خطأ: ملف قاعدة البيانات غير موجود. يرجى التأكد من وجود config/db.php");
}
require_once $dbPath;

// التحقق من وجود $pdo بعد التحميل
if (!isset($pdo) || !$pdo) {
    die("خطأ: فشل الاتصال بقاعدة البيانات");
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );
    } catch (PDOException $e) {
        die("خطأ في الاتصال بقاعدة البيانات");
    }
}

$functionsPath = __DIR__ . "/../functions.php";
if (!file_exists($functionsPath)) {
    $functionsPath = dirname(__DIR__) . "/functions.php";
}
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
$messageType = '';

// معالجة توزيع أرقام العضوية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_numbers'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. جلب الجد المؤسس (is_root = 1 في الشجرة الرئيسية)
        $mainTree = $pdo->query("SELECT id FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $mainTreeId = $mainTree ? (int)$mainTree['id'] : 0;
        
        $rootPerson = null;
        if ($mainTreeId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
            $stmt->execute([$mainTreeId]);
            $rootPerson = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // 2. إلغاء جميع أرقام العضوية الحالية (عدا الجد المؤسس)
        if ($rootPerson) {
            $stmt = $pdo->prepare("UPDATE persons SET membership_number = NULL WHERE id != ?");
            $stmt->execute([(int)$rootPerson['id']]);
        } else {
            $pdo->query("UPDATE persons SET membership_number = NULL");
        }
        
        // 3. إعطاء الجد المؤسس رقم 0001 إذا لم يكن لديه
        if ($rootPerson) {
            $stmt = $pdo->prepare("UPDATE persons SET membership_number = '0001' WHERE id = ?");
            $stmt->execute([(int)$rootPerson['id']]);
        }
        
        // 4. جلب جميع الأشخاص المؤهلين للحصول على رقم عضوية
        // شروط التأهيل:
        // - ليسوا في شجرة خارجية (tree_type != 'external') - هذا يستبعد الأزواج/الزوجات الخارجيين
        // - ليسوا الجد المؤسس (سيتم تخطيه)
        // ملاحظة: spouse_is_external يشير إلى أن الشخص لديه زوج خارجي، وليس أنه هو نفسه زوج خارجي
        // لذلك لا نستبعد الأشخاص بناءً على spouse_is_external
        
        $sql = "SELECT p.* 
                FROM persons p
                LEFT JOIN trees t ON p.tree_id = t.id
                WHERE (t.tree_type IS NULL OR t.tree_type != 'external')
                AND p.is_root = 0";
        
        if ($rootPerson) {
            $sql .= " AND p.id != " . (int)$rootPerson['id'];
        }
        
        // ترتيب حسب تاريخ الميلاد (من الأكبر سناً إلى الأصغر)
        // الأشخاص بدون تاريخ ميلاد يأتون في النهاية
        // ترتيب إضافي حسب id للأشخاص بنفس تاريخ الميلاد
        $sql .= " ORDER BY 
                    CASE 
                        WHEN p.birth_date IS NULL OR p.birth_date = '' OR p.birth_date = '0000-00-00' THEN 1 
                        ELSE 0 
                    END,
                    p.birth_date ASC,
                    p.id ASC";
        
        $stmt = $pdo->query($sql);
        $eligiblePersons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. توزيع الأرقام بدءاً من 0002
        $currentNumber = 2;
        foreach ($eligiblePersons as $person) {
            $membershipNumber = str_pad($currentNumber, 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE persons SET membership_number = ? WHERE id = ?");
            $stmt->execute([$membershipNumber, (int)$person['id']]);
            $currentNumber++;
        }
        
        $pdo->commit();
        $message = "تم توزيع أرقام العضوية بنجاح حسب تاريخ الميلاد!";
        $messageType = "success";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "حدث خطأ أثناء توزيع أرقام العضوية: " . htmlspecialchars($e->getMessage());
        $messageType = "error";
    }
}

// جلب الجد المؤسس
$mainTree = $pdo->query("SELECT id FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$mainTreeId = $mainTree ? (int)$mainTree['id'] : 0;

$rootPerson = null;
if ($mainTreeId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id = ? AND is_root = 1 LIMIT 1");
    $stmt->execute([$mainTreeId]);
    $rootPerson = $stmt->fetch(PDO::FETCH_ASSOC);
}

// الإحصائيات
$stats = [
    'without_number' => 0,
    'with_number' => 0,
    'total' => 0
];

// جلب جميع الأشخاص المؤهلين (من العائلة فقط - مستبعدين الأزواج الخارجيين)
// الأزواج الخارجيون هم فقط الأشخاص الموجودون في شجرة خارجية (tree_type = 'external')
$sql = "SELECT COUNT(*) as count 
        FROM persons p
        LEFT JOIN trees t ON p.tree_id = t.id
        WHERE (t.tree_type IS NULL OR t.tree_type != 'external')
        AND p.is_root = 0";
$stmt = $pdo->query($sql);
$stats['total'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

// الأشخاص بدون رقم عضوية
$sql = "SELECT COUNT(*) as count 
        FROM persons p
        LEFT JOIN trees t ON p.tree_id = t.id
        WHERE (t.tree_type IS NULL OR t.tree_type != 'external')
        AND p.is_root = 0
        AND (p.membership_number IS NULL OR p.membership_number = '')";
$stmt = $pdo->query($sql);
$stats['without_number'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

// الأشخاص مع رقم عضوية
$stats['with_number'] = $stats['total'] - $stats['without_number'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>توزيع أرقام العضوية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #3c2f2f;
            --accent: #f2c200;
            --line: #c4a77d;
            --bg: linear-gradient(135deg, #f5efe3 0%, #e8ddd0 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--primary);
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
            flex: 1;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                        0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box {
            background: linear-gradient(135deg, var(--accent) 0%, #f5d700 100%);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(242, 194, 0, 0.3);
        }

        .info-box p {
            margin: 8px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.8);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card.without {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }

        .stat-card.with {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }

        .stat-card.total {
            border-color: var(--line);
            background: rgba(196, 167, 125, 0.1);
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .stat-card.without .stat-number {
            color: #e74c3c;
        }

        .stat-card.with .stat-number {
            color: #27ae60;
        }

        .stat-card.total .stat-number {
            color: var(--primary);
        }

        .stat-label {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary);
        }

        .btn {
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--accent);
            box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            border: 2px solid #27ae60;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 2px solid #e74c3c;
            color: #721c24;
        }

        .note {
            background: rgba(196, 167, 125, 0.1);
            padding: 15px;
            border-radius: 10px;
            border-right: 4px solid var(--accent);
            margin-top: 20px;
            font-size: 14px;
            color: #6b543f;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php 
    $navPath = __DIR__ . '/nav.php';
    if (file_exists($navPath)) {
        include $navPath;
    }
    ?>
    <div class="container">
        <h1><i class="fas fa-id-card"></i> توزيع أرقام العضوية</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <h2 style="font-size: 22px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle"></i> معلومات التوزيع
            </h2>
            <div class="info-box">
                <?php if ($rootPerson): ?>
                    <p><strong>الجد (المؤسس):</strong> <?= h($rootPerson['full_name']) ?></p>
                    <p><strong>رقم العضوية الحالي:</strong> <?= h($rootPerson['membership_number'] ?? '0001') ?></p>
                <?php else: ?>
                    <p><strong>ملاحظة:</strong> لم يتم العثور على الجد المؤسس</p>
                <?php endif; ?>
            </div>

            <form method="POST" onsubmit="return confirm('هل أنت متأكد من توزيع أرقام العضوية حسب تاريخ الميلاد؟ سيتم إعادة تعيين جميع الأرقام الحالية (عدا الجد المؤسس).');">
                <button type="submit" name="assign_numbers" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> توزيع أرقام العضوية حسب تاريخ الميلاد
                </button>
            </form>

            <div class="note">
                <strong><i class="fas fa-lightbulb"></i> ملاحظات مهمة:</strong>
                <ul style="margin-top: 10px; padding-right: 20px; line-height: 1.8;">
                    <li><strong>الأزواج/الزوجات من خارج العائلة:</strong> لن يحصلوا على أرقام عضوية (مستبعدون تلقائياً)</li>
                    <li><strong>التوزيع:</strong> حسب تاريخ الميلاد (من الأكبر سناً = 0001 إلى الأصغر)</li>
                    <li><strong>الجد المؤسس:</strong> سيحتفظ برقم العضوية 0001 دائماً</li>
                    <li><strong>الأشخاص بدون تاريخ ميلاد:</strong> سيحصلون على أرقام في النهاية</li>
                    <li><strong>⚠️ مهم:</strong> عند إضافة أشخاص جدد، يجب إعادة التوزيع من هذه الصفحة</li>
                    <li><strong>⚠️ تنبيه:</strong> إذا أضفت شخصاً جديداً بتاريخ ميلاد أقدم من أشخاص موجودين، قد تتغير أرقامهم بعد إعادة التوزيع</li>
                    <li><strong>💡 نصيحة:</strong> أضف جميع الأشخاص أولاً، ثم قم بتوزيع الأرقام مرة واحدة</li>
                </ul>
            </div>
        </div>

        <div class="glass-card">
            <h2 style="font-size: 22px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-chart-bar"></i> الإحصائيات
            </h2>
            <div class="stats-grid">
                <div class="stat-card without">
                    <div class="stat-number"><?= $stats['without_number'] ?></div>
                    <div class="stat-label">بدون رقم عضوية</div>
                </div>
                <div class="stat-card with">
                    <div class="stat-number"><?= $stats['with_number'] ?></div>
                    <div class="stat-label">لديهم رقم عضوية</div>
                </div>
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">إجمالي الأعضاء</div>
                </div>
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
</body>
</html>