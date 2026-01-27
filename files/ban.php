<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

if (!USER->authorize_with_cookie()) {
    http_response_code(303);
    header('Location: /account/login.php');
    exit("You must be authorized!");
}

$redirect = urldecode($_GET['r'] ?? '%2F');

if ($_SESSION['user']->role->as_value() < UserRole::Moderator->as_value()) {
    generate_alert('/', 'You are not a moderator!', 401);
}

if (!CONFIG["moderation"]["banfiles"]) {
    generate_alert(
        $redirect,
        "File ban is not allowed",
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_alert(
        $redirect,
        "Method Not Allowed",
        405
    );
}

$file_id = $_POST['id'] ?? null;
$reason = $_POST['reason'] ?? null;

if (!isset($file_id)) {
    generate_alert(
        $redirect,
        "File ID must be set!",
        400
    );
    exit();
}

$file = File::load($file_id);

if (!$file) {
    generate_alert(
        $redirect,
        "File not found",
        404
    );
}

$file_sha = hash_file('sha256', $file->path);

if (!STORAGE->ban_file($file, $file_sha, $reason)) {
    generate_alert(
        $redirect,
        'Failed to remove files. Try again later',
        500
    );
}

generate_alert(
    $redirect,
    'Successfully banned the file',
    200,
    [
        'id' => $file_id,
        'sha256' => $file_sha,
        'reason' => $reason
    ]
);