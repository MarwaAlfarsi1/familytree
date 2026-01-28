<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// إنشاء مجلد رفع الصور تلقائياً بصلاحيات صحيحة
$uploadDir = __DIR__ . '/uploads/persons/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die('فشل إنشاء مجلد رفع الصور. يرجى إنشاء المجلد يدوياً: admin/uploads/persons/');
    }
    @chmod($uploadDir, 0755);
}

// التحقق من أن المجلد قابل للكتابة
if (!is_writable($uploadDir)) {
    @chmod($uploadDir, 0755);
    if (!is_writable($uploadDir)) {
        @chmod($uploadDir, 0777);
    }
}

$isAdmin = isset($_SESSION['admin_id']);
$isMember = isset($_SESSION['member_id']);

if (!$isAdmin && !$isMember) {
    header("Location: auth/login.php");
    exit();
}

$personId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($isMember && !$isAdmin) {
    $personId = (int)$_SESSION['member_id'];
}

if ($personId <= 0) {
    die('معرف غير صحيح');
}

$message = '';
$error = '';

// جلب بيانات الشخص
$stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ?");
$stmt->execute([$personId]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$person) {
    die('الشخص غير موجود');
}

// التحقق من الصلاحيات
if ($isMember && !$isAdmin && (int)$person['id'] !== (int)$_SESSION['member_id']) {
    die('ليس لديك صلاحية لتعديل هذا الملف الشخصي');
}

// التحقق من وجود حقول tribe, phone_number, birth_place
$tribeColumnExists = false;
$phoneColumnExists = false;
$birthPlaceColumnExists = false;

try {
    $checkTribe = $pdo->query("SHOW COLUMNS FROM persons LIKE 'tribe'");
    $tribeColumnExists = $checkTribe->rowCount() > 0;
} catch (Exception $e) {
    $tribeColumnExists = false;
}

try {
    $checkPhone = $pdo->query("SHOW COLUMNS FROM persons LIKE 'phone_number'");
    $phoneColumnExists = $checkPhone->rowCount() > 0;
} catch (Exception $e) {
    $phoneColumnExists = false;
}

