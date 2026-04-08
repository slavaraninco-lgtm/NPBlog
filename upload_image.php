<?php
header('Content-Type: application/json');


if (!file_exists('data/uploads')) {
    mkdir('data/uploads', 0777, true);
}

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был загружен']);
    exit;
}

$file = $_FILES['image'];
$fileName = $file['name'];
$fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));


$allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}


$newFileName = uniqid() . '.' . $fileType;
$uploadPath = 'data/uploads/' . $newFileName;


if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode([
        'success' => true,
        'url' => '/data/uploads/' . $newFileName
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении файла']);
}