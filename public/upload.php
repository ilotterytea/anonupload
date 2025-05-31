<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    json_response(null, 'Method not allowed', 405);
    exit;
}

if (!is_dir(FILE_DIRECTORY) && !mkdir(FILE_DIRECTORY, 0777, true)) {
    json_response(null, 'Failed to create a directory for user files', 500);
    exit();
}

try {
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

        $file_data = [
            'size' => $file['size'],
            'mime' => FILE_ACCEPTED_MIME_TYPES[$file_ext],
            'extension' => $file_ext
        ];
    }

    if (!$file_data) {
        throw new RuntimeException('No URL or file specified');
    }

    $file_id = generate_random_char_sequence(FILE_ID_CHARACTERS, FILE_ID_LENGTH);
    $file_data['id'] = $file_id;

    if (isset($url)) {
        $result = 0;
        $output = [];

        exec(sprintf(
            'yt-dlp -f "worst" -o "%s/%s.%s" %s 2>&1',
            FILE_DIRECTORY,
            $file_id,
            $file_data['extension'],
            escapeshellarg($url)
        ), $output, $result);

        if ($result != 0) {
            error_log(sprintf("Failed to download a file (URL: %s): %s", $url, implode('\n', $output)));
            throw new RuntimeException('Failed to download a file! Try again later.');
        }
    } else if (isset($paste) && !file_put_contents(FILE_DIRECTORY . sprintf('/%s.%s', $file_id, $file_data['extension']), $paste)) {
        throw new RuntimeException('Failed to paste a text! Try again later.');
    } else if (isset($file) && !move_uploaded_file($file['tmp_name'], FILE_DIRECTORY . sprintf('/%s.%s', $file_id, $file_data['extension']))) {
        throw new RuntimeException("Failed to save the file. Try again later.");
    }

    if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
        json_response($file_data, null, 201);
    } else {
        header("Location: /{$file_data['id']}.{$file_data['extension']}");
    }
} catch (RuntimeException $e) {
    if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
        json_response(null, $e->getMessage(), 400);
    } else {
        http_response_code(400);
        echo $e->getMessage();
    }
}