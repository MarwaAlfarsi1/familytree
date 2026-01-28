<?php
require_once __DIR__ . '/config/db.php';

$treeId = (int)($_GET['tree_id'] ?? 0);

if ($treeId <= 0) {
    die("بيانات غير مكتملة");
}

// جلب الجد (الزوج الخارجي)
$stmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND is_root=1 LIMIT 1");
$stmt->execute([$treeId]);
$spouse = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$spouse) {
    die("لا يوجد زوج خارجي");
}

// جلب الأبناء
$childrenStmt = $pdo->prepare("SELECT * FROM persons WHERE tree_id=? AND father_id=? ORDER BY id ASC");
$childrenStmt->execute([$treeId, $spouse['id']]);
$children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function displayPhoto($person) {
    if (!empty($person['photo_path']) && file_exists($person['photo_path'])) {
        return '<img src="' . h($person['photo_path']) . '" alt="' . h($person['full_name']) . '" style="width:80px;height:80px;object-fit:cover;border-radius:50%;margin:5px 0;border:3px solid #c7a56b;">';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>عائلة خارجية</title>
<style>
body {
    font-family: 'Cairo', Tahoma, Arial, sans-serif;
    background: #f5efe6;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 900px;
    margin: 0 auto;
    background: #fff;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0,0,0,.1);
}

.header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #c7a56b;
}

.header h1 {
    color: #4b2e1e;
    margin-bottom: 10px;
}

.spouse-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
    border: 2px solid #c7a56b;
}

.spouse-name {
    font-size: 24px;
    font-weight: bold;
    color: #3c2f2f;
    margin: 10px 0;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    margin: 5px;
}

.btn-primary {
    background: #3c2f2f;
    color: gold;
}

.btn:hover {
    opacity: 0.9;
}

.children-list {
    margin-top: 30px;
}

.child-item {
    background: #fff;
    border: 1px solid #e0e0e0;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.child-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.child-details {
    flex: 1;
}

.child-name {
    font-size: 18px;
    font-weight: bold;
    color: #3c2f2f;
    margin-bottom: 5px;
}

.child-gender {
    color: #6b543f;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6b543f;
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>عائلة خارجية</h1>
        <a href="view_public.php" class="btn btn-primary">رجوع</a>
    </div>

    <!-- عرض الزوج الخارجي -->
    <div class="spouse-card">
        <?php echo displayPhoto($spouse); ?>
        <div class="spouse-name"><?= h($spouse['full_name']) ?></div>
        <div style="color: #6b543f; margin-top: 5px;">
            <?= $spouse['gender'] === 'male' ? 'ذكر' : 'أنثى' ?>
        </div>
    </div>

    <!-- قائمة الأبناء -->
    <div class="children-list">
        <h2 style="color: #4b2e1e; margin-bottom: 20px;">الأبناء</h2>
        
        <?php if (empty($children)): ?>
            <div class="empty-state">
                لا يوجد أبناء حتى الآن
            </div>
        <?php else: ?>
            <?php foreach ($children as $child): ?>
                <div class="child-item">
                    <div class="child-info">
                        <?php echo displayPhoto($child); ?>
                        <div class="child-details">
                            <div class="child-name"><?= h($child['full_name']) ?></div>
                            <div class="child-gender">
                                <?= $child['gender'] === 'male' ? 'ذكر' : 'أنثى' ?>
                                <?php if (!empty($child['birth_date'])): ?>
                                    - تاريخ الميلاد: <?= h($child['birth_date']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

