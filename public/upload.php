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

// checking file mimetype
$finfo = new finfo(FILEINFO_MIME_TYPE);
if (false === $file_ext = array_search($finfo->file($file['tmp_name']), FILE_ACCEPTED_MIME_TYPES, true)) {
    json_response(null, 'Invalid file format', 400);
    exit();
}

$file_id = generate_random_char_sequence(FILE_ID_CHARACTERS, FILE_ID_LENGTH);

if (!move_uploaded_file($file['tmp_name'], FILE_DIRECTORY . sprintf('/%s.%s', $file_id, $file_ext))) {
    json_response(null, 'Failed to save the file. Try again later.', 500);
    exit();
}

json_response([
    'id' => $file_id,
    'ext' => $file_ext,
    'mime' => FILE_ACCEPTED_MIME_TYPES[$file_ext]
], null, 201);