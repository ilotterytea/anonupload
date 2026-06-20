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
        $get_thumbnail = false;
        $file_name = null;

        if (isset($_GET['i'])) {
            $file_name = basename($_GET['i']);
            $get_thumbnail = isset($_GET['thumbnail']);
        } else if ($path = parse_url($_SERVER['REQUEST_URI'])) {
            $file_name = basename($path['path']);
            $get_thumbnail = str_contains($path['query'], 'thumbnail');
        }

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

        // get thumbnail (old format support)
        if (isset($post) && $get_thumbnail) {
            $url = null;
            if ($s = $post->single_attachment()) {
                $url = $s->thumbnail_url();
            }

            if ($url) {
                http_response_code(303);
                header("Location: $url");
                die($url);
            } else {
                http_response_code(404);
                die();
            }
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