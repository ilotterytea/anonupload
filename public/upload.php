<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    json_response(null, 'Method not allowed', 405);
    exit;
}

if (!isset($_FILES['file'])) {
    json_response(null, "No 'file' specified!", 400);
    exit();
}

if (!is_dir(FILE_DIRECTORY) && !mkdir(FILE_DIRECTORY, 0777, true)) {
    json_response(null, 'Failed to create a directory for user files', 500);
    exit();
}

try {
    $file = $_FILES['file'];

    if (
        !isset($file['error']) ||
        is_array($file['error'])
    ) {
        throw new RuntimeException('Invalid parameters.');
    }

    // checking file size
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    // checking file mimetype
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (false === $file_ext = array_search($finfo->file($file['tmp_name']), FILE_ACCEPTED_MIME_TYPES, true)) {
        throw new RuntimeException("Invalid file format.");
    }

    $file_id = generate_random_char_sequence(FILE_ID_CHARACTERS, FILE_ID_LENGTH);

    if (!move_uploaded_file($file['tmp_name'], FILE_DIRECTORY . sprintf('/%s.%s', $file_id, $file_ext))) {
        throw new RuntimeException("Failed to save the file. Try again later.");
    }

    json_response([
        'id' => $file_id,
        'extension' => $file_ext,
        'mime' => FILE_ACCEPTED_MIME_TYPES[$file_ext],
        'size' => $file['size']
    ], null, 201);
} catch (RuntimeException $e) {
    json_response(null, $e->getMessage(), 400);
}