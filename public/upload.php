<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/thumbnails.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

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

if (!is_dir(FILE_UPLOAD_DIRECTORY) && !mkdir(FILE_UPLOAD_DIRECTORY, 0777, true)) {
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
    $title = str_safe($_POST['title'] ?? '', FILE_TITLE_MAX_LENGTH);
    if (empty(trim($title))) {
        $title = null;
    }

    $url = isset($_POST['url']) ? $_POST['url'] ?: null : null;
    $file = isset($_FILES['file']) ? $_FILES['file'] ?: null : null;
    if (empty($file['tmp_name'])) {
        $file = null;
    }
    $paste = isset($_POST['paste']) ? $_POST['paste'] ?: null : null;
    $password = $_POST['password'] ?? generate_random_char_sequence(FILE_ID_CHARACTERS, FILE_DELETION_KEY_LENGTH);
    $file_data = null;

    if (!(isset($file) ^ isset($url) ^ isset($paste)) || (isset($file) && isset($url) && isset($paste))) {
        throw new RuntimeException('You can upload only one type of content: file, URL or text');
    }

    if (FILEEXT_ENABLED && isset($url) && !empty($url)) {
        $output = [];
        $fileext_quality = FILEEXT_QUALITY ? ('-f "' . FILEEXT_QUALITY . '"') : "";
        exec('yt-dlp ' . $fileext_quality . ' --get-filename -o "%(filesize_approx)s %(ext)s %(duration)s" ' . escapeshellarg($url) . '', $output);
        if (empty($output)) {
            throw new RuntimeException('Bad URL');
        }

        $output = explode(' ', $output[0]);

        // TODO: some videos don't have duration
        $duration = intval($output[2]);
        if ($duration > FILEEXT_MAX_DURATION) {
            throw new RuntimeException(sprintf("File must be under %d minutes", FILEEXT_MAX_DURATION / 60));
        }

        if (!array_key_exists($output[1], FILE_ACCEPTED_MIME_TYPES)) {
            throw new RuntimeException("Unsupported extension: {$output[1]}");
        }

        $file_data = [
            'size' => intval($output[0]),
            'mime' => FILE_ACCEPTED_MIME_TYPES[$output[1]],
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
        if (false === $file_ext = array_search($finfo->file($file['tmp_name']), FILE_ACCEPTED_MIME_TYPES, true)) {
            throw new RuntimeException("Invalid file format.");
        }

        // verifying file mimetype
        $file_mime = FILE_ACCEPTED_MIME_TYPES[$file_ext];
        $is_media = str_starts_with($file_mime, 'image/') || str_starts_with($file_mime, 'video/') || str_starts_with($file_mime, 'audio/');
        if (FILE_VERIFY_MIMETYPE && $is_media && !verify_mimetype($file['tmp_name'], $file_mime)) {
            throw new RuntimeException('Invalid file format.');
        }

        // striping exif data
        if (FILE_STRIP_EXIF && $is_media && !strip_exif($file['tmp_name'])) {
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

    $db = new PDO(DB_URL, DB_USER, DB_PASS);

    if (FILE_CUSTOM_ID && isset($_POST['id']) && !empty(trim($_POST['id']))) {
        $file_id = $_POST['id'];
        if (!preg_match(FILE_CUSTOM_ID_REGEX, $file_id) || strlen($file_id) > FILE_CUSTOM_ID_LENGTH) {
            throw new RuntimeException('Invalid file ID.');
        }

        $stmt = $db->prepare('SELECT id FROM files WHERE id = ?');
        $stmt->execute([$file_id]);
        if ($stmt->rowCount() > 0) {
            throw new RuntimeException('File ID has already been taken.');
        }
    } else {
        $file_id_length = FILE_ID_LENGTH;
        $file_id_gen_attempts = 0;
        $sql = 'SELECT id FROM files WHERE id = ? AND extension = ?';
        do {
            $file_id = FILE_ID_PREFIX . generate_random_char_sequence(FILE_ID_CHARACTERS, $file_id_length);
            if ($file_id_gen_attempts > 20) {
                $file_id_length++;
                $file_id_gen_attempts = 0;
            }
            $file_id_gen_attempts++;

            $stmt = $db->prepare($sql);
            $stmt->execute([$file_id, $file_data['extension']]);
        } while ($stmt->rowCount() > 0);
    }

    $file_data['id'] = $file_id;

    if (isset($url)) {
        $result = 0;
        $output = [];

        $file_path = FILE_UPLOAD_DIRECTORY . "/$file_id.{$file_data['extension']}";

        exec(sprintf(
            'yt-dlp %s -o "%s" %s 2>&1',
            FILEEXT_QUALITY ? ('-f "' . FILEEXT_QUALITY . '"') : "",
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
        if (FILE_VERIFY_MIMETYPE && $is_media && !verify_mimetype($file_path, $file_mime)) {
            delete_file($file_id, $file_data['extension']);
            throw new RuntimeException('Invalid file format.');
        }
    } else if (isset($paste) && !file_put_contents($file_path = FILE_UPLOAD_DIRECTORY . sprintf('/%s.%s', $file_id, $file_data['extension']), $paste)) {
        throw new RuntimeException('Failed to paste a text! Try again later.');
    } else if (isset($file) && !move_uploaded_file($file['tmp_name'], $file_path = FILE_UPLOAD_DIRECTORY . sprintf('/%s.%s', $file_id, $file_data['extension']))) {
        throw new RuntimeException("Failed to save the file. Try again later.");
    }

    // checking if this is a banned file
    $file_sha = hash_file('sha256', $file_path);
    $stmt = $db->prepare('SELECT reason FROM hash_bans WHERE sha256 = ?');
    $stmt->execute([$file_sha]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        delete_file($file_id, $file_data['extension']);
        throw new RuntimeException('This file is not allowed for upload.' . (isset($row['reason']) ? ' Reason: ' . $row['reason'] : ''));
    }

    // remove letterbox
    $remove_letterbox = FILE_REMOVE_LETTERBOXES && boolval($_POST['remove_letterbox'] ?? '0');
    if ($remove_letterbox && str_starts_with($file_data['mime'], 'video/')) {
        $output_path = $file_path;
        $input_path = FILE_UPLOAD_DIRECTORY . sprintf('/%s.temporary.%s', $file_id, $file_data['extension']);
        rename($output_path, $input_path);
        if (!remove_video_letterbox($input_path, $output_path)) {
            rename($input_path, $output_path);
            delete_file($file_id, $file_data['extension']);
            throw new RuntimeException('Failed to remove letterbox from the video');
        }
        unlink($input_path);
    }

    $file_data['size'] = filesize($file_path);

    if (FILE_THUMBNAILS && !is_dir(FILE_THUMBNAIL_DIRECTORY) && !mkdir(FILE_THUMBNAIL_DIRECTORY, 0777, true)) {
        throw new RuntimeException('Failed to create a directory for thumbnails');
    }

    if (
        FILE_THUMBNAILS && (
            (
                str_starts_with($file_data['mime'], 'image/') &&
                $thumbnail_error = generate_image_thumbnail(
                    FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_data['extension']}",
                    FILE_THUMBNAIL_DIRECTORY . "/{$file_id}.webp",
                    FILE_THUMBNAIL_SIZE[0],
                    FILE_THUMBNAIL_SIZE[1]
                )
            ) ||
            (
                str_starts_with($file_data['mime'], 'video/') &&
                $thumbnail_error = generate_video_thumbnail(
                    FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_data['extension']}",
                    FILE_THUMBNAIL_DIRECTORY . "/{$file_id}",
                    FILE_THUMBNAIL_DIRECTORY . "/{$file_id}.webp",
                    FILE_THUMBNAIL_SIZE[0],
                    FILE_THUMBNAIL_SIZE[1]
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

    if (str_starts_with($file_data['mime'], 'image/')) {
        [$width, $height] = explode('x', trim(shell_exec('identify -format "%wx%h" ' . escapeshellarg($file_path) . '[0]')));
        $file_data['metadata']['width'] = intval($width);
        $file_data['metadata']['height'] = intval($height);
    } else if (str_starts_with($file_data['mime'], 'video/')) {
        $info = shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of csv=p=0 ' . escapeshellarg($file_path));
        [$width, $height, $duration] = explode(',', trim($info));
        $file_data['metadata']['width'] = intval($width);
        $file_data['metadata']['height'] = intval($height);
        $file_data['metadata']['duration'] = $duration == 'N/A' ? null : intval(round($duration, 2));
    } else if (str_starts_with($file_data['mime'], 'audio/')) {
        $file_data['metadata']['duration'] = intval(round(trim(shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file_path))), 2));
    } else if (str_starts_with($file_data['mime'], 'text/')) {
        $file_data['metadata']['line_count'] = intval(trim(shell_exec('wc -l < ' . escapeshellarg($file_path))));
    } else if ($file_data['mime'] == 'application/x-shockwave-flash') {
        [$width, $height] = parse_swf_file($file_path);
        $file_data['metadata']['width'] = $width;
        $file_data['metadata']['height'] = $height;
    }

    $file_data['urls'] = [
        'download_url' => INSTANCE_URL . "/{$file_data['id']}.{$file_data['extension']}"
    ];

    if (FILE_DELETION && !empty($password)) {
        $file_data['password'] = $password;
        $file_data['urls']['deletion_url'] = INSTANCE_URL . "/delete.php?f={$file_data['id']}.{$file_data['extension']}&key={$file_data['password']}";
    }

    $file_data['expires_at'] = null;

    if (array_key_exists($_POST['expires_in'] ?? '', FILE_EXPIRATION)) {
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

    generate_alert(
        "/{$file_data['id']}.{$file_data['extension']}",
        null,
        201,
        $file_data
    );

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

    $db->prepare('INSERT INTO files(id, mime, extension, size, title, password, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $file_data['id'],
            $file_data['mime'],
            $file_data['extension'],
            $file_data['size'],
            $file_data['original_name'] ?? null,
            $file_data['password'],
            $file_data['expires_at']
        ]);

    if ($metadata_should_be_created) {
        $file_data['metadata']['id'] = $file_data['id'];
        $db->prepare('INSERT INTO file_metadata(width, height, duration, line_count, id) VALUES (?, ?, ?, ?, ?)')
            ->execute(array_values($file_data['metadata']));
    }

    // don't add a view from the owner
    $viewed_file_ids = $_SESSION['viewed_file_ids'] ?? [];
    array_push($viewed_file_ids, $file_id);
    $_SESSION['viewed_file_ids'] = $viewed_file_ids;
} catch (RuntimeException $e) {
    generate_alert(
        "/",
        $e->getMessage(),
        400
    );
}