try {
    $checkBirthPlace = $pdo->query("SHOW COLUMNS FROM persons LIKE 'birth_place'");
    $birthPlaceColumnExists = $checkBirthPlace->rowCount() > 0;
} catch (Exception $e) {
    $birthPlaceColumnExists = false;
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $birthPlace = trim($_POST['birth_place'] ?? '');
    $deathDate = !empty($_POST['death_date']) ? $_POST['death_date'] : null;
    $residence = trim($_POST['residence_location'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $tribe = trim($_POST['tribe'] ?? '');
    
    if (empty($fullName)) {
        $error = 'الاسم الكامل مطلوب';
    } else {
        // معالجة رفع الصورة
        $photoPath = $person['photo_path'];
        
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExt, $allowedExts)) {
                if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                    $error = 'حجم الصورة كبير جداً. الحد الأقصى 5MB';
                } else {
                    $fileName = uniqid('person_', true) . '.' . $fileExt;
                    $targetPath = $uploadDir . $fileName;
                    
                    // حذف الصورة القديمة إذا كانت موجودة
                    if (!empty($person['photo_path'])) {
                        $oldPhotoPath = __DIR__ . '/' . str_replace('admin/', '', $person['photo_path']);
                        if (file_exists($oldPhotoPath)) {
                            @unlink($oldPhotoPath);
                        }
                    }
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                        $photoPath = 'admin/uploads/persons/' . $fileName;
                        @chmod($targetPath, 0644);
                    } else {
                        $error = 'فشل رفع الصورة. تحقق من صلاحيات المجلد. الخطأ: ' . $_FILES['photo']['error'];
                    }
                }
            } else {
                $error = 'نوع الملف غير مدعوم. استخدم: jpg, jpeg, png, gif, webp';
            }
        }
        
        if (empty($error)) {
            // بناء استعلام UPDATE ديناميكي حسب الحقول المتاحة
            $updateFields = [];
            $updateValues = [];
            
            $updateFields[] = "full_name = ?";
            $updateValues[] = $fullName;
            
            $updateFields[] = "birth_date = ?";
            $updateValues[] = $birthDate;
            
            if ($birthPlaceColumnExists) {
                $updateFields[] = "birth_place = ?";
                $updateValues[] = $birthPlace;
            }
            
            $updateFields[] = "death_date = ?";
            $updateValues[] = $deathDate;
            
            $updateFields[] = "residence_location = ?";
            $updateValues[] = $residence;
            
            if ($phoneColumnExists) {
                $updateFields[] = "phone_number = ?";
                $updateValues[] = $phoneNumber;
            }
            
            $updateFields[] = "notes = ?";
            $updateValues[] = $notes;
            
            if ($tribeColumnExists) {
                $updateFields[] = "tribe = ?";
                $updateValues[] = $tribe;
            }
            
            $updateFields[] = "photo_path = ?";
            $updateValues[] = $photoPath;
            
            $updateValues[] = $personId;
            
            $updateSql = "UPDATE persons SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateValues);
            
            $message = 'تم تحديث الملف الشخصي بنجاح';
            
            // إعادة جلب البيانات المحدثة
            $stmt = $pdo->prepare("SELECT * FROM persons WHERE id = ?");
            $stmt->execute([$personId]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// دالة بناء الاسم الكامل مع القبيلة
function buildFullName($pdo, $person) {
    $name = htmlspecialchars($person['full_name']);
    $parts = [];
    
    // جلب اسم الأب
    if (!empty($person['father_id'])) {
        $fatherStmt = $pdo->prepare("SELECT full_name, father_id FROM persons WHERE id = ?");
        $fatherStmt->execute([$person['father_id']]);
        $father = $fatherStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($father) {
            $fatherName = htmlspecialchars($father['full_name']);
            
            // جلب اسم الجد (أب الأب)
            if (!empty($father['father_id'])) {
                $grandfatherStmt = $pdo->prepare("SELECT full_name FROM persons WHERE id = ?");
                $grandfatherStmt->execute([$father['father_id']]);
                $grandfather = $grandfatherStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($grandfather) {
                    $grandfatherName = htmlspecialchars($grandfather['full_name']);
                    if ($person['gender'] === 'female') {
                        $parts[] = $name . ' بنت ' . $fatherName . ' بن ' . $grandfatherName;
                    } else {
                        $parts[] = $name . ' بن ' . $fatherName . ' بن ' . $grandfatherName;
                    }
                } else {
                    if ($person['gender'] === 'female') {
                        $parts[] = $name . ' بنت ' . $fatherName;
                    } else {
                        $parts[] = $name . ' بن ' . $fatherName;
                    }
                }
            } else {
                if ($person['gender'] === 'female') {
                    $parts[] = $name . ' بنت ' . $fatherName;
                } else {
                    $parts[] = $name . ' بن ' . $fatherName;
                }
            }
        } else {
            $parts[] = $name;
        }
    } else {
        $parts[] = $name;
    }
    
    // جلب القبيلة
    $tribe = null;
    if (isset($person['tribe']) && !empty($person['tribe'])) {
        $tribe = htmlspecialchars($person['tribe']);
    } else {
        // البحث في الملاحظات إذا لم يكن حقل tribe موجوداً
        if (!empty($person['notes'])) {
            if (preg_match('/(?:قبيلة|القبيلة)[\s:]+([^\n\r]+)/i', $person['notes'], $matches)) {
                $tribe = trim($matches[1]);
            }
        }
    }
    
    // إضافة القبيلة إذا كانت موجودة
    if ($tribe) {
        $parts[] = 'والقبيلة ' . $tribe;
    }
    
    return implode(' ', $parts);
}

$fullDisplayName = buildFullName($pdo, $person);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الملف الشخصي - <?= htmlspecialchars($person['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/common.css">
    <style>
        * {
            box-sizing: border-box;
        }
        
        .profile-edit-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary, #333);
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color, #ddd);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Cairo', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color, #8B4513);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .membership-display {
            background: rgba(139, 69, 19, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 2px solid rgba(139, 69, 19, 0.3);
        }
        
        .membership-display.no-membership {
            background: rgba(255, 0, 0, 0.1);
            border-color: rgba(211, 47, 47, 0.3);
        }
        
        .membership-display label {
            color: #8B4513;
            font-weight: 700;
        }
        
        .membership-display.no-membership label {
            color: #d32f2f;
        }
        
        .membership-display p {
            font-size: 1.2rem;
            font-weight: 600;
            color: #8B4513;
            margin-top: 0.5rem;
            margin-bottom: 0;
        }
        
        .membership-display.no-membership p {
            color: #d32f2f;
            font-size: 1rem;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color, #8B4513);
            margin-bottom: 1rem;
            display: block;
        }
        
        .photo-upload {
            margin-top: 1rem;
        }
        
        .photo-upload input[type="file"] {
            padding: 0.5rem;
            border: 2px dashed var(--border-color, #ddd);
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
        }
        
        .photo-upload input[type="file"]:hover {
            border-color: var(--primary-color, #8B4513);
        }
        
        .buttons-group {
            display: flex !important;
            gap: 1rem !important;
            margin-top: 2rem !important;
            margin-bottom: 2rem !important;
            flex-wrap: wrap !important;
            width: 100% !important;
            clear: both !important;
        }
        
        .btn-submit {
            background: #8B4513 !important;
            color: white !important;
            padding: 0.75rem 2rem !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: background 0.3s !important;
            font-family: 'Cairo', sans-serif !important;
            display: inline-block !important;
            text-decoration: none !important;
            min-width: 180px !important;
        }
        
        .btn-submit:hover {
            background: #654321 !important;
        }
        
        .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            padding: 0.75rem 2rem !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: background 0.3s !important;
            text-decoration: none !important;
            display: inline-block !important;
            font-family: 'Cairo', sans-serif !important;
            text-align: center !important;
            min-width: 180px !important;
        }
        
        .btn-secondary:hover {
            background: #5a6268 !important;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group small {
            color: #666;
            margin-top: 0.5rem;
            display: block;
        }
        
        @media (max-width: 768px) {
            .profile-edit-container {
                padding: 0 0.5rem;
            }
            
            .buttons-group {
                flex-direction: column !important;
            }
            
            .btn-submit,
            .btn-secondary {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="main-container">
        <div class="profile-edit-container">
            <div class="glass-card">
                <h2><i class="fas fa-user-edit"></i> تعديل الملف الشخصي</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <!-- عرض رقم العضوية -->
                <div class="membership-display <?= empty($person['membership_number']) ? 'no-membership' : '' ?>">
                    <label><i class="fas fa-id-card"></i> رقم العضوية</label>
                    <p>
                        <?php if (!empty($person['membership_number'])): ?>
                            <?= htmlspecialchars($person['membership_number']) ?>
                        <?php else: ?>
                            غير محدد
                        <?php endif; ?>
                    </p>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> الاسم الكامل</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($person['full_name']) ?>" required>
                        <small>الاسم الكامل: <strong><?= $fullDisplayName ?></strong></small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> الصورة الشخصية</label>
                        <?php if (!empty($person['photo_path'])): ?>
                            <?php 
                            $photoUrl = '../' . $person['photo_path'];
                            $photoFullPath = __DIR__ . '/' . str_replace('admin/', '', $person['photo_path']);
                            if (file_exists($photoFullPath)) {
                                echo '<img src="' . htmlspecialchars($photoUrl) . '" alt="صورة ' . htmlspecialchars($person['full_name']) . '" class="photo-preview">';
                            } else {
                                echo '<p style="color: #999;">الصورة غير موجودة في المسار المحدد</p>';
                            }
                            ?>
                        <?php else: ?>
                            <p style="color: #999;">لا توجد صورة حالياً</p>
                        <?php endif; ?>
                        <div class="photo-upload">
                            <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <small>الحجم الأقصى: 5MB | الأنواع المدعومة: JPG, PNG, GIF, WEBP</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-birthday-cake"></i> تاريخ الميلاد</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($person['birth_date'] ?? '') ?>">
                    </div>
                    
                    <?php if ($birthPlaceColumnExists): ?>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> مكان الميلاد</label>
                        <input type="text" name="birth_place" value="<?= htmlspecialchars($person['birth_place'] ?? '') ?>" placeholder="مثال: مسقط، سلطنة عمان">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label><i class="fas fa-heart-broken"></i> تاريخ الوفاة</label>
                        <input type="date" name="death_date" value="<?= htmlspecialchars($person['death_date'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-home"></i> مكان الإقامة</label>
                        <input type="text" name="residence_location" value="<?= htmlspecialchars($person['residence_location'] ?? '') ?>">
                    </div>
                    
                    <?php if ($phoneColumnExists): ?>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" name="phone_number" value="<?= htmlspecialchars($person['phone_number'] ?? '') ?>" placeholder="مثال: +968 1234 5678">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tribeColumnExists): ?>
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> القبيلة</label>
                        <input type="text" name="tribe" value="<?= htmlspecialchars($person['tribe'] ?? '') ?>" placeholder="اسم القبيلة">
                        <small>سيتم عرض القبيلة تلقائياً في الاسم الكامل</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> الملاحظات</label>
                        <textarea name="notes"><?= htmlspecialchars($person['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="buttons-group">
                        <button type="submit" class="btn-submit" id="saveButton">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                        
                        <a href="member_profile.php?id=<?= $personId ?>" class="btn-secondary">
                            <i class="fas fa-arrow-right"></i> العودة للملف الشخصي
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
    
    <script>
        // التأكد من ظهور زر الحفظ
        document.addEventListener('DOMContentLoaded', function() {
            var saveButton = document.getElementById('saveButton');
            if (saveButton) {
                saveButton.style.display = 'inline-block';
                saveButton.style.visibility = 'visible';
                saveButton.style.opacity = '1';
            }
            
            // التأكد من أن الأزرار مرئية
            var buttonsGroup = document.querySelector('.buttons-group');
            if (buttonsGroup) {
                buttonsGroup.style.display = 'flex';
                buttonsGroup.style.visibility = 'visible';
            }
        });
    </script>
</body>
</html>