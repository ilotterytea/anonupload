<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

$redirect = urldecode($_GET['r'] ?? '%2F');

if (!CONFIG["files"]["deletion"]) {
    generate_alert($redirect, 'File deletion is not allowed', 403);
}

if (!isset($_GET['id'])) {
    generate_alert($redirect, 'File ID must be set!', 400);
}

$file = File::load($_GET['id']);
if ($file === null) {
    generate_alert($redirect, 'File not found', 404);
}

// authorizing the user
USER->authorize_with_cookie();
$is_moderator = isset($_SESSION['user']) && $_SESSION['user']->role->as_value() >= UserRole::Moderator->as_value();

if (!$is_moderator) {
    if (!isset($file->password)) {
        generate_alert(
            $redirect,
            'This file cannot be deleted!',
            403
        );
    } elseif (!isset($_GET['key'])) {
        generate_alert(
            $redirect,
            'File key must be provided!',
            403
        );
    } elseif (!password_verify($_GET['key'], $file->password)) {
        generate_alert(
            $redirect,
            'Unauthorized',
            401
        );
    }
}

if (!STORAGE->delete_file($file)) {
    generate_alert(
        $redirect,
        'Failed to remove files. Try again later',
        500
    );
}

generate_alert(
    $redirect,
    'Successfully deleted the file',
    200,
    [
        'id' => $_GET['id'],
    ]
);