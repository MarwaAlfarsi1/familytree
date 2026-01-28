<?php
session_start();
require_once "../config/db.php";

// إظهار الأخطاء مؤقتاً للتشخيص (احذفيه لاحقاً بعد التأكد)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* جلب الجد (من جدول persons) */
$rootStmt = $pdo->query("SELECT * FROM persons WHERE is_root = 1 ORDER BY id ASC LIMIT 1");
$root = $rootStmt->fetch(PDO::FETCH_ASSOC);

if (!$root) {
    header("Location: add_root.php");
    exit;
}

/* جلب جميع الأفراد (من جدول persons) */
$membersStmt = $pdo->query("SELECT * FROM persons ORDER BY generation_level ASC, id ASC");
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

/* فحص وجود زوج خارجي للبنت */
function hasExternalSpouse($pdo, $wife_id) {
    $s = $pdo->prepare("SELECT id FROM persons WHERE id=? AND spouse_is_external=1 AND external_tree_id IS NOT NULL LIMIT 1");
    $s->execute([$wife_id]);
    return $s->rowCount() > 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إدارة أفراد العائلة</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#f5efe3] min-h-screen p-4 md:p-6">
    <?php 
    $navPath = __DIR__ . '/nav.php';
    if (file_exists($navPath)) {
        include $navPath;
    }
    ?>

<div class="max-w-5xl mx-auto">

  <div class="flex items-center justify-between gap-3 mb-6">
    <a href="dashboard_new.php" class="bg-gray-700 text-white px-4 py-2 rounded">رجوع</a>
    <a href="view_tree_classic.php" class="bg-amber-800 text-white px-4 py-2 rounded">عرض الشجرة</a>
  </div>

  <h1 class="text-2xl md:text-3xl font-bold mb-6 text-center">إدارة أفراد العائلة</h1>

  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
      <?= h($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
      <?= h($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <?php foreach ($members as $person): ?>
    <div class="bg-white bg-opacity-90 backdrop-blur-sm p-4 rounded-2xl shadow-lg mb-4 border border-white border-opacity-50">
      <div class="font-bold text-lg mb-3 flex flex-wrap items-center gap-2">
        <span><?= h($person['full_name']) ?></span>
        <?php if (!empty($person['membership_number'])): ?>
          <span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded">
            رقم العضوية: <?= h($person['membership_number']) ?>
          </span>
        <?php endif; ?>
        <span class="text-sm text-gray-500">
          (جيل <?= h($person['generation_level']) ?> – <?= ($person['gender']=='male') ? 'ذكر' : 'أنثى' ?>)
        </span>
      </div>
      
      <?php if (!empty($person['residence_location'])): ?>
        <div class="text-sm text-gray-600 mb-2">
          📍 مكان الإقامة: <?= h($person['residence_location']) ?>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($person['death_date'])): ?>
        <div class="text-sm text-red-600 mb-2">
          ⚰️ تاريخ الوفاة: <?= h($person['death_date']) ?>
        </div>
      <?php endif; ?>

      <div class="flex flex-wrap gap-2">

        <?php
          // مهم: إذا أنثى نمرر mother_id بدل father_id لتفادي أخطاء الإضافة
          $addChildUrl = ($person['gender'] === 'male')
              ? "person_add_child_new.php?father_id=".(int)$person['id']
              : "person_add_child_new.php?mother_id=".(int)$person['id'];
        ?>
        <a href="<?= $addChildUrl ?>"
           class="bg-green-700 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-green-800 transition">
           إضافة ابن / ابنة
        </a>

        <?php if (empty($person['spouse_person_id']) && empty($person['second_spouse_person_id'])): ?>
          <a href="person_add_spouse_internal_new.php?person_id=<?= (int)$person['id'] ?>"
             class="bg-blue-800 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-blue-900 transition">
             زواج داخل العائلة
          </a>
        <?php endif; ?>

        <?php if ($person['gender'] == 'female'): ?>
          <?php if (!hasExternalSpouse($pdo, $person['id']) && empty($person['spouse_person_id'])): ?>
            <a href="person_add_spouse_external_new.php?person_id=<?= (int)$person['id'] ?>"
               class="bg-pink-700 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-pink-800 transition">
               زواج من خارج العائلة
            </a>
          <?php elseif (hasExternalSpouse($pdo, $person['id'])): ?>
            <a href="external_family.php?wife_id=<?= (int)$person['id'] ?>"
               class="bg-emerald-800 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-emerald-900 transition">
               عرض أسرة الزوج + الأبناء
            </a>
          <?php endif; ?>
          
          <?php if (!empty($person['spouse_person_id']) && empty($person['second_spouse_person_id'])): ?>
            <a href="person_add_second_spouse.php?person_id=<?= (int)$person['id'] ?>"
               class="bg-purple-700 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-purple-800 transition">
               إضافة زوج ثاني
            </a>
          <?php endif; ?>
          
          <?php if (!empty($person['second_spouse_person_id'])): ?>
            <a href="person_add_child_second_spouse.php?person_id=<?= (int)$person['id'] ?>"
               class="bg-indigo-700 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-indigo-800 transition">
               إضافة ابن/ابنة من الزوج الثاني
            </a>
          <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($person['gender'] == 'male'): ?>
          <?php if (!hasExternalSpouse($pdo, $person['id']) && empty($person['spouse_person_id'])): ?>
            <a href="person_add_spouse_external_new.php?person_id=<?= (int)$person['id'] ?>"
               class="bg-pink-700 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-pink-800 transition">
               زواج من خارج العائلة
            </a>
          <?php elseif (hasExternalSpouse($pdo, $person['id'])): ?>
            <a href="external_family.php?husband_id=<?= (int)$person['id'] ?>"
               class="bg-emerald-800 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-emerald-900 transition">
               عرض أسرة الزوجة + الأبناء
            </a>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($person['is_root'])): ?>
          <a href="person_delete.php?id=<?= (int)$person['id'] ?>"
             class="bg-red-700 text-white px-3 py-2 rounded text-sm md:text-base hover:bg-red-800 transition"
             onclick="return confirm('هل أنت متأكد من حذف <?= htmlspecialchars($person['full_name'], ENT_QUOTES) ?>؟ سيتم حذف هذا الشخص وأطفاله أيضاً!')">
             حذف
          </a>
        <?php endif; ?>

      </div>
    </div>
  <?php endforeach; ?>

</div>

</body>
</html>
