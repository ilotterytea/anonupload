<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

if (!isset($_FILES['file'])) {
    json_response(null, "No 'file' specified!", 400);
    exit();
}

if (!is_dir(FILE_DIRECTORY) && !mkdir(FILE_DIRECTORY, 0777, true)) {
    json_response(null, 'Failed to create a directory for user files', 500);
    exit();
}

$file = $_FILES['file'];

if (!move_uploaded_file($file['tmp_name'], FILE_DIRECTORY . sprintf('/%s', $file['name']))) {
    json_response(null, 'Failed to save the file. Try again later.', 500);
    exit();
}

json_response([
    'id' => $file['name']
], null, 201);