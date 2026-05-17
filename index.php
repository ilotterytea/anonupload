<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/storage.php';

$file = null;

// retrieving file
$file_name_specified = isset($_GET['i']) || isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/';
if ($file_name_specified) {
    $file_name = basename($_GET['i'] ?? $_SERVER['REQUEST_URI']);
    $file = FILESTORAGE->get_file($file_name);
}

if ($file) {
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/file.php';
} else {
    include $_SERVER['DOCUMENT_ROOT'] . '/lib/pages/home.php';
}