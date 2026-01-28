<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login_username.php");
    exit();
}
require_once '../config/db.php';

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// معالجة البحث
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$members = [];

if (!empty($searchQuery)) {
    // البحث بالاسم أو رقم العضوية
    $searchTerm = '%' . $searchQuery . '%';
    $membersStmt = $pdo->prepare("SELECT * FROM persons 
                                  WHERE full_name LIKE ? 
                                  OR membership_number LIKE ?
                                  ORDER BY generation_level ASC, full_name ASC");
    $membersStmt->execute([$searchTerm, $searchTerm]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // جلب جميع الأعضاء
    $membersStmt = $pdo->query("SELECT * FROM persons ORDER BY generation_level ASC, full_name ASC");
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملفات الأعضاء</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/common.css">
    <style>
        .profiles-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            color: #3c2f2f;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stats-info {
            color: #666;
            font-size: 1rem;
        }
        
        .search-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-input-wrapper {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Cairo', sans-serif;
            transition: border-color 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #8B4513;
        }
        
        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .search-btn {
            background: #8B4513;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            font-family: 'Cairo', sans-serif;
        }
        
        .search-btn:hover {
            background: #654321;
        }
        
        .clear-search-btn {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            font-family: 'Cairo', sans-serif;
        }
        
        .clear-search-btn:hover {
            background: #5a6268;
        }
        
        .search-info {
            margin-top: 1rem;
            padding: 0.75rem;
            background: rgba(139, 69, 19, 0.1);
            border-radius: 8px;
            color: #8B4513;
            font-weight: 600;
        }
        
        .profiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .profile-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #8B4513;
            margin: 0 auto 1rem;
            display: block;
        }
        
        .profile-photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ddd 0%, #bbb 100%);
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            border: 3px solid #8B4513;
        }
        
        .profile-photo-placeholder i {
            font-size: 2.5rem;
        }
        
        .profile-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #3c2f2f;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .profile-info {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .profile-info-item {
            margin: 0.3rem 0;
        }
        
        .membership-badge {
            display: inline-block;
            background: rgba(139, 69, 19, 0.1);
            color: #8B4513;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .membership-badge.no-membership {
            background: rgba(255, 0, 0, 0.1);
            color: #d32f2f;
        }
        
        .profile-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .btn-view,
        .btn-edit {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-view {
            background: #8B4513;
            color: white;
        }
        
        .btn-view:hover {
            background: #654321;
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: #6c757d;
            color: white;
        }
        
        .btn-edit:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .back-button {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
        
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }
            
            .search-input-wrapper {
                width: 100%;
            }
            
            .search-btn,
            .clear-search-btn {
                width: 100%;
            }
            
            .profiles-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
            
            .profile-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="main-container">
        <div class="profiles-container">
            <a href="dashboard_new.php" class="back-button">
                <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
            </a>
            
            <div class="glass-card">
                <div class="page-header">
                    <h2><i class="fas fa-id-card"></i> ملفات الأعضاء</h2>
                    <p class="stats-info">
                        إجمالي الأعضاء: <strong><?= count($members) ?></strong>
                        <?php if (!empty($searchQuery)): ?>
                            <span style="color: #8B4513;">| نتائج البحث: <?= count($members) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- قسم البحث -->
            <div class="search-section">
                <form method="GET" action="" class="search-form">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="ابحث بالاسم أو رقم العضوية..." 
                            value="<?= htmlspecialchars($searchQuery) ?>"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="member_profiles_list.php" class="clear-search-btn">
                            <i class="fas fa-times"></i> إلغاء البحث
                        </a>
                    <?php endif; ?>
                </form>
                
                <?php if (!empty($searchQuery)): ?>
                    <div class="search-info">
                        <i class="fas fa-info-circle"></i>
                        نتائج البحث عن: "<strong><?= htmlspecialchars($searchQuery) ?></strong>"
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($members)): ?>
                <div class="glass-card" style="text-align: center; padding: 3rem;">
                    <?php if (!empty($searchQuery)): ?>
                        <p style="color: #666; font-size: 1.1rem;">
                            <i class="fas fa-search"></i> لم يتم العثور على نتائج للبحث: "<strong><?= htmlspecialchars($searchQuery) ?></strong>"
                        </p>
                        <a href="member_profiles_list.php" class="btn-view" style="display: inline-block; margin-top: 1rem;">
                            <i class="fas fa-list"></i> عرض جميع الأعضاء
                        </a>
                    <?php else: ?>
                        <p style="color: #666; font-size: 1.1rem;">لا يوجد أعضاء مسجلين بعد</p>
                        <a href="manage_people_new.php" class="btn-view" style="display: inline-block; margin-top: 1rem;">
                            <i class="fas fa-plus"></i> إضافة عضو جديد
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="profiles-grid">
                    <?php foreach ($members as $member): ?>
                        <div class="profile-card">
                            <?php if (!empty($member['photo_path'])): ?>
                                <?php 
                                $photoUrl = '../' . $member['photo_path'];
                                $photoFullPath = __DIR__ . '/' . str_replace('admin/', '', $member['photo_path']);
                                if (file_exists($photoFullPath)) {
                                    echo '<img src="' . htmlspecialchars($photoUrl) . '" alt="' . htmlspecialchars($member['full_name']) . '" class="profile-photo">';
                                } else {
                                    echo '<div class="profile-photo-placeholder"><i class="fas fa-user"></i></div>';
                                }
                                ?>
                            <?php else: ?>
                                <div class="profile-photo-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="profile-name"><?= h($member['full_name']) ?></div>
                            
                            <div class="profile-info">
                                <div class="profile-info-item">
                                    جيل <?= (int)$member['generation_level'] ?> - 
                                    <?= $member['gender'] === 'male' ? 'ذكر' : 'أنثى' ?>
                                </div>
                                <?php if (!empty($member['membership_number'])): ?>
                                    <div class="membership-badge">
                                        <i class="fas fa-id-card"></i> رقم العضوية: <?= h($member['membership_number']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="membership-badge no-membership">
                                        <i class="fas fa-exclamation-circle"></i> غير محدد
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="profile-actions">
                                <a href="member_profile.php?id=<?= (int)$member['id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
                                <a href="edit_member_profile.php?id=<?= (int)$member['id'] ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
    
    <script>
        // البحث الفوري عند الكتابة (اختياري)
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            const searchForm = document.querySelector('.search-form');
            
            // البحث عند الضغط على Enter
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchForm.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>