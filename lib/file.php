<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

function parse_file_name(string|null $filename): array|null
{
    if ($filename === null) {
        return null;
    }

    $parts = explode(".", $filename);
    if (count($parts) < 2) {
        return null;
    }

    $ext = $parts[count($parts) - 1];
    unset($parts[count($parts) - 1]);

    return [
        'name' => implode(".", $parts),
        'extension' => $ext
    ];
}

function get_file_metadata(string $file_path): array|null
{
    $ext = explode('.', basename($file_path));
    $ext = $ext[count($ext) - 1];
    $mime = CONFIG['upload']['acceptedmimetypes'][$ext];

    if (str_starts_with($mime, 'image/') && IMAGEMAGICK_COMMAND) {
        [$width, $height] = explode('x', trim(shell_exec(IMAGEMAGICK_COMMAND['identify'] . ' -format "%wx%h" ' . escapeshellarg($file_path) . '[0]')));
        return [
            'width' => intval($width),
            'height' => intval($height),
            'duration' => null,
            'line_count' => null
        ];
    } else if (str_starts_with($mime, 'video/')) {
        $info = shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of csv=p=0 ' . escapeshellarg($file_path));
        [$width, $height, $duration] = explode(',', trim($info));
        return [
            'width' => intval($width),
            'height' => intval($height),
            'duration' => $duration == 'N/A' ? null : intval(round($duration, 2)),
            'line_count' => null
        ];
    } else if (str_starts_with($mime, 'audio/')) {
        return [
            'width' => null,
            'height' => null,
            'duration' => intval(round(trim(shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file_path))), 2)),
            'line_count' => null
        ];
    } else if (str_starts_with($mime, 'text/')) {
        return [
            'width' => null,
            'height' => null,
            'duration' => null,
            'line_count' => intval(trim(shell_exec('wc -l < ' . escapeshellarg($file_path))))
        ];
    } else if ($mime === 'application/x-shockwave-flash') {
        [$width, $height] = parse_swf_file($file_path);
        return [
            'width' => $width,
            'height' => $height,
            'duration' => null,
            'line_count' => null
        ];
    } else {
        return null;
    }
}

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

    if (str_starts_with($mimetype, 'image/') && IMAGEMAGICK_COMMAND) {
        $output = shell_exec(IMAGEMAGICK_COMMAND['identify'] . " -quiet -ping $path");
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

class File
{
    public string $id, $mime, $extension, $path, $color;
    public int $size;
    public int|null $views, $visibility, $width, $height, $duration, $line_count;
    public string|null $password, $ban_reason, $title;
    public bool $is_banned;
    public DateTime|null $expires_at, $uploaded_at;

    public function __construct()
    {
    }

    public function as_array(): array
    {
        $d = [
            'id' => $this->id,
            'mime' => $this->mime,
            'extension' => $this->extension,
            'size' => $this->size,
            'views' => $this->views,
            'visibility' => $this->visibility,
            'password' => $this->password,
            'expires_at' => $this->expires_at,
            'uploaded_at' => $this->uploaded_at,
            'is_banned' => $this->is_banned,
            'ban_reason' => $this->ban_reason
        ];

        if (isset($this->width) || isset($this->height) || isset($this->duration) || isset($this->line_count)) {
            $d['metadata'] = [
                'width' => $this->width,
                'height' => $this->height,
                'duration' => $this->duration,
                'line_count' => $this->line_count
            ];
        }

        return $d;
    }

    public static function from(mixed $data): File
    {
        $f = new File();

        $f->id = $data['id'];
        $f->mime = $data['mime'];
        $f->extension = $data['extension'];
        $f->size = $data['size'];
        $f->views = $data['views'] ?? null;
        $f->password = $data['password'] ?? null;
        $f->path = CONFIG['files']['directory'] . "/{$f->id}.{$f->extension}";
        $f->is_banned = $data['is_banned'] ?? false;
        $f->ban_reason = $data['ban_reason'] ?? null;
        $f->title = $data['title'] ?? null;
        $f->visibility = $data['visibility'] ?? null;

        if (isset($data['metadata'])) {
            $f->width = $data['metadata']['width'] ?? null;
            $f->height = $data['metadata']['height'] ?? null;
            $f->duration = $data['metadata']['duration'] ?? null;
            $f->line_count = $data['metadata']['line_count'] ?? null;
        }

        if (isset($data['expires_at'])) {
            $f->expires_at = new DateTime();

            if (is_numeric($data['expires_at'])) {
                $f->expires_at->setTimestamp(intval($data['expires_at']));
            } elseif (isset($data['expires_at']['date'])) {
                $f->expires_at->setTimestamp(strtotime($data['expires_at']['date']));
            } else {
                $f->expires_at->setTimestamp(strtotime($data['expires_at']));
            }
        } else {
            $f->expires_at = null;
        }

        if (isset($data['uploaded_at'])) {
            $f->uploaded_at = new DateTime();

            if (is_numeric($data['uploaded_at'])) {
                $f->uploaded_at->setTimestamp(intval($data['uploaded_at']));
            } elseif (isset($data['uploaded_at']['date'])) {
                $f->uploaded_at->setTimestamp(strtotime($data['uploaded_at']['date']));
            } else {
                $f->uploaded_at->setTimestamp(strtotime($data['uploaded_at']));
            }
        } else {
            $f->uploaded_at = null;
        }

        if (str_starts_with($f->mime, 'video/')) {
            $f->color = 'blue';
        } else if ($f->mime == 'application/x-shockwave-flash') {
            $f->color = 'red';
        } else {
            $f->color = 'black';
        }

        return $f;
    }

    public static function load(string|null $file_name): File|null
    {
        if ($file_name === null) {
            return null;
        }

        $file_name = basename($file_name);
        $path = CONFIG['files']['directory'] . "/$file_name";

        $file_name = parse_file_name($file_name);
        if ($file_name === null) {
            return null;
        }

        $id = $file_name['name'];
        $ext = $file_name['extension'];

        $data = null;

        if (STORAGE->get_type() === FileStorageType::Database) {
            $stmt = STORAGE->get_db()->prepare('SELECT fm.*, f.*,
                hb.reason AS ban_reason,
                CASE WHEN fb.hash_ban IS NOT NULL THEN 1 ELSE 0 END AS is_banned
                FROM files f
                LEFT JOIN file_metadata fm ON fm.id = f.id
                LEFT JOIN file_bans fb ON fb.id = f.id
                LEFT JOIN hash_bans hb ON hb.sha256 = fb.hash_ban
                WHERE f.id = ? AND f.extension = ?
            ');
            $stmt->execute([$id, $ext]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($data == null && file_exists(CONFIG['metadata']['directory'] . "/$id.json")) {
            $data = json_decode(file_get_contents(CONFIG['metadata']['directory'] . "/$id.json"), true);
        }

        if ($data == null && file_exists($path)) {
            $data = [
                'id' => $id,
                'extension' => $ext,
                'mime' => CONFIG['upload']['acceptedmimetypes'][$ext],
                'size' => filesize($path),
                'views' => 0,
                'password' => null,
                'expires_at' => null,
                'visibility' => CONFIG['files']['defaultvisibility'],
                'uploaded_at' => filectime($path),
                'metadata' => get_file_metadata($path)
            ];
        }

        return File::from($data);
    }
}

enum FileStorageType
{
    case File;
    case Json;
    case Database;
}

class FileMetadataStorage
{
    private FileStorageType $type;

    private PDO|null $db;

    public function __construct(FileStorageType $type)
    {
        $this->type = $type;
        if ($type === FileStorageType::Database) {
            $this->db = new PDO(CONFIG['database']['url'], CONFIG['database']['user'], CONFIG['database']['pass']);
        }
    }

    public function get_by_name(string $name): File|null
    {
        $file_id = explode('.', $name);
        if (count($file_id) != 2) {
            throw new RuntimeException("Invalid file ID: $name");
        }

        $file_ext = $file_id[1];
        $file_id = $file_id[0];

        return $this->get_by_name_and_extension($file_id, $file_ext);
    }

    public function get_by_name_and_extension(string $name, string $extension): File|null
    {
        switch ($this->type) {
            case FileStorageType::File: {
                $path = CONFIG['files']['directory'] . "/$name.$extension";
                if (!file_exists($path)) {
                    return null;
                }

                $file = new File();
                $file->extension = $extension;
                $file->mime = CONFIG['upload']['acceptedmimetypes'][$extension];
                $file->size = filesize($path);
                $file->password = null;
                $file->expires_at = null;

                return $file;
            }
            case FileStorageType::Database: {
                $stmt = $this->db->prepare('SELECT fm.*, f.*,
                    hb.reason AS ban_reason,
                    CASE WHEN fb.hash_ban IS NOT NULL THEN 1 ELSE 0 END AS is_banned
                    FROM files f
                    LEFT JOIN file_metadata fm ON fm.id = f.id
                    LEFT JOIN file_bans fb ON fb.id = f.id
                    LEFT JOIN hash_bans hb ON hb.sha256 = fb.hash_ban
                    WHERE f.id = ? AND f.extension = ?
                ');
                $stmt->execute([$name, $extension]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (!$res) {
                    return null;
                }

                return File::from($res);
            }
            case FileStorageType::Json: {
                $metadata_path = CONFIG['metadata']['directory'] . "/$name.json";
                if (!file_exists($metadata_path)) {
                    return null;
                }

                return File::from(json_decode(file_get_contents($metadata_path), true));
            }
            default:
                throw new RuntimeException("Unimplemented for " . $this->type->id);
        }
    }

    public function save(File $file): bool
    {
        switch ($this->type) {
            case FileStorageType::File: {
                return true;
            }
            case FileStorageType::Database: {
                $stmt = $this->db->prepare('SELECT id FROM files WHERE id = ? AND extension = ?');
                $stmt->execute([$file->id, $file->extension]);

                if ($stmt->rowCount() === 0) {
                    $this->db->prepare('INSERT INTO files
                (id, mime, extension, `size`, `password`, visibility, expires_at, uploaded_at) VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([
                                $file->id,
                                $file->mime,
                                $file->extension,
                                $file->size,
                                $file->password,
                                $file->visibility ?? CONFIG['files']['defaultvisibility'],
                                $file->expires_at === null ? null : $file->expires_at->format('Y-m-d H:i:s'),
                                ($file->uploaded_at ?? new DateTime())->format('Y-m-d H:i:s')
                            ]);
                }

                $this->db->prepare('UPDATE files
                SET
                    mime = ?,
                    extension = ?,
                    `size` = ?,
                    title = ?,
                    `password` = ?,
                    visibility = ?,
                    uploaded_at = ?,
                    expires_at = ?,
                    views = ?
                WHERE
                    id = ? AND
                    extension = ?')
                    ->execute([
                        $file->mime,
                        $file->extension,
                        $file->size,
                        $file->title,
                        $file->password,
                        $file->visibility ?? CONFIG['files']['defaultvisibility'],
                        ($file->uploaded_at ?? new DateTime())->format('Y-m-d H:i:s'),
                        $file->expires_at === null ? null : $file->expires_at->format('Y-m-d H:i:s'),
                        $file->views ?? 0,
                        $file->id,
                        $file->extension
                    ]);

                return true;
            }
            case FileStorageType::Json: {
                if (!is_dir(CONFIG['metadata']['directory'])) {
                    mkdir(CONFIG['metadata']['directory'], 0777, true);
                }
                $metadata_path = CONFIG['metadata']['directory'] . "/{$file->id}.json";
                return file_put_contents($metadata_path, json_encode($file->as_array(), JSON_UNESCAPED_SLASHES));
            }
            default:
                throw new RuntimeException("Unimplemented for " . $this->type->id);
        }
    }

    public function get_random_file(): File|null
    {
        if ($this->type === FileStorageType::Database) {
            $viewed_files = [];
            if (session_status() === PHP_SESSION_ACTIVE)
                $viewed_files = $_SESSION['random_viewed_files'] ?? [];

            $mime_filter = "";
            if (!empty(CONFIG["filecatalog"]["includemimetypes"])) {
                var_dump(CONFIG["filecatalog"]["includemimetypes"]);
                $mime_filter = [];
                foreach (CONFIG["filecatalog"]["includemimetypes"] as $k) {
                    array_push($mime_filter, "mime LIKE '$k'");
                }
                $mime_filter = '(' . implode(' OR ', $mime_filter) . ')';
            }

            $in = !empty($viewed_files) ? (str_repeat('?,', count($viewed_files) - 1) . '?') : '';
            $in_condition = !empty($viewed_files) ? ("id NOT IN ($in) " . ($mime_filter ? " AND " : "")) : "";
            $where_word = $in_condition || $mime_filter ? "WHERE" : "";
            $order_condition = CONFIG["supriseme"]["order"] ?: "rand()";

            $file_id = null;
            $file_path = null;
            $attempts = 0;

            do {
                $file_id = null;
                $file_path = null;

                $stmt = $this->db->prepare("SELECT id, extension FROM files $where_word $in_condition $mime_filter ORDER BY $order_condition LIMIT 1");
                if (empty($viewed_files)) {
                    $stmt->execute();
                } else {
                    $stmt->execute($viewed_files);
                }

                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $file_id = $row['id'];
                    $file_path = "{$row['id']}.{$row['extension']}";
                } else {
                    $viewed_files = array_diff($viewed_files, $viewed_files);
                    $in_condition = '';
                }

                $attempts++;
            } while ((!$file_id || in_array($file_id, $viewed_files)) && $attempts < 256);
        } else {
            $files = glob(CONFIG['files']['directory'] . '/*.*');
            $count = count($files);
            if ($count == 0) {
                return null;
            }
            $file_path = $files[random_int(0, $count - 1)];
        }

        return File::load($file_path);
    }

    public function count_file_and_size(): array
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query('SELECT COUNT(*) AS file_count, SUM(size) AS file_overall_size FROM files WHERE id NOT IN (SELECT id FROM file_bans)');
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row;
        } else {
            if (!is_dir(CONFIG['files']['directory']) && !mkdir(CONFIG['files']['directory'], 0777, true)) {
                throw new RuntimeException("Failed to create a folder");
            }

            $iter = new DirectoryIterator(CONFIG['files']['directory']);
            $count = 0;
            $size = 0;

            foreach ($iter as $file) {
                if ($file->isDot()) {
                    continue;
                }
                $count++;
                $size += filesize($file->getPathname());
            }

            return [
                'file_count' => $count,
                'file_overall_size' => $size
            ];
        }
    }

    public function get_upload_timeline(): array
    {
        $stats = [];

        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT YEAR(uploaded_at) AS year, QUARTER(uploaded_at) AS quarter, COUNT(*) AS file_count
                FROM files
                WHERE uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                GROUP BY YEAR(uploaded_at), QUARTER(uploaded_at)
                ORDER BY year, quarter
            ");
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $raw = [];
            $cutoff = strtotime("-5 years");

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $timestamp = $file->getMTime();
                if ($timestamp < $cutoff) {
                    continue;
                }

                $year = (int) date('Y', $timestamp);
                $quarter = (int) ceil(date('n', $timestamp) / 3);

                if (!isset($raw[$year][$quarter])) {
                    $raw[$year][$quarter] = 0;
                }

                $raw[$year][$quarter]++;
            }

            ksort($raw);

            foreach ($raw as $y => $qs) {
                ksort($qs);
                foreach ($qs as $q => $c) {
                    array_push($stats, [
                        'year' => $y,
                        'quarter' => $q,
                        'file_count' => $c
                    ]);
                }
            }
        }

        return $stats;
    }

    public function get_recent_files_by_mime(string $mime = "%/%", int $limit = 0): array
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT id, extension FROM files WHERE mime LIKE '$mime' ORDER BY uploaded_at DESC" . ($limit > 0 ? " LIMIT $limit" : ''));
            return array_map(fn($x) => File::from($x), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $mime = explode('/', $mime);
            $basemime = $mime[0];
            $submime = $mime[1];

            $files = glob(CONFIG['files']['directory'] . '/*.*');
            $out = [];
            foreach ($files as $f) {
                if ($limit > 0 && count($out) >= $limit) {
                    break;
                }
                $file = File::load($f);
                if ($file == null) {
                    continue;
                }

                // parsing mime
                $mime = explode('/', $file->mime);
                if (
                    ($basemime !== '%' && $basemime !== $mime[0]) ||
                    ($submime !== '%' && $submime !== $mime[1])
                ) {
                    continue;
                }

                array_push($out, $file);
            }

            usort($out, fn($a, $b) => ($b->uploaded_at?->getTimestamp() ?? PHP_INT_MIN) <=> ($a->uploaded_at?->getTimestamp() ?? PHP_INT_MIN));

            return $out;
        }
    }

    public function get_most_viewed_files(int $limit = 0): array
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT id, extension, mime FROM files ORDER BY views DESC" . ($limit > 0 ? " LIMIT $limit" : ''));
            return array_map(fn($x) => File::from($x), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $paths = glob(CONFIG['metadata']['directory'] . '/*.*');
            $files = [];
            foreach ($paths as $path) {
                $file = File::load($path);
                if ($file === null || $file->views === null) {
                    continue;
                }
                array_push($files, $file);
            }
            usort($files, fn($a, $b) => ($b->views ?? PHP_INT_MIN) <=> ($a->views ?? PHP_INT_MIN));
            return $files;
        }
    }

    public function get_stats(): array
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT COUNT(*) AS file_count, SUM(size) AS active_content, AVG(size) AS approx_filesize,
                COUNT(*) / TIMESTAMPDIFF(MINUTE, MIN(uploaded_at), MAX(uploaded_at)) AS avg_upload_rate
                FROM files
                WHERE id NOT IN (SELECT id FROM file_bans)
            ");
            $file_stats = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
        } else {
            $file_count = 0;
            $active_content = 0;
            $approx_filesize = 0;

            $min_time = null;
            $max_time = null;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $size = $file->getSize();
                $time = $file->getMTime();

                $file_count++;
                $active_content += $size;
                $min_time = $min_time === null ? $time : min($min_time, $time);
                $max_time = $max_time === null ? $time : max($max_time, $time);
            }

            $approx_filesize = $file_count > 0 ? $active_content / $file_count : 0;

            $minutes = ($min_time !== null && $max_time !== null && $max_time > $min_time)
                ? ($max_time - $min_time) / 60
                : 0;

            $avg_upload_rate = $minutes > 0 ? $file_count / $minutes : 0;

            return [
                'file_count' => $file_count,
                'active_content' => $active_content,
                'approx_filesize' => $approx_filesize,
                'avg_upload_rate' => $avg_upload_rate
            ];
        }
    }

    public function get_files(int $page, string $sort = "recent"): array
    {
        if (!in_array($sort, ["recent", "oldest", "most_viewed", "least_viewed"], true)) {
            $sort = "recent";
        }

        $limit = CONFIG['filecatalog']['limit'];
        $offset = $page * $limit;

        $files = [];

        if ($this->type === FileStorageType::Database) {
            $sort_sql = match ($sort) {
                "oldest" => "ORDER BY f.uploaded_at ASC",
                "recent" => "ORDER BY f.uploaded_at DESC",
                "most_viewed" => "ORDER BY f.views DESC",
                "least_viewed" => "ORDER BY f.views ASC",
            };

            $stmt = $this->db->query("SELECT f.id, f.mime, f.extension, f.size
                FROM files f
                WHERE f.id NOT IN (SELECT id FROM file_bans)
                    AND f.visibility = 1
                $sort_sql
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                array_push($files, File::from($row));
            }

            return $files;
        } else {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $f = File::load($file->getPathname());
                if ($f->visibility !== null && $f->visibility !== 1) {
                    continue;
                }
                array_push($files, $f);
            }

            switch ($sort) {
                case "oldest":
                    usort($files, fn($a, $b) => ($a->uploaded_at?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->uploaded_at?->getTimestamp() ?? PHP_INT_MAX));
                    break;
                case "least_viewed":
                    usort($files, fn($a, $b) => ($a->views ?? PHP_INT_MAX) <=> ($b->views ?? PHP_INT_MAX));
                    break;
                case "most_viewed":
                    usort($files, fn($a, $b) => ($b->views ?? PHP_INT_MIN) <=> ($a->views ?? PHP_INT_MIN));
                    break;
                case "recent":
                default:
                    usort($files, fn($a, $b) => ($b->uploaded_at?->getTimestamp() ?? PHP_INT_MIN) <=> ($a->uploaded_at?->getTimestamp() ?? PHP_INT_MIN));
                    break;
            }

            $files = array_slice($files, $offset, $limit);

            return $files;
        }
    }

    public function get_files_by_visibility(int $visibility): array
    {
        $files = [];

        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT f.id, f.mime, f.extension
                FROM files f
                WHERE f.id NOT IN (SELECT id FROM file_bans) AND f.visibility = ?
            ");
            $stmt->execute([$visibility]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                array_push($files, File::from($row));
            }
        } elseif ($this->type === FileStorageType::Json) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $f = File::load($file->getPathname());
                if ($f->visibility === null || $f->visibility !== $visibility) {
                    continue;
                }
                array_push($files, $f);
            }
        }

        return $files;
    }

    public function delete_file(File $file): bool
    {
        if ($this->type === FileStorageType::Database) {
            $this->db->prepare('DELETE FROM files WHERE id = ? AND extension = ?')
                ->execute([$file->id, $file->extension]);
        }

        $paths = [
            CONFIG["files"]["directory"] . "/{$file->id}.{$file->extension}",
            CONFIG["thumbnails"]["directory"] . "/{$file->id}.webp",
            CONFIG["metadata"]["directory"] . "/{$file->id}.json"
        ];

        foreach ($paths as $path) {
            if (is_file($path) && !unlink($path)) {
                error_log("Failed to remove file: $path");
                return false;
            }
        }

        return true;
    }

    public function delete_file_by_id(string $id, string $extension): bool
    {
        return $this->delete_file(File::from([
            'id' => $id,
            'extension' => $extension,
            'mime' => CONFIG['upload']['acceptedmimetypes'][$extension],
            'size' => 0
        ]));
    }

    public function ban_file(File $file, string $sha256, string|null $reason = null)
    {
        if (!$this->delete_file($file)) {
            return false;
        }

        if ($this->type === FileStorageType::Database) {
            $this->db->prepare('INSERT IGNORE INTO hash_bans(sha256, reason) VALUES (?,?)')
                ->execute([$sha256, $reason]);
            $this->db->prepare('INSERT INTO file_bans(id, hash_ban) VALUES (?,?)')
                ->execute([$file->id, $sha256]);
        } else if (!file_put_contents(CONFIG['moderation']['hashpath'], "$sha256 $reason" . PHP_EOL, FILE_APPEND)) {
            return false;
        }

        return true;
    }

    public function has_id(string $id, string|null $ext = null): bool
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->prepare('SELECT id FROM files WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } else if ($ext !== null) {
            return file_exists(CONFIG['files']['directory'] . "/$id.$ext");
        } else {
            return count(glob(CONFIG['files']['directory'] . "/$id.*")) > 0;
        }
    }

    public function is_sha256_banned(string $sha256): string|bool
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->prepare('SELECT reason FROM hash_bans WHERE sha256 = ?');
            $stmt->execute([$sha256]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return empty($row['reason']) ? true : $row['reason'];
            }
        } else if (file_exists(CONFIG['moderation']['hashpath'])) {
            $contents = file_get_contents(CONFIG['moderation']['hashpath']);
            $lines = explode(PHP_EOL, $contents);
            foreach ($lines as $line) {
                $parts = explode(" ", $line, 2);
                if ($parts[0] === $sha256) {
                    return empty($parts[1]) ? true : $parts[1];
                }
            }
        }

        return false;
    }

    public function count_pages(int $limit = 1): int
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT COUNT(id) AS all_files FROM files WHERE id NOT IN (SELECT id FROM file_bans)");
            $stmt->execute();

            return ceil(($stmt->fetch(PDO::FETCH_ASSOC)['all_files'] ?: 0) / $limit);
        } else {
            $file_count = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $file_count++;
                }
            }

            return ceil($file_count / $limit);
        }
    }

    public function count_registered_files(): int
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->query("SELECT COUNT(id) AS all_files FROM files WHERE id NOT IN (SELECT id FROM file_bans)");
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['all_files'] ?: 0;
        } else if ($this->type === FileStorageType::Json) {
            $count = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(CONFIG['metadata']['directory'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }

            return $count;
        } else {
            return $this->count_uploaded_files();
        }
    }

    public function count_uploaded_files(): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(CONFIG['files']['directory'], FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    public function get_type(): FileStorageType
    {
        return $this->type;
    }

    public function get_db(): PDO|null
    {
        return $this->db;
    }
}

define(
    "STORAGE",
    new FileMetadataStorage(
        match (CONFIG['storage']['type']) {
            'file' => FileStorageType::File,
            'json' => FileStorageType::Json,
            'database' => FileStorageType::Database,
            default => null
        }
    )
);