<?php
header('Content-Type: application/json');

if (!file_exists('data/uploads')) {
    mkdir('data/uploads', 0777, true);
}

if (empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'Файлы не были загружены']);
    exit;
}

$files = $_FILES['image'];
$gridLayout = $_POST['gridLayout'] ?? '';
$width = intval($_POST['width']);
$widthUnit = $_POST['widthUnit'] ?? 'px';

$allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
$uploadedUrls = [];

foreach ($files['name'] as $i => $name) {
    $tmp_name = $files['tmp_name'][$i];
    if (!is_uploaded_file($tmp_name)) continue;

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) continue;

    $newFileName = uniqid() . '.' . $ext;
    $uploadPath = 'data/uploads/' . $newFileName;

    if (move_uploaded_file($tmp_name, $uploadPath)) {
        $uploadedUrls[] = '/data/uploads/' . $newFileName;
    }
}

if (count($uploadedUrls) === 0) {
    echo json_encode(['success' => false, 'error' => 'Не удалось загрузить ни одно изображение']);
    exit;
}

echo json_encode([
    'success' => true,
    'urls' => $uploadedUrls,
    'gridLayout' => $gridLayout
]);