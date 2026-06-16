<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/registry.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/storage.php';

$post = null;

// -- retrieving file
$file_name_specified = isset($_GET['i']) || isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/';

try {
    // retrieving random file
    if (CONFIG['surpriseme']['enable'] && isset($_GET['random'])) {
        $post = FILEREGISTRY->get_random_post();
    } elseif ($file_name_specified) {
        $file_name = basename($_GET['i'] ?? $_SERVER['REQUEST_URI']);
        $post = FILEREGISTRY->get_post($file_name);

        if ($post) {
            foreach ($post->attachments as &$file) {
                if (!FILESTORAGE->ensure_file($file)) {
                    $post = null;
                    break;
                }
            }
            unset($file);
        }
    }
} catch (Exception $e) {
    $error = "500 {$e->getMessage()}";
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/error.php';
    die();
}

if ($post) {
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/file.php';
} elseif ($file_name_specified) {
    $error = '404 Not Found';
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/error.php';
} else {
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/home.php';
}