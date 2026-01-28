<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/functions.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}

// جلب الإحصائيات مباشرة من قاعدة البيانات (بدون استخدام الدالة القديمة)
// إحصائيات عامة - جميع الأفراد
$stmt = $pdo->query("SELECT COUNT(*) as total, 
                            SUM(CASE WHEN gender='male' THEN 1 ELSE 0 END) as males,
                            SUM(CASE WHEN gender='female' THEN 1 ELSE 0 END) as females
                     FROM persons");
$general = $stmt->fetch(PDO::FETCH_ASSOC);
$total = (int)($general['total'] ?? 0);
$males = (int)($general['males'] ?? 0);
$females = (int)($general['females'] ?? 0);

// إحصائيات لكل أسرة (كل أب وأطفاله)
$stmt = $pdo->query("SELECT p.id, p.full_name, 
                            COUNT(c.id) as children_count,
                            SUM(CASE WHEN c.gender='male' THEN 1 ELSE 0 END) as sons,
                            SUM(CASE WHEN c.gender='female' THEN 1 ELSE 0 END) as daughters
                     FROM persons p
                     LEFT JOIN persons c ON c.father_id = p.id
                     WHERE p.gender='male'
                     GROUP BY p.id, p.full_name
                     HAVING children_count > 0
                     ORDER BY p.generation_level, p.full_name");
$families = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إحصائيات العائلة</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .main-content {
            flex: 1;
            padding: 30px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
        }

        .btn-back {
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary);
            border: 1px solid rgba(191, 169, 138, 0.5);
        }

        .btn-back:hover {
            background: #fff;
            border-color: var(--line);
            transform: translateY(-2px);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                        0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--line);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-box {
            background: linear-gradient(135deg, rgba(60, 47, 47, 0.1) 0%, rgba(196, 167, 125, 0.1) 100%);
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid rgba(196, 167, 125, 0.2);
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(60, 47, 47, 0.15);
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }

        .stat-label {
            font-size: 16px;
            color: #6b543f;
            font-weight: 600;
        }

        .stat-icon {
            font-size: 32px;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            overflow: hidden;
        }

        thead {
            background: var(--primary);
            color: var(--accent);
        }

        thead th {
            padding: 15px;
            text-align: right;
            font-weight: 700;
            font-size: 16px;
        }

        tbody tr {
            border-bottom: 1px solid rgba(196, 167, 125, 0.2);
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.9);
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody td {
            padding: 15px;
            color: #6b543f;
        }

        tbody td:first-child {
            font-weight: 700;
            color: var(--primary);
        }

        tbody td:not(:first-child) {
            text-align: center;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #6b543f;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-number {
                font-size: 36px;
            }

            .table-container {
                overflow-x: scroll;
            }

            table {
                min-width: 600px;
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

    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-chart-bar"></i> إحصائيات العائلة</h1>
                <a href="dashboard_new.php" class="btn-back">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>

            <!-- الإحصائيات العامة -->
            <div class="glass-card">
                <h2 class="section-title">الإحصائيات العامة</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <i class="fas fa-users stat-icon"></i>
                        <span class="stat-number"><?= $total ?></span>
                        <div class="stat-label">إجمالي الأفراد</div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-mars stat-icon"></i>
                        <span class="stat-number"><?= $males ?></span>
                        <div class="stat-label">الذكور</div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-venus stat-icon"></i>
                        <span class="stat-number"><?= $females ?></span>
                        <div class="stat-label">الإناث</div>
                    </div>
                </div>
            </div>

            <!-- إحصائيات الأسر -->
            <div class="glass-card">
                <h2 class="section-title">إحصائيات الأسر</h2>
                <?php if (empty($families)): ?>
                    <div class="no-data">
                        <i class="fas fa-info-circle" style="font-size: 48px; color: var(--line); margin-bottom: 15px; display: block;"></i>
                        <p>لا توجد أسر مسجلة</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>اسم الأب</th>
                                    <th>عدد الأبناء</th>
                                    <th>الذكور</th>
                                    <th>الإناث</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($families as $family): ?>
                                    <tr>
                                        <td><?= h($family['full_name']) ?></td>
                                        <td><?= (int)$family['children_count'] ?></td>
                                        <td><?= (int)$family['sons'] ?></td>
                                        <td><?= (int)$family['daughters'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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