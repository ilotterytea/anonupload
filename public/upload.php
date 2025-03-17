<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if (!isset($_FILES['file'])) {
    http_response_code(400);
    exit("No 'file' specified!");
}

if (!is_dir(FILE_DIRECTORY) && !mkdir(FILE_DIRECTORY, 0777, true)) {
    http_response_code(500);
    exit("Failed to create a directory for user files");
}

$file = $_FILES['file'];

if (!move_uploaded_file($file['tmp_name'], FILE_DIRECTORY . sprintf('/%s', $file['name']))) {
    http_response_code(500);
    exit("Failed to save the file. Try again later.");
}

header(sprintf('Location: /%s', $file['name']));