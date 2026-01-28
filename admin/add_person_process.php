<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

function pickIntOrNull($v){
    if (!isset($v) || $v === '' || $v === null) return null;
    $x = (int)$v;
    return ($x > 0) ? $x : null;
}

$treeId = isset($_POST['tree_id']) ? (int)$_POST['tree_id'] : 0;
$fullName = trim($_POST['full_name'] ?? '');
$gender = $_POST['gender'] ?? '';
$birth = $_POST['birth_date'] ?? null;

$fatherId = pickIntOrNull($_POST['father_id'] ?? null);
$motherId = pickIntOrNull($_POST['mother_id'] ?? null);
$spouseId = pickIntOrNull($_POST['spouse_person_id'] ?? null);
$isRoot = isset($_POST['is_root']) ? (int)$_POST['is_root'] : 0;

if ($treeId <= 0) die("tree_id غير صحيح.");
if ($fullName === '') die("الاسم مطلوب.");
if (!in_array($gender, ['male','female'], true)) die("الجنس غير صحيح.");

// حساب generation_level تلقائياً: أعلى جيل من الأب/الأم + 1 وإلا 1
$gen = 1;
$parents = array_filter([$fatherId, $motherId]);
if (!empty($parents)) {
    $in = implode(',', array_fill(0, count($parents), '?'));
    $sql = "SELECT MAX(generation_level) AS mx FROM persons WHERE id IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($parents));
    $mx = (int)($stmt->fetchColumn() ?? 0);
    $gen = max(1, $mx + 1);
}

// إدراج
$stmt = $pdo->prepare("
    INSERT INTO persons
    (tree_id, full_name, gender, birth_date, father_id, mother_id, generation_level, is_root, spouse_person_id, spouse_is_external, external_tree_id)
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)
");
$stmt->execute([
    $treeId,
    $fullName,
    $gender,
    ($birth !== '' ? $birth : null),
    $fatherId,
    $motherId,
    $gen,
    ($isRoot === 1 ? 1 : 0),
    $spouseId
]);

$newId = (int)$pdo->lastInsertId();

// لو هو Root: نصفر أي Root قديم للشجرة ونحدّث trees.root_person_id
if ($isRoot === 1) {
    $pdo->prepare("UPDATE persons SET is_root=0 WHERE tree_id=? AND id<>?")->execute([$treeId, $newId]);
    $pdo->prepare("UPDATE persons SET is_root=1 WHERE id=?")->execute([$newId]);
    $pdo->prepare("UPDATE trees SET root_person_id=? WHERE id=?")->execute([$newId, $treeId]);
}

header("Location: view_tree_classic.php?tree_id=" . $treeId);
exit();
