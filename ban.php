<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

session_start();

if (!CONFIG["moderation"]["banfiles"] || !isset($_SESSION['is_moderator'])) {
    generate_alert(
        '/',
        "File ban is not allowed",
        403
    );
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    exit;
}

$file_id = $_POST['f'] ?? null;
$reason = $_POST['reason'] ?? null;

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
        "File not found",
        404
    );
    exit();
}

$file = File::load("$file_id.$file_ext");

if (!$file) {
    generate_alert(
        "/",
        "File not found",
        404
    );
    exit();
}

$file_sha = hash_file('sha256', $file->path);

if (!STORAGE->ban_file($file, $file_sha, $reason)) {
    generate_alert(
        "/$file_id.$file_ext",
        'Failed to remove files. Try again later',
        500
    );
    exit();
}

generate_alert(
    $_GET['r'] ?? '/',
    'Successfully banned the file',
    200,
    [
        'id' => $file_id,
        'extension' => $file_ext,
        'sha256' => $file_sha,
        'reason' => $reason
    ]
);