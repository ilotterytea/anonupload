<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

if (!USER->authorize_with_cookie()) {
    generate_alert('/account/', 'You must be authorized!', 303);
}

if ($_SESSION['user']->role->as_value() < UserRole::Administrator->as_value()) {
    generate_alert('/account/', 'You are not allowed to make changes on this page!', 401);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
);

$missing_filenames = [];
$count = 0;

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = $file->getExtension();
        $name = $file->getBasename(".$ext");
        array_push($missing_filenames, $name);

        $f = File::load($file->getBasename());
        if ($f === null) {
            continue;
        }

        if (!STORAGE->save($f)) {
            throw new RuntimeException("Failed to save the file");
        }

        $count++;
    }
}

// checking for non-existent files
$nonexistent_files = [];

switch (STORAGE->get_type()) {
    case FileStorageType::Database:
        $file_ids = implode(',', array_map(fn($x) => "'" . addslashes($x) . "'", $missing_filenames));
        $db = STORAGE->get_db();
        $stmt = $db->prepare("SELECT id, extension FROM files
            WHERE
                id NOT IN (SELECT id FROM file_bans) AND
                id NOT IN ($file_ids)
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            array_push($nonexistent_files, "{$row['id']}.{$row['extension']}");
        }
        break;
    case FileStorageType::Json:
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(CONFIG['metadata']['directory'], FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = $file->getExtension();
                $name = $file->getBasename(".$ext");
                if ($ext !== "json" || in_array($name, $missing_filenames)) {
                    continue;
                }
                array_push($nonexistent_files, $file->getBasename());
            }
        }
        break;
    default:
        break;
}

foreach ($nonexistent_files as $file) {
    $f = File::load($file);
    if ($f === null) {
        return;
    }

    if (!STORAGE->delete_file($f)) {
        throw new RuntimeException("Failed to delete the file");
    }

    $count--;
}

generate_alert(
    "/system/",
    "Re-indexed $count files"
);