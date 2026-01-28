<?php
// =====================================================
// الدوال المساعدة للنظام
// =====================================================

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * توليد رقم عضوية تلقائي
 * يبدأ من 0001 للجد ويزيد تدريجياً (0001, 0002, 0003, ...)
 */
function generateMembershipNumber($pdo) {
    // الحصول على آخر رقم عضوية
    $stmt = $pdo->query("SELECT membership_number FROM persons WHERE membership_number IS NOT NULL AND membership_number != '' ORDER BY CAST(membership_number AS UNSIGNED) DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last && !empty($last['membership_number'])) {
        // إزالة الأصفار من البداية للحصول على الرقم الفعلي
        $lastNumber = (int)$last['membership_number'];
        // زيادة الرقم
        $nextNumber = $lastNumber + 1;
    } else {
        // البدء من 1
        $nextNumber = 1;
    }
    
    // تنسيق الرقم بأربعة أرقام مع أصفار في البداية (0001, 0002, ...)
    return str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * التحقق من اسم المستخدم وكلمة المرور للعضو
 */
function verifyMemberLogin($pdo, $username, $password) {
    // تنظيف المدخلات
    $username = trim($username);
    $password = trim($password);
    
    if (empty($username) || empty($password)) {
        return false;
    }
    
    // البحث عن العضو
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE username = ? AND username IS NOT NULL AND username != '' LIMIT 1");
    $stmt->execute([$username]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        return false;
    }
    
    // التحقق من وجود كلمة المرور المشفرة
    if (empty($member['password_hash']) || trim($member['password_hash']) === '') {
        return false;
    }
    
    // التحقق من كلمة المرور
    if (password_verify($password, $member['password_hash'])) {
        return $member;
    }
    
    return false;
}

/**
 * الحصول على إحصائيات العائلة
 */
function getFamilyStatistics($pdo) {
    $stats = [
        'total' => 0,
        'males' => 0,
        'females' => 0,
        'families' => []
    ];
    
    // إحصائيات عامة
    $stmt = $pdo->query("SELECT COUNT(*) as total, 
                                SUM(CASE WHEN gender='male' THEN 1 ELSE 0 END) as males,
                                SUM(CASE WHEN gender='female' THEN 1 ELSE 0 END) as females
                         FROM persons WHERE is_root=0");
    $general = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = (int)($general['total'] ?? 0);
    $stats['males'] = (int)($general['males'] ?? 0);
    $stats['females'] = (int)($general['females'] ?? 0);
    
    // إحصائيات لكل أسرة (كل أب وأطفاله)
    $stmt = $pdo->query("SELECT p.id, p.full_name, 
                                COUNT(c.id) as children_count,
                                SUM(CASE WHEN c.gender='male' THEN 1 ELSE 0 END) as sons,
                                SUM(CASE WHEN c.gender='female' THEN 1 ELSE 0 END) as daughters
                         FROM persons p
                         LEFT JOIN persons c ON c.father_id = p.id
                         WHERE p.gender='male' AND p.is_root=0
                         GROUP BY p.id, p.full_name
                         HAVING children_count > 0
                         ORDER BY p.generation_level, p.full_name");
    $stats['families'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

/**
 * البحث عن فرد في العائلة
 */
function searchPerson($pdo, $query) {
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare("SELECT * FROM persons 
                           WHERE full_name LIKE ? 
                           OR membership_number LIKE ?
                           OR username LIKE ?
                           ORDER BY generation_level, full_name
                           LIMIT 50");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

