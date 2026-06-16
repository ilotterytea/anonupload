<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/storage.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/registry.php";

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    generate_alert('/', "Method not allowed", 405);
    exit;
}

$id = trim($_GET['id'] ?? '');
$key = trim($_GET['key'] ?? '');

if (empty($id) || empty($key)) {
    generate_alert('/', 'File ID and password must be set!', 400);
    exit();
}

$file = FILEREGISTRY->get_file($id);
if (!$file) {
    generate_alert('/', 'File does not exist or has been deleted', 404);
    exit();
}

if ($file->password === null || !password_verify($key, $file->password)) {
    generate_alert('/', 'Incorrect password or this file cannot be deleted', 400);
    exit();
}

if (!FILEREGISTRY->delete_post($file)) {
    generate_alert('/', 'Failed to delete the file. Try again later.', 500);
    exit();
}

generate_alert('/', "File {$file->id}.{$file->extension} has been deleted", 200, $file);

