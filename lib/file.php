<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function verify_mimetype(string $file_path, string $mimetype): bool
{
    $path = escapeshellarg($file_path);
    $output = [];
    $exitCode = 0;

    if (str_starts_with($mimetype, 'image/')) {
        $output = shell_exec("identify -quiet -ping $path");
        return !empty($output);
    } else if (str_starts_with($mimetype, 'video/') || str_starts_with($mimetype, 'audio/')) {
        $cmd = "ffprobe -v error -i $path 2>&1";
        exec($cmd, $output, $exitCode);
        return $exitCode === 0;
    } else if ($mimetype == 'application/x-shockwave-flash') {
        $cmd = "swfdump $path 2>&1";
        exec($cmd, $output, $exitCode);
        return $exitCode === 0;
    }

    throw new RuntimeException("Illegal type for MIME verifications: $mimetype");
}

function delete_file(string $file_id, string $file_extension, PDO|null $db = null): bool
{
    $paths = [
        FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_extension}",
        FILE_THUMBNAIL_DIRECTORY . "/{$file_id}.webp"
    ];

    foreach ($paths as $path) {
        if (is_file($path) && !unlink($path)) {
            return false;
        }
    }

    if ($db) {
        $db->prepare('DELETE FROM files WHERE id = ? AND extension = ?')->execute([$file_id, $file_extension]);
    }

    return true;
}

function strip_exif(string $file_path)
{
    $file_path = escapeshellarg($file_path);
    $output = shell_exec("exiftool -q -EXIF= $file_path $file_path");
    return empty($output);
}