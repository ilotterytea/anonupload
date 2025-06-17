<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

session_start();

if (!FILE_DELETION) {
    generate_alert(
        '/',
        "File deletion is not allowed",
        403
    );
    exit();
}

$file_id = $_GET['f'] ?? null;
$password = $_GET['key'] ?? null;

if (!isset($file_id)) {
    generate_alert(
        '/',
        "File ID must be set!",
        400
    );
    exit();
}

$file_id = explode('.', $file_id);
$file_ext = $file_id[1];
$file_id = $file_id[0];

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $file_id) || !preg_match('/^[a-zA-Z0-9]+$/', $file_ext)) {
    generate_alert(
        '/',
        "Invalid file ID or extension",
        400
    );
    exit();
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);
$stmt = $db->prepare('SELECT password FROM files WHERE id = ? AND extension = ?');
$stmt->execute([$file_id, $file_ext]);

$file = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$file) {
    generate_alert(
        "/",
        "File $file_id not found",
        404
    );
    exit();
}

if (!isset($file['password'])) {
    generate_alert(
        "/$file_id.$file_ext",
        "File $file_id does not have a password. File cannot be deleted!",
        400
    );
    exit();
}

if (!isset($_SESSION['is_moderator']) && !isset($password)) {
    generate_alert(
        "/$file_id.$file_ext",
        "Field 'key' must be set!",
        400
    );
    exit();
}

if (!isset($_SESSION['is_moderator']) && !password_verify($password, $file['password'])) {
    generate_alert(
        "/$file_id.$file_ext",
        'Unauthorized',
        401
    );
    exit();
}

if (!delete_file($file_id, $file_ext, $db)) {
    generate_alert(
        "/$file_id.$file_ext",
        'Failed to remove files. Try again later',
        500
    );
    exit();
}

generate_alert(
    $_GET['r'] ?? '/',
    'Successfully deleted the file',
    200,
    [
        'id' => $file_id,
        'extension' => $file_ext
    ]
);