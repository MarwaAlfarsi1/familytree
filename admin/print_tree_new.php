<?php
session_start();
if (!isset($_SESSION['admin_id'])) { 
    header("Location: auth/login_username.php"); 
    exit(); 
}
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/functions.php";

/** Fetch main tree */
$main = $pdo->query("SELECT * FROM trees WHERE tree_type='main' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$main){ die("لا توجد شجرة"); }
$treeId = (int)$main['id'];

/** Fetch grandfather (root) */
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

/** Function to get children - محدث ليشمل جميع الأبناء */
function getChildren($pdo, $treeId, $personId, $personGender) {
    $children = [];
    
    if ($personGender === 'male') {
        // إذا كان ذكر، جلب جميع أطفاله
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND father_id=? ORDER BY birth_date, full_name");
        $stmt->execute([$treeId, $personId]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // إذا كانت أنثى، جلب جميع أطفالها (من الزوج الأول والثاني)
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND mother_id=? ORDER BY birth_date, full_name");
        $stmt->execute([$treeId, $personId]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // إذا كان الزوج الثاني خارجي، جلب أبناءه من الشجرة الخارجية
        $stmt2 = $pdo->prepare("SELECT second_external_tree_id FROM persons WHERE id=? AND second_spouse_is_external=1 LIMIT 1");
        $stmt2->execute([$personId]);
        $personData = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($personData && !empty($personData['second_external_tree_id'])) {
            $stmt3 = $pdo->prepare("SELECT id FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
            $stmt3->execute([$personData['second_external_tree_id']]);
            $externalSpouseRoot = $stmt3->fetch(PDO::FETCH_ASSOC);
            
            if ($externalSpouseRoot) {
                $stmt4 = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND father_id=? ORDER BY birth_date, full_name");
                $stmt4->execute([$personData['second_external_tree_id'], $externalSpouseRoot['id']]);
                $externalChildren = $stmt4->fetchAll(PDO::FETCH_ASSOC);
                $children = array_merge($children, $externalChildren);
            }
        }
    }
    
    return $children;
}

/** Function to get internal spouse */
function getSpouse($pdo, $person) {
    if (!empty($person['spouse_person_id']) && empty($person['spouse_is_external'])) {
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
        $stmt->execute([$person['spouse_person_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

/** Function to get second spouse */
function getSecondSpouse($pdo, $person) {
    if (!empty($person['second_spouse_person_id']) && empty($person['second_spouse_is_external'])) {
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE id=? LIMIT 1");
        $stmt->execute([$person['second_spouse_person_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($person['second_spouse_is_external']) && !empty($person['second_external_tree_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
        $stmt->execute([$person['second_external_tree_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

/** Recursive function to render tree */
function renderTreeRecursive($pdo, $treeId, $person) {
    // جلب الأطفال - محدث ليشمل جميع الأبناء
    $children = getChildren($pdo, $treeId, $person['id'], $person['gender']);
    $spouse = getSpouse($pdo, $person);
    $secondSpouse = getSecondSpouse($pdo, $person);
    
    echo '<li>';
    echo '<div class="person-node">';
    echo '<div class="person-info">';
    echo '<span class="name">' . htmlspecialchars($person['full_name']) . '</span>';
    if ($spouse) {
        $spouseLabel = ($person['gender'] === 'male') ? 'زوجة: ' : 'زوج: ';
        echo '<div class="spouse">' . $spouseLabel . htmlspecialchars($spouse['full_name']) . '</div>';
    }
    if ($secondSpouse) {
        echo '<div class="spouse spouse-second">زوج ثاني: ' . htmlspecialchars($secondSpouse['full_name']) . '</div>';
    }
    echo '</div>';
    echo '</div>';
    
    if (!empty($children)) {
        echo '<ul>';
        foreach ($children as $child) {
            renderTreeRecursive($pdo, $treeId, $child);
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
    <title>طباعة شجرة العائلة الكلاسيكية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --tree-color: #3c2f2f;
            --node-bg: #fff;
            --node-border: #c4a77d;
            --text-color: #3c2f2f;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: #fcfbf7;
            color: var(--text-color);
            padding: 40px;
            direction: rtl;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--tree-color);
            font-weight: 800;
        }

        .header p {
            color: #6b543f;
            font-size: 16px;
        }

        /* Tree CSS Structure */
        .tree {
            display: flex;
            justify-content: center;
            padding-top: 20px;
        }

        .tree ul {
            padding-top: 20px; 
            position: relative;
            display: flex;
            justify-content: center;
            margin: 0;
            list-style: none;
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

        /* Vertical line from parent down to children level */
        .tree ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 2px solid var(--tree-color);
            width: 0;
            height: 20px;
            transform: translateX(-50%);
        }

        /* Remove vertical line for root */
        .tree > ul::before {
            display: none;
        }

        /* Horizontal connector line - right side */
        .tree li::before {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            width: 50%;
            height: 20px;
            border-top: 2px solid var(--tree-color);
            border-right: 2px solid var(--tree-color);
        }

        /* Horizontal connector line - left side */
        .tree li::after {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 20px;
            border-top: 2px solid var(--tree-color);
        }

        /* Single child - remove all connectors */
        .tree li:only-child::after,
        .tree li:only-child::before {
            display: none;
        }

        .tree li:only-child {
            padding-top: 0;
        }

        /* First child - remove left connector, keep right */
        .tree li:first-child::before {
            border-top: 2px solid var(--tree-color);
            border-right: 2px solid var(--tree-color);
        }

        .tree li:first-child::after {
            display: none;
        }

        /* Last child - remove right connector, keep left */
        .tree li:last-child::after {
            border-top: 2px solid var(--tree-color);
        }

        .tree li:last-child::before {
            border-top: 2px solid var(--tree-color);
            border-right: 2px solid var(--tree-color);
        }

        /* Middle children - show both connectors */
        .tree li:not(:first-child):not(:last-child):not(:only-child)::before {
            border-top: 2px solid var(--tree-color);
            border-right: 2px solid var(--tree-color);
        }

        .tree li:not(:first-child):not(:last-child):not(:only-child)::after {
            border-top: 2px solid var(--tree-color);
        }

        .person-node {
            display: inline-block;
            background: var(--node-bg);
            border: 2px solid var(--node-border);
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            min-width: 120px;
            z-index: 1;
        }

        .person-info .name {
            font-weight: 700;
            font-size: 14px;
            display: block;
            margin-bottom: 4px;
            color: var(--text-color);
        }

        .person-info .spouse {
            font-size: 11px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 4px;
            margin-top: 4px;
        }

        .person-info .spouse-second {
            color: #9b59b6;
        }

        /* Control Panel */
        .controls {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .btn {
            background: var(--tree-color);
            color: #f2c200;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Cairo', sans-serif;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: #fff;
            color: #3c2f2f;
            border: 1px solid #ccc;
        }

        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                background: #fff;
            }
            .tree {
                transform: scale(0.85);
                transform-origin: top center;
            }
            @page {
                size: landscape;
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="controls no-print">
        <button onclick="window.print()" class="btn">
            <i class="fas fa-print"></i> طباعة الشجرة
        </button>
        <a href="dashboard_new.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع للوحة التحكم
        </a>
    </div>

    <div class="header">
        <h1>شجرة عائلة العائلة الكريمة</h1>
        <p>مخطط هرمي متسلسل للأنساب</p>
    </div>

    <div class="tree">
        <ul>
            <?php 
            if($root) {
                renderTreeRecursive($pdo, $treeId, $root);
            } else {
                echo "<li>لا توجد بيانات للعرض</li>";
            }
            ?>
        </ul>
    </div>
</body>
</html>