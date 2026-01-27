<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/thumbnails.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

session_start();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    generate_alert(
        '/',
        "Method not allowed",
        405,
        null
    );
    exit;
}

if (!is_dir(CONFIG["files"]["directory"]) && !mkdir(CONFIG["files"]["directory"], 0777, true)) {
    generate_alert(
        '/',
        "Failed to create a directory for user files",
        500,
        null
    );
    exit();
}

try {
    $preserve_original_name = boolval($_POST['preserve_original_name'] ?? '0');
    $title = str_safe($_POST['title'] ?? '', CONFIG["upload"]["titlelength"]);
    if (empty(trim($title))) {
        $title = null;
    }

    if (!isset($_FILES['file']) && isset($_POST['base64'])) {
        $_FILES['file'] = [
            'tmp_name' => '/tmp/php' . bin2hex(random_bytes(5)),
            'error' => UPLOAD_ERR_OK,
            'base64' => true
        ];

        $parts = explode(',', $_POST['base64']);
        $meta = $parts[0];
        if (!str_starts_with($meta, 'data:image/') || !str_ends_with($meta, ';base64')) {
            throw new RuntimeException("Invalid base64 data.");
        }

        $data = $parts[1];

        $ifp = fopen($_FILES['file']['tmp_name'], 'wb');

        fwrite($ifp, base64_decode($data));
        fclose($ifp);

        $_FILES['file']['size'] = filesize($_FILES['file']['tmp_name']);
    }

    $url = isset($_POST['url']) ? $_POST['url'] ?: null : null;
    $file = isset($_FILES['file']) ? $_FILES['file'] ?: null : null;
    if (empty($file['tmp_name'])) {
        $file = null;
    }
    $paste = isset($_POST['paste']) ? $_POST['paste'] ?: null : null;
    $password = $_POST['password'] ?? generate_random_char_sequence(CONFIG["upload"]["idcharacters"], CONFIG["files"]["deletionkeylength"]);
    $file_data = null;

    if (!(isset($file) ^ isset($url) ^ isset($paste)) || (isset($file) && isset($url) && isset($paste))) {
        throw new RuntimeException('You can upload only one type of content: file, URL or text');
    }

    if (CONFIG["externalupload"]["enable"] && isset($url) && !empty($url)) {
        $output = [];
        $CONFIG["externalupload"]["quality"] = CONFIG["externalupload"]["quality"] ? ('-f "' . CONFIG["externalupload"]["quality"] . '"') : "";
        exec('yt-dlp ' . $CONFIG["externalupload"]["quality"] . ' --get-filename -o "%(filesize_approx)s %(ext)s %(duration)s" ' . escapeshellarg($url) . '', $output);
        if (empty($output)) {
            throw new RuntimeException('Bad URL');
        }

        $output = explode(' ', $output[0]);

        // TODO: some videos don't have duration
        $duration = intval($output[2]);
        if ($duration > CONFIG["externalupload"]["maxduration"]) {
            throw new RuntimeException(sprintf("File must be under %d minutes", CONFIG["externalupload"]["maxduration"] / 60));
        }

        if (!array_key_exists($output[1], CONFIG["upload"]["acceptedmimetypes"])) {
            throw new RuntimeException("Unsupported extension: {$output[1]}");
        }

        $file_data = [
            'size' => intval($output[0]),
            'mime' => CONFIG["upload"]["acceptedmimetypes"][$output[1]],
            'extension' => $output[1]
        ];
    } else if (isset($paste)) {
        $file_data = [
            'size' => strlen($paste),
            'mime' => 'text/plain',
            'extension' => 'txt'
        ];
    } else if (isset($file)) {
        if (
            !isset($file['error']) ||
            is_array($file['error'])
        ) {
            throw new RuntimeException('Invalid parameters.');
        }

        // checking file error
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file sent.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Exceeded filesize limit.');
            default:
                throw new RuntimeException('Unknown errors.');
        }

        // checking file mimetype
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (false === $file_ext = array_search($finfo->file($file['tmp_name']), CONFIG["upload"]["acceptedmimetypes"], true)) {
            throw new RuntimeException("Invalid file format.");
        }

        // verifying file mimetype
        $file_mime = CONFIG["upload"]["acceptedmimetypes"][$file_ext];
        $is_media = str_starts_with($file_mime, 'image/') || str_starts_with($file_mime, 'video/') || str_starts_with($file_mime, 'audio/');
        if (CONFIG["upload"]["verifymimetype"] && $is_media && !verify_mimetype($file['tmp_name'], $file_mime)) {
            throw new RuntimeException('Invalid file format.');
        }

        // striping exif data
        if (CONFIG["upload"]["stripexif"] && $is_media && !strip_exif($file['tmp_name'])) {
            throw new RuntimeException('Failed to strip EXIF tags.');
        }

        $file_data = [
            'size' => $file['size'],
            'mime' => $file_mime,
            'extension' => $file_ext,
        ];
    }

    if (!$file_data) {
        throw new RuntimeException('No URL or file specified');
    }

    if (CONFIG["upload"]["customid"] && isset($_POST['id']) && !empty(trim($_POST['id']))) {
        $file_id = $_POST['id'];
        if (!preg_match(CONFIG["upload"]["customidregex"], $file_id) || strlen($file_id) > CONFIG["upload"]["customidlength"]) {
            throw new RuntimeException('Invalid file ID.');
        }

        if (STORAGE->has_id($file_id, $file_ext)) {
            throw new RuntimeException('File ID has already been taken.');
        }
    } else {
        $CONFIG["upload"]["idlength"] = CONFIG["upload"]["idlength"];
        $file_id_gen_attempts = 0;
        do {
            $file_id = CONFIG["upload"]["idprefix"] . generate_random_char_sequence(CONFIG["upload"]["idcharacters"], $CONFIG["upload"]["idlength"]);
            if ($file_id_gen_attempts > 20) {
                $CONFIG["upload"]["idlength"]++;
                $file_id_gen_attempts = 0;
            }
            $file_id_gen_attempts++;
        } while (STORAGE->has_id($file_id, $file_data['extension']));
    }

    $file_data['id'] = $file_id;

    $file_path = sprintf('%s/%s.%s', CONFIG["files"]["directory"], $file_id, $file_data['extension']);

    if (isset($url)) {
        $result = 0;
        $output = [];

        exec(sprintf(
            'yt-dlp %s -o "%s" %s 2>&1',
            CONFIG["externalupload"]["quality"] ? ('-f "' . CONFIG["externalupload"]["quality"] . '"') : "",
            $file_path,
            escapeshellarg($url)
        ), $output, $result);

        if ($result != 0) {
            error_log(sprintf("Failed to download a file (URL: %s): %s", $url, implode('\n', $output)));
            throw new RuntimeException('Failed to download a file! Try again later.');
        }

        // verifying file mime type
        $file_mime = $file_data['mime'];
        $is_media = str_starts_with($file_mime, 'image/') || str_starts_with($file_mime, 'video/') || str_starts_with($file_mime, 'audio/');
        if (CONFIG["upload"]["verifymimetype"] && $is_media && !verify_mimetype($file_path, $file_mime)) {
            STORAGE->delete_file_by_id($file_id, $file_data['extension']);
            throw new RuntimeException('Invalid file format.');
        }
    } else if (isset($paste) && !file_put_contents($file_path, $paste)) {
        throw new RuntimeException('Failed to paste a text! Try again later.');
    } else if (isset($file, $file['base64']) && !rename($file['tmp_name'], $file_path)) {
        throw new RuntimeException("Failed to move the file. Try again later.");
    } else if (isset($file) && !isset($file['base64']) && !move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new RuntimeException("Failed to save the file. Try again later.");
    }

    // checking if this is a banned file
    if ($reason = STORAGE->is_sha256_banned(hash_file('sha256', $file_path))) {
        STORAGE->delete_file_by_id($file_id, $file_data['extension']);
        throw new RuntimeException('This file is not allowed for upload.' . (is_string($reason) ? " Reason: $reason" : ''));
    }

    // remove letterbox
    $remove_letterbox = CONFIG["upload"]["removeletterboxes"] && boolval($_POST['remove_letterbox'] ?? '0');
    if ($remove_letterbox && str_starts_with($file_data['mime'], 'video/')) {
        $output_path = $file_path;
        $input_path = CONFIG["files"]["directory"] . sprintf('/%s.temporary.%s', $file_id, $file_data['extension']);
        rename($output_path, $input_path);
        if (!remove_video_letterbox($input_path, $output_path)) {
            rename($input_path, $output_path);
            STORAGE->delete_file_by_id($file_id, $file_data['extension']);
            throw new RuntimeException('Failed to remove letterbox from the video');
        }
        unlink($input_path);
    }

    if (
        CONFIG["upload"]["zipwebapps"] &&
        $file_data["extension"] === "zip" &&
        parse_zip_web_archive(
            $file_path,
            sprintf("%s/%s", CONFIG["files"]["directory"], $file_id),
        )
    ) {
        $file_data["extension"] = "html";
        $file_data["mime"] = "text/html";
    }

    $file_data['size'] = filesize($file_path);

    if (CONFIG["thumbnails"]["enable"] && !is_dir(CONFIG["thumbnails"]["directory"]) && !mkdir(CONFIG["thumbnails"]["directory"], 0777, true)) {
        throw new RuntimeException('Failed to create a directory for thumbnails');
    }

    if (
        CONFIG["thumbnails"]["enable"] && (
            (
                str_starts_with($file_data['mime'], 'image/') &&
                $thumbnail_error = generate_image_thumbnail(
                    CONFIG["files"]["directory"] . "/{$file_id}.{$file_data['extension']}",
                    CONFIG["thumbnails"]["directory"] . "/{$file_id}.webp",
                    CONFIG["thumbnails"]["width"],
                    CONFIG["thumbnails"]["height"]
                )
            ) ||
            (
                str_starts_with($file_data['mime'], 'video/') &&
                $thumbnail_error = generate_video_thumbnail(
                    CONFIG["files"]["directory"] . "/{$file_id}.{$file_data['extension']}",
                    CONFIG["thumbnails"]["directory"] . "/{$file_id}",
                    CONFIG["thumbnails"]["directory"] . "/{$file_id}.webp",
                    CONFIG["thumbnails"]["width"],
                    CONFIG["thumbnails"]["height"]
                )
            )
        )
    ) {
        throw new RuntimeException("Failed to create a thumbnail (Error code {$thumbnail_error})");
    }

    // getting file metadata
    $file_data['metadata'] = [
        'width' => null,
        'height' => null,
        'duration' => null,
        'line_count' => null,
    ];
    $metadata_should_be_created = in_array(explode('/', $file_data['mime'])[0], ['image', 'video', 'audio', 'text']) || $file_data['mime'] == 'application/x-shockwave-flash';
    $file_path_escaped = escapeshellarg($file_path);

    $file_data['metadata'] = get_file_metadata($file_path);

    $file_data['urls'] = [
        'download_url' => CONFIG["instance"]["url"] . "/{$file_data['id']}.{$file_data['extension']}"
    ];

    if (CONFIG["files"]["deletion"] && CONFIG['storage']['type'] !== 'file' && !empty($password)) {
        $file_data['password'] = $password;
        $file_data['urls']['deletion_url'] = CONFIG["instance"]["url"] . "/files/delete.php?id={$file_data['id']}.{$file_data['extension']}&key={$file_data['password']}";
    }

    $file_data['expires_at'] = null;

    if (array_key_exists($_POST['expires_in'] ?? '', CONFIG["upload"]["expiration"])) {
        $e = $_POST['expires_in'];
        $format = 'Y-m-d H:i:s';

        function calculate_expiration_time($e, $format)
        {
            $v = intval(substr($e, 0, strlen($e) - 1));
            $m = substr($e, strlen($e) - 1);

            $secs = match ($m) {
                'd' => 86400,
                'h' => 3600,
                'm' => 60,
                default => 0
            };

            $t = time() + $v * $secs;
            return date($format, $t);
        }

        $file_data['expires_at'] = match ($e) {
            'ne' => null,
            're' => date($format),
            default => calculate_expiration_time($e, $format)
        };
    }

    $data_to_send = $file_data;

    $file_data['password'] = isset($file_data['password']) ? password_hash($file_data['password'], PASSWORD_DEFAULT) : null;
    $file_data['views'] = 0;
    $file_data['uploaded_at'] = time();

    if ($title) {
        $file_data['original_name'] = $title;
    }

    if ($preserve_original_name && !$title) {
        if ($file && !empty($file['name'])) {
            $file_data['original_name'] = $file['name'];
        } else if ($url) {
            $file_data['original_name'] = $url;
        }
    }

    // setting visibility
    $visibility = intval($_GET['visibility'] ?? CONFIG['files']['defaultvisibility']);
    if (CONFIG['files']['defaultvisibility'] !== 2) {
        $visibility = max(0, min(1, $visibility));
    }
    $file_data['visibility'] = $visibility;

    $file_data = File::from($file_data);

    if (!STORAGE->save($file_data)) {
        STORAGE->delete_file($file_data);
        throw new RuntimeException('Failed to save the file');
    }

    // don't add a view from the owner
    $viewed_file_ids = $_SESSION['viewed_file_ids'] ?? [];
    array_push($viewed_file_ids, $file_id);
    $_SESSION['viewed_file_ids'] = $viewed_file_ids;

    generate_alert(
        "/{$file_data->id}.{$file_data->extension}",
        null,
        201,
        $data_to_send
    );
} catch (RuntimeException $e) {
    generate_alert(
        "/",
        $e->getMessage(),
        400
    );
}