<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/storage.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/registry.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/id.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/thumbnails.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";

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

$status = new FileTrackStatus(
    isset($_POST['track_id']) && !empty(trim($_POST['track_id'])) ?
    trim($_POST['track_id'])
    : "0"
);

function create_post(string|null $password, DateTime $upload_date, DateTime|null $expiration_date): Post
{
    global $status;

    // generating file id
    $status->send('up_genid', 're-rolling IDs...');
    $attempts = 0;
    $id_length = CONFIG["id"]["length"];
    $id_prefix = CONFIG["id"]["prefix"] ?? '';
    do {
        $post_id = $id_prefix . IDENTIFIER->generate($id_length);
        if ($attempts > 20) {
            $id_length++;
            $attempts = 0;
        }
        $attempts++;
    } while (FILEREGISTRY->has_post($post_id));

    $p = new Post();
    $p->id = $post_id;
    $p->uploaded_at = $upload_date;
    $p->expires_at = $expiration_date;
    $p->password = $password;

    if (!FILEREGISTRY->put_post($p)) {
        throw new RuntimeException("Failed to save post");
    }

    return $p;
}

try {
    $files = [];

    $status->send('up_prep', 'preparing...');

    if (isset($_FILES['file'])) {
        $f = $_FILES['file'];
        // multiple files (rearraging the array)
        if (is_array($f['tmp_name'])) {
            $c = count($f['tmp_name']);
            $ks = array_keys($f);
            for ($i = 0; $i < $c; $i++) {
                $file = [];
                foreach ($ks as $k) {
                    $file[$k] = $f[$k][$i];
                }
                array_push($files, $file);
            }
        } else {
            array_push($files, $f);
        }
    }
    // -- load file from base64
    else if (isset($_POST['base64'])) {
        $file = [
            'tmp_name' => '/tmp/php' . bin2hex(random_bytes(5)),
            'error' => UPLOAD_ERR_OK,
            'base64' => true
        ];

        $parts = explode(',', $_POST['base64']);
        $meta = $parts[0];
        if (!str_starts_with($meta, 'data:image/') || !str_ends_with($meta, ';base64')) {
            throw new HTTPException("Invalid base64 data");
        }

        $data = $parts[1];

        $ifp = fopen($file['tmp_name'], 'wb');

        fwrite($ifp, base64_decode($data));
        fclose($ifp);

        $file['size'] = filesize($file['tmp_name']);
        array_push($files, $file);
    } else {
        throw new HTTPException("No file to upload");
    }

    if (count($files) > CONFIG['upload']['max_files_per_request']) {
        throw new HTTPException("Exceeded file upload limit", 413);
    }

    $single_url = boolval($_POST['single_url'] ?? '0');
    $strip_exif = boolval($_POST['strip_exif'] ?? '0');
    $expire_in = $_POST['expires_in'] ?? CONFIG['upload']['default_expiration'];
    $password = $_POST['password'] ?? null;

    if (CONFIG['upload']['force_default_expiration'] || !array_key_exists($expire_in, CONFIG['upload']['expiration'])) {
        $expire_in = CONFIG['upload']['default_expiration'];
    }

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

    $upload_date = new DateTime();
    $expiration_date = match ($expire_in) {
        'ne' => null,
        're' => $upload_date,
        default => new DateTime(calculate_expiration_time($expire_in, $format))
    };

    $root_post = $single_url ? create_post($password, $upload_date, $expiration_date) : null;
    $uploaded_posts = $single_url ? [$root_post] : [];
    $file_count = count($files);

    for ($i = 0; $i < $file_count; $i++) {
        $file = $files[$i];
        $ic = $i + 1;

        try {
            if (
                !isset($file['error']) ||
                is_array($file['error'])
            ) {
                throw new HTTPException('Invalid parameters');
            }

            // checking file error
            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new HTTPException('No file sent');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new HTTPException('Exceeded filesize limit');
                default:
                    throw new HTTPException('Unknown errors');
            }

            $status->send('up_integrity', "verifying file integrity... ($ic/$file_count)");

            // checking file mimetype
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if (false === $file_ext = array_search($finfo->file($file['tmp_name']), CONFIG["upload"]["acceptedmimetypes"], true)) {
                throw new HTTPException("Invalid file format.");
            }

            // verifying file mimetype
            $file_mime = CONFIG["upload"]["acceptedmimetypes"][$file_ext];
            $is_media = str_starts_with($file_mime, 'image/') || str_starts_with($file_mime, 'video/') || str_starts_with($file_mime, 'audio/');
            if (CONFIG["upload"]["verifymimetype"] && $is_media && !verify_mimetype($file['tmp_name'], $file_mime)) {
                throw new RuntimeException('Invalid file format.');
            }

            // striping exif data
            if (CONFIG["upload"]["stripexif"] && $strip_exif && $is_media && !strip_exif($file['tmp_name'])) {
                throw new RuntimeException('Failed to strip EXIF tags.');
            }

            $post = $root_post ?? create_post($password, $upload_date, $expiration_date);

            // searching for similar file
            $file_hash = hash_file("sha256", $file['tmp_name']);
            if (!$file_hash) {
                throw new RuntimeException("Failed to calculate file hash");
            }

            $base_file = FILEREGISTRY->get_file_by_hash($file_hash);
            if (!$base_file) {
                $meta = new FileMetadata();
                $meta->content_type = $file_mime;

                $status->send('up_save', match (CONFIG['storage']['type']) {
                    'local' => 'writing files to disk...',
                    'sql' => 'writing files to database...',
                    's3' => 'saving files to object storage (may take a long time)...',
                    default => 'saving...',
                } . " ($ic/$file_count)");

                $snowflake_id = new SnowflakeIdentifier(CONFIG['instance']['machine_id'], CONFIG['instance']['epoch']);

                $base_file = FILESTORAGE->save_file("{$snowflake_id->generate()}.$file_ext", $file['tmp_name'], $meta);
                if (!$base_file) {
                    throw new HTTPException('Failed to save file. Try again later.');
                }
                $base_file->hash = $file_hash;
            }

            if (!FILEREGISTRY->attach_to_post($post, $base_file)) {
                throw new HTTPException("Failed to attach file to the post.");
            }

            if (!$single_url) {
                array_push($uploaded_posts, $post);
            }
        } catch (Exception $e) {
            array_push($uploaded_posts, [
                'original_name' => $file['name'],
                'error' => $e->getMessage()
            ]);
        }
    }

    // -- generating thumbnails
    if (THUMBNAILER !== null) {
        $status->send('up_thumbnail', 'drawing thumbnails...');
        $s3_thumb = THUMBNAILER instanceof S3ProxyThumbnailer;
        $data = [];
        foreach ($uploaded_posts as &$p) {
            foreach ($p->attachments as &$f) {
                if (!is_array($f) && ($s3_thumb || $f->path)) {
                    array_push($data, [
                        'input_path' => $s3_thumb ? "{$f->id}.{$f->extension}" : $f->path,
                        'width' => CONFIG['thumbnails']['width'],
                        'height' => CONFIG['thumbnails']['height'],
                    ]);
                }
            }
            unset($f);
        }
        unset($p);

        $thumbnails = THUMBNAILER->generate_thumbnails($data);
    }

    if (isset($_POST['save_upload_list']) && boolval($_POST['save_upload_list'])) {
        $_SESSION['recently_uploaded_files'] = $uploaded_posts;
    }

    $bad_status_count = 0;
    foreach ($uploaded_posts as $x) {
        if (is_array($x) && isset($x['error']))
            $bad_status_count++;
    }

    $status->send('success', null);

    generate_alert(
        "/",
        null,
        $bad_status_count === count($uploaded_posts) ? 400 : 201,
        match (true) {
            count($uploaded_posts) == 1 => $uploaded_posts[0],
            default => $uploaded_posts
        }
    );
} catch (HTTPException $e) {
    $status->send('error', $e->getMessage());
    generate_alert('/', $e->getMessage(), $e->getStatusCode());
} catch (Exception $e) {
    generate_alert('/', $e->getMessage(), 400);
}