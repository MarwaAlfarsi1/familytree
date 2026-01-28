<?php
require_once '../config/db.php';

if (
    empty($_POST['parent_id']) ||
    empty($_POST['name']) ||
    empty($_POST['gender'])
) {
    die('بيانات غير مكتملة');
}

$parent_id = intval($_POST['parent_id']);
$name = trim($_POST['name']);
$gender = $_POST['gender'];

$stmt = $pdo->prepare("
    INSERT INTO persons (name, gender, parent_id)
    VALUES (?, ?, ?)
");
$stmt->execute([$name, $gender, $parent_id]);

header("Location: view_tree_classic.php");
exit;
