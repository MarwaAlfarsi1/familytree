<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/functions.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}

$results = [];
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['q'])) {
    $query = trim($_GET['q'] ?? $_POST['query'] ?? '');
    if (!empty($query)) {
        $results = searchPerson($pdo, $query);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>البحث عن فرد</title>
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
            max-width: 1000px;
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

        .search-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid rgba(191, 169, 138, 0.5);
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s;
            font-family: 'Cairo', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--line);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(196, 167, 125, 0.1);
        }

        .btn-search {
            background: var(--primary);
            color: var(--accent);
            padding: 14px 30px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(60, 47, 47, 0.3);
        }

        .results-header {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--line);
        }

        .results-count {
            font-size: 16px;
            font-weight: 400;
            color: #6b543f;
            margin-right: 10px;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #6b543f;
            font-size: 18px;
        }

        .result-item {
            background: rgba(255, 255, 255, 0.6);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(196, 167, 125, 0.3);
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .result-item:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 47, 47, 0.1);
        }

        .result-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .result-info {
            flex: 1;
            min-width: 250px;
        }

        .result-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .result-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
            color: #6b543f;
        }

        .result-detail-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .result-detail-item i {
            color: var(--accent);
        }

        .btn-view {
            background: #2563eb;
            color: #fff;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-view:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                min-width: 100%;
            }

            .result-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-view {
                width: 100%;
                justify-content: center;
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
                <h1><i class="fas fa-search"></i> البحث عن فرد</h1>
                <a href="dashboard_new.php" class="btn-back">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>

            <div class="glass-card">
                <form method="GET" class="search-form">
                    <input type="text" name="q" class="search-input" 
                           placeholder="ابحث بالاسم، رقم العضوية، أو اسم المستخدم..." 
                           value="<?= h($query) ?>" required>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </form>
            </div>

            <?php if (!empty($query)): ?>
                <div class="glass-card">
                    <h2 class="results-header">
                        نتائج البحث عن "<?= h($query) ?>"
                        <span class="results-count">(<?= count($results) ?> نتيجة)</span>
                    </h2>

                    <?php if (empty($results)): ?>
                        <div class="no-results">
                            <i class="fas fa-search" style="font-size: 48px; color: var(--line); margin-bottom: 15px; display: block;"></i>
                            <p>لم يتم العثور على نتائج</p>
                        </div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($results as $person): ?>
                                <div class="result-item">
                                    <div class="result-content">
                                        <div class="result-info">
                                            <div class="result-name"><?= h($person['full_name']) ?></div>
                                            <div class="result-details">
                                                <?php if (!empty($person['membership_number'])): ?>
                                                    <div class="result-detail-item">
                                                        <i class="fas fa-id-card"></i>
                                                        <span>رقم العضوية: <?= h($person['membership_number']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="result-detail-item">
                                                    <i class="fas fa-layer-group"></i>
                                                    <span>جيل <?= (int)$person['generation_level'] ?></span>
                                                </div>
                                                <div class="result-detail-item">
                                                    <i class="fas fa-<?= $person['gender'] === 'male' ? 'mars' : 'venus' ?>"></i>
                                                    <span><?= $person['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></span>
                                                </div>
                                                <?php if (!empty($person['residence_location'])): ?>
                                                    <div class="result-detail-item">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <span><?= h($person['residence_location']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="member_profile.php?id=<?= (int)$person['id'] ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> عرض التفاصيل
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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