<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/thumbnails.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

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

    $url = isset($_POST['url']) ? $_POST['url'] ?: null : null;
    $file = isset($_FILES['file']) ? $_FILES['file'] ?: null : null;
    if (empty($file['tmp_name'])) {
        $file = null;
    }
    $paste = isset($_POST['paste']) ? $_POST['paste'] ?: null : null;
    $file_data = null;

    if (!(isset($file) ^ isset($url) ^ isset($paste)) || (isset($file) && isset($url) && isset($paste))) {
        throw new RuntimeException('You can upload only one type of content: file, URL or text');
    }

    if (FILEEXT_ENABLED && isset($url) && !empty($url)) {
        $output = [];
        exec('yt-dlp -f "worst" --get-filename -o "%(filesize_approx)s %(ext)s %(duration)s" ' . escapeshellarg($url) . '', $output);
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

        $file_data = [
            'size' => $file['size'],
            'mime' => $file_mime,
            'extension' => $file_ext
        ];
    }

    if (!$file_data) {
        throw new RuntimeException('No URL or file specified');
    }

    $file_id_length = FILE_ID_LENGTH;
    $file_id_gen_attempts = 0;
    do {
        $file_id = FILE_ID_PREFIX . generate_random_char_sequence(FILE_ID_CHARACTERS, $file_id_length);
        if ($file_id_gen_attempts > 20) {
            $file_id_length++;
            $file_id_gen_attempts = 0;
        }
        $file_id_gen_attempts++;
    } while (is_file(FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_data['extension']}"));
    $file_data['id'] = $file_id;

    if (isset($url)) {
        $result = 0;
        $output = [];

        $file_path = FILE_UPLOAD_DIRECTORY . "/$file_id.{$file_data['extension']}";

        exec(sprintf(
            'yt-dlp -f "worst" -o "%s" %s 2>&1',
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
    } else if (isset($paste) && !file_put_contents(FILE_UPLOAD_DIRECTORY . sprintf('/%s.%s', $file_id, $file_data['extension']), $paste)) {
        throw new RuntimeException('Failed to paste a text! Try again later.');
    } else if (isset($file) && !move_uploaded_file($file['tmp_name'], FILE_UPLOAD_DIRECTORY . sprintf('/%s.%s', $file_id, $file_data['extension']))) {
        throw new RuntimeException("Failed to save the file. Try again later.");
    }

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

    $file_data['urls'] = [
        'download_url' => INSTANCE_URL . "/{$file_data['id']}.{$file_data['extension']}"
    ];

    if (FILE_METADATA && FILE_DELETION) {
        $file_data['password'] = $_POST['password'] ?? generate_random_char_sequence(FILE_ID_CHARACTERS, FILE_DELETION_KEY_LENGTH);
        $file_data['urls']['deletion_url'] = INSTANCE_URL . "/delete.php?f={$file_data['id']}.{$file_data['extension']}&key={$file_data['password']}";
    }

    generate_alert(
        "/{$file_data['id']}.{$file_data['extension']}",
        null,
        201,
        $file_data
    );

    if (FILE_METADATA) {
        unset($file_data['urls']);
        $file_data['password'] = password_hash($file_data['password'], PASSWORD_DEFAULT);
        $file_data['views'] = 0;
        $file_data['uploaded_at'] = time();

        if ($preserve_original_name) {
            if ($file && !empty($file['name'])) {
                $file_data['original_name'] = $file['name'];
            } else if ($url) {
                $file_data['original_name'] = $url;
            }
        }

        if (!is_dir(FILE_METADATA_DIRECTORY) && !mkdir(FILE_METADATA_DIRECTORY, 0777, true)) {
            throw new RuntimeException('Failed to create a folder for file metadata');
        }
        if (!file_put_contents(FILE_METADATA_DIRECTORY . "/{$file_data['id']}.metadata.json", json_encode($file_data, JSON_UNESCAPED_SLASHES))) {
            throw new RuntimeException('Failed to create a file metadata');
        }
    }
} catch (RuntimeException $e) {
    generate_alert(
        "/",
        $e->getMessage(),
        400
    );
}