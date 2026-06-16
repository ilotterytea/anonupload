<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/registry.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/storage.php';

$file = null;

// -- retrieving file
$file_name_specified = isset($_GET['i']) || isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/';

// retrieving random file
if (CONFIG['surpriseme']['enable'] && isset($_GET['random'])) {
    $file = FILEREGISTRY->get_random_file();
} elseif ($file_name_specified) {
    $file_name = basename($_GET['i'] ?? $_SERVER['REQUEST_URI']);
    $file = FILEREGISTRY->get_file_by_post_id($file_name);

    if ($file && !FILESTORAGE->ensure_file($file)) {
        $file = null;
    }
}

if ($file) {
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/file.php';
} elseif ($file_name_specified) {
    $error = '404 Not Found';
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/error.php';
} else {
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/home.php';
}