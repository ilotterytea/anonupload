<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

function is_swf_file($filename)
{
    if (!is_readable($filename)) {
        return false;
    }

    $fh = fopen($filename, 'rb');
    if (!$fh)
        return false;

    $header = fread($fh, 8);
    fclose($fh);

    if (strlen($header) < 8) {
        return false;
    }

    $signature = substr($header, 0, 3);
    $version = ord($header[3]);

    if (in_array($signature, ['FWS', 'CWS', 'ZWS'])) {
        if ($version >= 6 && $version <= 50) {
            return true;
        }
    }

    return false;
}

function parse_swf_file(string $file_path): array
{
    $fh = fopen($file_path, 'rb');
    if (!$fh) {
        throw new RuntimeException('Failed to open the file!');
    }

    $chunk = fread($fh, 512 * 1024);
    fclose($fh);

    if (strlen($chunk) < 9) {
        throw new RuntimeException('File too short.');
    }

    $signature = substr($chunk, 0, 3);
    if (!in_array($signature, ['FWS', 'CWS', 'ZWS'])) {
        throw new RuntimeException('Not a valid SWF.');
    }

    if ($signature === 'CWS') {
        $decompressed = gzuncompress(substr($chunk, 8));
        if ($decompressed === false) {
            throw new RuntimeException('Bad compressed SWF.');
        }
        $data = $decompressed;
    } else if ($signature === 'ZWS') {
        throw new RuntimeException('LZMA SWF is not supported');
    } else {
        $data = substr($chunk, 8);
    }

    $bits = ord($data[0]) >> 3;
    $bitstr = '';
    for ($i = 0; $i < ceil((5 + 4 * $bits) / 8); $i++) {
        $bitstr .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }

    $nbits = bindec(substr($bitstr, 0, 5));
    $pos = 5;
    $xmin = bindec(substr($bitstr, $pos, $nbits));
    $pos += $nbits;
    $xmax = bindec(substr($bitstr, $pos, $nbits));
    $pos += $nbits;
    $ymin = bindec(substr($bitstr, $pos, $nbits));
    $pos += $nbits;
    $ymax = bindec(substr($bitstr, $pos, $nbits));
    $pos += $nbits;

    $width = ($xmax - $xmin) / 20;
    $height = ($ymax - $ymin) / 20;

    return [$width, $height];
}

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
        return is_swf_file($file_path);
    }

    throw new RuntimeException("Illegal type for MIME verifications: $mimetype");
}

function delete_file(string $file_id, string $file_extension, PDO|null $db = null): bool
{
    $paths = [
        CONFIG["files"]["directory"] . "/{$file_id}.{$file_extension}",
        CONFIG["thumbnails"]["directory"] . "/{$file_id}.webp"
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

function remove_video_letterbox(string $input_path, string $output_path)
{
    $input_path = escapeshellarg($input_path);
    $output_path = escapeshellarg($output_path);
    $output = shell_exec("ffmpeg -nostdin -i $input_path -vf cropdetect=24:16:0 -f null - 2>&1");

    if (preg_match_all('/crop=\d+:\d+:\d+:\d+/', $output, $matches)) {
        $area = end($matches);
        $area = end($area);
    } else {
        throw new RuntimeException('Could not detect crop parameters. Try upload the video without "Remove letterbox" option.');
    }

    $area = escapeshellarg($area);

    $output = [];
    exec("ffmpeg -nostdin -i $input_path -vf $area -c:a copy $output_path 2>&1", $output, $code);

    return $code == 0;
}

function parse_zip_web_archive(string $input_path, string $output_path)
{
    $allowed_extensions = [
        "html",
        "js",
        "css",
        "png",
        "jpg",
        "jpeg",
        "gif",
        "mp3",
        "ogg",
        "wasm",
        "atlas",
        "skin",
        "txt",
        "fnt",
        "json",
        "glb",
        "glsl",
        "map",
        "teavmdbg",
        "xml",
        "ds_store",
    ];
    $max_total_uncompressed = 128 * 1024 * 1024;
    $max_file_size = 32 * 1024 * 1024;

    $zip = new ZipArchive();
    if ($zip->open($input_path) !== true) {
        throw new RuntimeException("Invalid ZIP");
    }

    $is_webapp = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $is_webapp = $stat["name"] == "index.html";
        if ($is_webapp) {
            break;
        }
    }

    if (!$is_webapp) {
        $zip->close();
        return $is_webapp;
    }

    $total_uncompressed = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $path = $stat["name"];
        if (strpos($path, "..") !== false) {
            throw new RuntimeException("Invalid file path");
        }

        if (
            strpos($path, "__MACOSX/") === 0 ||
            (basename($path)[0] === "." &&
                strstr(basename($path), 0, 2) === "._")
        ) {
            continue;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        error_log($ext);
        if (!in_array($ext, $allowed_extensions) && $stat["size"] > 0) {
            throw new RuntimeException(
                "Forbidden file type in the archive: $path",
            );
        }

        $total_uncompressed += $stat["size"];
        if (
            $total_uncompressed > $max_total_uncompressed ||
            $stat["size"] > $max_file_size
        ) {
            throw new RuntimeException("ZIP too large when uncompressed");
        }
    }

    mkdir($output_path, 0755, true);
    if (!$zip->extractTo($output_path)) {
        rmdir($output_path);
        throw new RuntimeException("ZIP extraction failed");
    }

    $zip->close();

    return $is_webapp;
}
