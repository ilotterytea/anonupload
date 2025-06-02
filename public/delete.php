<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

if (!FILE_DELETION) {
    json_response(null, 'File deletion is not allowed!', 403);
    exit();
}

$file_id = $_GET['f'] ?: null;
$password = $_GET['key'] ?: null;

if (!isset($file_id, $password)) {
    json_response(null, "Fields 'f' and 'key' must be set!", 400);
    exit();
}

$file_id = explode('.', $file_id);
$file_ext = $file_id[1];
$file_id = $file_id[0];

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $file_id) || !preg_match('/^[a-zA-Z0-9]+$/', $file_ext)) {
    json_response(null, "Invalid file ID or extension", 400);
    exit();
}

if (!is_file(FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_ext}")) {
    json_response(null, "File {$file_id} not found", 404);
    exit();
}

if (!is_file(FILE_METADATA_DIRECTORY . "/{$file_id}.metadata.json")) {
    json_response(null, "File metadata {$file_id} not found", 404);
    exit();
}

$metadata = json_decode(file_get_contents(FILE_METADATA_DIRECTORY . "/{$file_id}.metadata.json"), true);

if (!array_key_exists('password', $metadata)) {
    json_response(null, "File {$file_id} does not have a password. File cannot be deleted!", 400);
    exit();
}

if (!password_verify($password, $metadata['password'])) {
    json_response(null, "Bad password", 401);
    exit();
}

$paths = [
    FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_ext}",
    FILE_THUMBNAIL_DIRECTORY . "/{$file_id}.webp",
    FILE_METADATA_DIRECTORY . "/{$file_id}.metadata.json"
];

foreach ($paths as $path) {
    if (is_file($path) && !unlink($path)) {
        json_response(null, "Failed to delete a file ID {$file_id}", 500);
        exit();
    }
}

json_response(
    [
        'id' => $file_id,
        'extension' => $file_ext
    ],
    'Successfully deleted the file'
);