<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

if (!isset($_SESSION['admin_id'])) { 
    header("Location: auth/login_username.php"); 
    exit(); 
}

// تحديد مسار ملفات config - محاولة عدة مسارات
$dbPath = null;
$possiblePaths = [
    __DIR__ . "/../db.php",
    dirname(__DIR__) . "/db.php",
    $_SERVER['DOCUMENT_ROOT'] . "/familytree/db.php",
    dirname(dirname(__FILE__)) . "/db.php"
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $dbPath = $path;
        break;
    }
}

if ($dbPath && file_exists($dbPath)) {
    require_once $dbPath;
} else {
    // محاولة الاتصال المباشر
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
        die("خطأ في الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage()));
    }
}

if (!isset($pdo) || !$pdo) {
    die("خطأ: فشل الاتصال بقاعدة البيانات");
}

$functionsPath = __DIR__ . "/../functions.php";
if (!file_exists($functionsPath)) {
    $functionsPath = dirname(__DIR__) . "/functions.php";
}
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

/** جلب الشجرة الرئيسية */
try {
    $main = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$main){ 
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
function getChildren($pdo, $treeId, $personId) {
    $children = [];
    try {
        // جلب الأطفال من الأب
        $st = $pdo->prepare("SELECT p.* 
                            FROM persons p
                            LEFT JOIN trees t ON p.tree_id = t.id
                            WHERE p.father_id = ? 
                            AND (t.tree_type IS NULL OR t.tree_type != 'external')
                            ORDER BY p.id ASC");
        $st->execute([$personId]);
        $fatherChildren = $st->fetchAll(PDO::FETCH_ASSOC);
        $children = array_merge($children, $fatherChildren);
        
        // جلب الأطفال من الأم
        $st2 = $pdo->prepare("SELECT p.* 
                             FROM persons p
                             LEFT JOIN trees t ON p.tree_id = t.id
                             WHERE p.mother_id = ? 
                             AND (t.tree_type IS NULL OR t.tree_type != 'external')
                             ORDER BY p.id ASC");
        $st2->execute([$personId]);
        $motherChildren = $st2->fetchAll(PDO::FETCH_ASSOC);
        
        $existingIds = array_column($children, 'id');
        foreach ($motherChildren as $child) {
            if (!in_array($child['id'], $existingIds)) {
                $children[] = $child;
            }
        }
    } catch (Exception $e) {
        error_log("Error in getChildren: " . $e->getMessage());
    }
    return $children;
}

/** وظيفة جلب الزوج/الزوجة */
function getSpouse($pdo, $person) {
    try {
        if (!empty($person['spouse_person_id']) && empty($person['spouse_is_external'])) {
            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id=? LIMIT 1");
            $stmt->execute([$person['spouse_person_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($person['spouse_is_external']) && !empty($person['external_tree_id'])) {
            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
            $stmt->execute([$person['external_tree_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error in getSpouse: " . $e->getMessage());
    }
    return null;
}

/** وظيفة جلب الزوج الثاني */
function getSecondSpouse($pdo, $person) {
    try {
        if (!empty($person['second_spouse_person_id']) && empty($person['second_spouse_is_external'])) {
            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE id=? LIMIT 1");
            $stmt->execute([$person['second_spouse_person_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($person['second_spouse_is_external']) && !empty($person['second_external_tree_id'])) {
            $stmt = $pdo->prepare("SELECT full_name FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
            $stmt->execute([$person['second_external_tree_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error in getSecondSpouse: " . $e->getMessage());
    }
    return null;
}

/** البناء التكراري للشجرة */
function renderTree($pdo, $treeId, $person) {
    if (!$person || !is_array($person) || empty($person['id'])) {
        return;
    }
    
    try {
        $children = getChildren($pdo, $treeId, $person['id']);
        $spouse = getSpouse($pdo, $person);
        $secondSpouse = getSecondSpouse($pdo, $person);
        $spouseLabel = ($person['gender'] === 'male') ? 'زوجة: ' : 'زوج: ';
        $fullName = isset($person['full_name']) ? htmlspecialchars($person['full_name'], ENT_QUOTES, 'UTF-8') : '';
        
        echo '<li>';
        echo '<div class="card">';
        echo '<div class="name">' . $fullName . '</div>';
        if ($spouse && isset($spouse['full_name'])) {
            echo '<div class="spouse">' . $spouseLabel . '<span>' . htmlspecialchars($spouse['full_name'], ENT_QUOTES, 'UTF-8') . '</span></div>';
        }
        if ($secondSpouse && isset($secondSpouse['full_name'])) {
            echo '<div class="spouse" style="color: #9b59b6; border-top: 1px solid #ddd; margin-top: 8px; padding-top: 8px;">زوج ثاني: <span>' . htmlspecialchars($secondSpouse['full_name'], ENT_QUOTES, 'UTF-8') . '</span></div>';
        }
        echo '</div>';
        
        if (!empty($children)) {
            echo '<ul>';
            foreach ($children as $child) {
                if ($child && is_array($child) && !empty($child['id'])) {
                    renderTree($pdo, $treeId, $child);
                }
            }
            echo '</ul>';
        }
        echo '</li>';
    } catch (Exception $e) {
        error_log("Error in renderTree: " . $e->getMessage());
        return;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>شجرة العائلة الكلاسيكية</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root {
    --primary: #3c2f2f;
    --accent: #f2c200;
    --line: #c4a77d;
    --bg: #fcfaf5;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Cairo', sans-serif; 
    background: var(--bg); 
    color: var(--primary); 
    min-height: 100vh; 
}

.logo-header {
    text-align: center; 
    padding: 0 0 20px; 
    z-index: 1001;
    display: flex; 
    justify-content: center; 
    align-items: center;
    position: relative;
    margin: 0 auto;
    width: 100%;
}
.logo-container {
    display: flex; 
    align-items: center; 
    justify-content: center;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%);
    padding: 6px; 
    border-radius: 50%; 
    box-shadow: 0 12px 40px rgba(60, 47, 47, 0.15), 
                0 4px 15px rgba(242, 194, 0, 0.2),
                inset 0 2px 10px rgba(255, 255, 255, 0.8);
    border: 3px solid var(--line); 
    backdrop-filter: blur(15px);
    width: 150px; 
    height: 150px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    margin: 0 auto;
}
.logo-img {
    width: 100%; 
    height: 100%; 
    object-fit: cover;
    display: block !important;
    border-radius: 50%;
    background: transparent;
    position: relative;
    z-index: 2;
    opacity: 1;
    visibility: visible !important;
}
.logo-fallback {
    width: 100%; 
    height: 100%; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    background: linear-gradient(135deg, var(--primary) 0%, #5a4a4a 50%, var(--primary) 100%);
    border-radius: 50%; 
    color: var(--accent); 
    font-size: 65px;
    position: absolute; 
    top: 0; 
    left: 0;
    z-index: 1;
}

.back-to-dashboard {
    position: fixed; 
    top: 20px; 
    left: 20px; 
    z-index: 1002;
    display: inline-flex; 
    align-items: center; 
    gap: 10px;
    background: var(--primary); 
    color: var(--accent);
    padding: 12px 25px; 
    border-radius: 50px;
    text-decoration: none; 
    font-weight: 700; 
    font-size: 15px;
    box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
    transition: all 0.3s;
    border: 2px solid var(--line);
}
.back-to-dashboard:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(60, 47, 47, 0.4);
    background: #2a2222;
}

.tree-wrapper { 
    padding: 10px 20px 40px; 
    min-width: fit-content; 
    text-align: center;
    overflow: auto;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.tree {
    width: 100%;
    overflow: auto;
    padding: 20px 10px 30px;
    text-align: center;
    display: flex;
    justify-content: center;
}

.tree ul {
    padding-top: 20px;
    position: relative;
    list-style: none;
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.tree li {
    text-align: center;
    list-style-type: none;
    position: relative;
    padding: 20px 5px 0 5px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* خط عمودي من الأب إلى مستوى الأبناء */
.tree ul::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    border-left: 2px solid var(--line);
    width: 0;
    height: 20px;
    transform: translateX(-50%);
}

/* إزالة الخط العمودي للجذر */
.tree > ul::before {
    display: none;
}

/* خط أفقي - الجانب الأيمن */
.tree li::before {
    content: '';
    position: absolute;
    top: 0;
    right: 50%;
    width: calc(50% + 1px);
    height: 20px;
    border-top: 2px solid var(--line);
    border-right: 2px solid var(--line);
}

/* خط أفقي - الجانب الأيسر */
.tree li::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: calc(50% + 1px);
    height: 20px;
    border-top: 2px solid var(--line);
}

/* طفل واحد - إزالة جميع الخطوط */
.tree li:only-child::after,
.tree li:only-child::before {
    display: none;
}

.tree li:only-child {
    padding-top: 0;
}

/* الطفل الأول - إزالة الخط الأيسر، الإبقاء على الأيمن */
.tree li:first-child::before {
    border-top: 2px solid var(--line);
    border-right: 2px solid var(--line);
    width: calc(50% + 1px);
}

.tree li:first-child::after {
    display: none;
}

/* إذا كان هناك طفل واحد فقط (first-child = last-child) */
.tree li:first-child:last-child::before {
    display: none;
}

/* الطفل الأخير - إزالة الخط الأيمن، الإبقاء على الأيسر */
.tree li:last-child::after {
    border-top: 2px solid var(--line);
    width: calc(50% + 1px);
    border-left: 2px solid var(--line);
    height: 20px;
    left: 50%;
}

.tree li:last-child::before {
    display: none;
}

/* إغلاق الخط في نهاية الطفل الأخير - إضافة خط عمودي صغير */
.tree li:last-child:not(:only-child)::after {
    border-left: 2px solid var(--line);
    border-top: 2px solid var(--line);
}

/* الأطفال الوسط - عرض كلا الخطين */
.tree li:not(:first-child):not(:last-child):not(:only-child)::before {
    border-right: 2px solid var(--line);
    border-top: 2px solid var(--line);
}

.tree li:not(:first-child):not(:last-child):not(:only-child)::after {
    border-top: 2px solid var(--line);
}

.card {
    display: inline-block;
    background: #fff;
    border: 2px solid var(--line);
    padding: 10px 15px;
    border-radius: 10px;
    min-width: 120px;
    max-width: 170px;
    box-shadow: 0 4px 10px rgba(60, 47, 47, 0.08);
    transition: 0.3s;
    white-space: normal;
    word-wrap: break-word;
    text-align: center;
    position: relative;
    z-index: 1;
}
.card:hover {
    transform: translateY(-3px);
    border-color: var(--accent);
    box-shadow: 0 8px 18px rgba(60, 47, 47, 0.15);
}
.name {
    font-weight: 800;
    font-size: 13px;
    color: var(--primary);
    margin-bottom: 3px;
    line-height: 1.3;
    word-break: break-word;
}
.spouse {
    font-size: 10px;
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
}

@media (max-width: 1200px) {
    .card {
        min-width: 110px;
        max-width: 160px;
        padding: 9px 13px;
    }
    .name {
        font-size: 12px;
    }
    .spouse {
        font-size: 9px;
    }
    .tree ul {
        padding-top: 18px;
    }
    .tree li {
        padding: 18px 4px 0 4px;
    }
}

@media (max-width: 768px) {
    .logo-header {
        padding: 0 0 15px;
    }
    .logo-container {
        width: 120px; 
        height: 120px;
    }
    .back-to-dashboard {
        top: 10px; 
        left: 10px;
        padding: 8px 15px; 
        font-size: 12px;
    }
    .tree-wrapper {
        padding: 15px 10px;
    }
    .tree {
        padding: 10px 5px 20px;
    }
    .tree ul {
        padding-top: 12px;
    }
    .tree li {
        padding: 10px 2px 0 2px;
    }
    .card {
        min-width: 110px;
        max-width: 150px;
        padding: 8px 12px;
        border-width: 2px;
    }
    .name {
        font-size: 12px;
    }
    .spouse {
        font-size: 9px;
        margin-top: 6px;
        padding-top: 6px;
    }
    .zoom-btns {
        bottom: 20px;
        left: 20px;
    }
    .zoom-btns button {
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .logo-header {
        padding: 0 0 10px;
    }
    .logo-container {
        width: 100px; 
        height: 100px;
    }
    .tree-wrapper {
        padding: 10px 5px;
    }
    .tree ul {
        padding-top: 10px;
    }
    .tree li {
        padding: 8px 1px 0 1px;
    }
    .card {
        min-width: 90px;
        max-width: 130px;
        padding: 6px 10px;
    }
    .name {
        font-size: 11px;
    }
    .spouse {
        font-size: 8px;
        margin-top: 4px;
        padding-top: 4px;
    }
    .zoom-btns {
        bottom: 15px;
        left: 15px;
    }
    .zoom-btns button {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
}
</style>
</head>
<body>
    <a href="dashboard_new.php" class="back-to-dashboard">
        <i class="fas fa-arrow-right"></i>
        <span>رجوع للوحة التحكم</span>
    </a>

    <div class="zoom-btns">
        <button onclick="applyZoom(1.1)" title="تكبير"><i class="fas fa-plus"></i></button>
        <button onclick="applyZoom(0.9)" title="تصغير"><i class="fas fa-minus"></i></button>
    </div>

    <div class="tree-wrapper">
        <div class="logo-header">
            <div class="logo-container">
                <?php
                $baseDir = dirname(__DIR__);
                $logoFile = $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png';
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                
                if (file_exists($logoFile) && is_file($logoFile)) {
                    $finalLogoPath = '../assets/logo.png';
                } else {
                    $finalLogoPath = $baseUrl . '/familytree/assets/logo.png';
                }
                
                $finalLogoPath .= '?v=' . time();
                ?>
                <img id="familyLogo" 
                     src="<?= htmlspecialchars($finalLogoPath, ENT_QUOTES, 'UTF-8') ?>" 
                     alt="شعار العائلة" 
                     class="logo-img"
                     onerror="document.getElementById('logoFallback').style.display='flex';">
                <div class="logo-fallback" id="logoFallback" style="display: none;">
                    <i class="fas fa-tree"></i>
                </div>
            </div>
        </div>
        
        <div id="mainTree" style="transform-origin: top center; transition: transform 0.2s;">
            <div class="tree">
                <ul>
                    <?php 
                    if($root) {
                        renderTree($pdo, $treeId, $root);
                    } else {
                        echo "<li>لا توجد بيانات للعرض</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

<script>
let scale = 0.75;
const tree = document.getElementById('mainTree');
if (tree) {
    tree.style.transform = 'scale(' + scale + ')';
}

function applyZoom(factor) {
    scale *= factor;
    if (scale < 0.2) scale = 0.2;
    if (scale > 3) scale = 3;
    if (tree) {
        tree.style.transform = 'scale(' + scale + ')';
    }
}

window.onload = function() {
    if (window.innerWidth > 768) {
        var treeWrapper = document.querySelector('.tree-wrapper');
        if (treeWrapper) {
            setTimeout(function() {
                treeWrapper.scrollLeft = (treeWrapper.scrollWidth - treeWrapper.clientWidth) / 2;
            }, 200);
        }
    }
};
</script>
</body>
</html>