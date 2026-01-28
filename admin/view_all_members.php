<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}
require_once __DIR__ . "/../config/db.php";

// جلب جميع الأعضاء
$stmt = $pdo->query("SELECT * FROM persons ORDER BY full_name ASC");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE full_name LIKE ? OR membership_number LIKE ? ORDER BY full_name ASC");
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ملفات الأعضاء - شجرة العائلة</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/common.css">
<style>
:root {
    --primary-dark: #3c2f2f;
    --primary-gold: #f2c200;
    --secondary-brown: #6b543f;
    --light-gold: #c7a56b;
    --bg-light: #f5efe3;
    --bg-lighter: #e8ddd0;
    --white: #ffffff;
}

body {
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-lighter) 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding: 0;
    position: relative;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(242, 194, 0, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(199, 165, 107, 0.08) 0%, transparent 50%);
    z-index: 0;
    pointer-events: none;
}

.main-container {
    flex: 1;
    padding: 30px 20px;
    position: relative;
    z-index: 1;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}

.page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 40px rgba(60, 47, 47, 0.12);
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: clamp(24px, 4vw, 32px);
    color: var(--primary-dark);
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header h1 i {
    color: var(--primary-gold);
}

.search-box {
    position: relative;
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    padding: 14px 50px 14px 20px;
    border-radius: 12px;
    border: 1.5px solid rgba(191, 169, 138, 0.4);
    font-size: 16px;
    background: rgba(255, 255, 255, 0.95);
    transition: all 0.3s ease;
    font-family: 'Cairo', sans-serif;
}

.search-box input:focus {
    outline: none;
    border-color: var(--light-gold);
    box-shadow: 0 0 0 4px rgba(199, 165, 107, 0.08);
}

.search-box i {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary-brown);
    font-size: 18px;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.member-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 40px rgba(60, 47, 47, 0.12);
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: block;
}

.member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(60, 47, 47, 0.2);
    text-decoration: none;
    color: inherit;
}

.member-photo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--primary-gold);
    margin: 0 auto 15px;
    display: block;
}

.member-name {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-dark);
    text-align: center;
    margin-bottom: 10px;
}

.member-details {
    text-align: center;
    color: var(--secondary-brown);
    font-size: 14px;
    margin-bottom: 15px;
}

.member-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.member-actions .btn {
    flex: 1;
    padding: 10px;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(60, 47, 47, 0.12);
}

.empty-state i {
    font-size: 64px;
    color: var(--light-gold);
    margin-bottom: 20px;
}

.empty-state p {
    font-size: 18px;
    color: var(--secondary-brown);
}

@media (max-width: 768px) {
    .members-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        padding: 20px;
    }
}
</style>
</head>
<body>

<?php include 'nav.php'; ?>

<div class="main-container">
    <div class="page-header">
        <h1>
            <i class="fas fa-address-book"></i>
            ملفات الأعضاء
        </h1>
        
        <form method="GET" class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="ابحث عن عضو بالاسم أو رقم العضوية..." value="<?= htmlspecialchars($search) ?>">
        </form>
        
        <div style="text-align: center; color: var(--secondary-brown); font-size: 14px;">
            <i class="fas fa-info-circle"></i>
            إجمالي الأعضاء: <?= count($members) ?>
        </div>
    </div>

    <?php if (empty($members)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <p>لا يوجد أعضاء مسجلين</p>
        </div>
    <?php else: ?>
        <div class="members-grid">
            <?php foreach ($members as $member): ?>
                <a href="member_profile.php?id=<?= $member['id'] ?>" class="member-card">
                    <?php if (!empty($member['photo_path']) && file_exists($member['photo_path'])): ?>
                        <img src="<?= htmlspecialchars($member['photo_path']) ?>" alt="<?= h($member['full_name']) ?>" class="member-photo">
                    <?php else: ?>
                        <div class="member-photo" style="background: linear-gradient(135deg, var(--primary-gold) 0%, var(--light-gold) 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user" style="font-size: 48px; color: var(--primary-dark);"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="member-name"><?= h($member['full_name']) ?></div>
                    
                    <div class="member-details">
                        <?php if (!empty($member['membership_number'])): ?>
                            <div style="margin-bottom: 5px;">
                                <i class="fas fa-id-card"></i>
                                رقم العضوية: <?= h($member['membership_number']) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <i class="fas fa-<?= $member['gender'] === 'male' ? 'mars' : 'venus' ?>"></i>
                            <?= $member['gender'] === 'male' ? 'ذكر' : 'أنثى' ?>
                        </div>
                        <?php if (!empty($member['residence_location'])): ?>
                            <div style="margin-top: 5px; font-size: 12px;">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= h($member['residence_location']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="member-actions">
                        <span class="btn btn-primary" style="pointer-events: none;">
                            <i class="fas fa-eye"></i>
                            عرض الملف
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>

</body>
</html>