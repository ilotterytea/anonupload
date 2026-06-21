<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/vendor/autoload.php";
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
    if (str_starts_with($mimetype, 'image/')) {
        return verify_image($file_path);
    }

    if (str_starts_with($mimetype, 'video/') || str_starts_with($mimetype, 'audio/')) {
        return verify_media($file_path);
    }

    if ($mimetype === 'application/x-shockwave-flash') {
        return is_swf_file($file_path);
    }

    throw new RuntimeException("Illegal type for MIME verifications: $mimetype");
}

function verify_image(string $file_path): bool
{
    try {
        $img = new Imagick($file_path);
        $format = $img->getImageFormat();
        return !empty($format);
    } catch (Exception $e) {
        return false;
    }
}

function verify_media(string $file_path): bool
{
    try {
        $getID3 = new getID3();
        $info = $getID3->analyze($file_path);

        if (!empty($info['error'])) {
            return false;
        }

        return isset($info['fileformat']) && $info['fileformat'] !== '';
    } catch (Exception $e) {
        return false;
    }
}


function strip_exif(string $file_path): bool
{
    try {
        $img = new Imagick($file_path);

        $img->stripImage();
        $img->writeImage($file_path);

        $img->clear();
        $img->destroy();

        return true;
    } catch (Exception $e) {
        return false;
    }
}

class FileOptions
{
    public string|null $content_type;
}

class FileMetadata
{
    public int|null $width = null, $height = null, $duration = null, $line_count = null;

    public static function from_file_path(string $input_path, string $mime): FileMetadata|null
    {
        $m = null;
        if (str_starts_with($mime, 'image/')) {
            [$w, $h] = getimagesize($input_path);

            $m = new FileMetadata();
            $m->width = intval($w);
            $m->height = intval($h);
        } else if (str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/')) {
            $getID3 = new getID3();
            $info = $getID3->analyze($input_path);
            $m = new FileMetadata();

            if (isset($info['video']['resolution_x'])) {
                $m->width = (int) $info['video']['resolution_x'];
            }

            if (isset($info['video']['resolution_y'])) {
                $m->height = (int) $info['video']['resolution_y'];
            }

            if (isset($info['playtime_seconds'])) {
                $m->duration = (int) round($info['playtime_seconds']);
            }
        } else if (str_starts_with($mime, 'text/')) {
            $m = new FileMetadata();

            $file = new SplFileObject($input_path);
            $file->seek(PHP_INT_MAX);

            $m->line_count = $file->key() + 1;
        }

        return $m;
    }
}

class BaseFile implements JsonSerializable
{
    public string $name, $extension, $mime, $hash;
    public int $size;
    public string|null $path = null, $original_filename = null;
    public FileMetadata|null $metadata = null;

    public static function from_array(array $row): BaseFile
    {
        $f = new BaseFile();
        $f->name = $row['id'];
        $f->extension = $row['extension'];
        $f->mime = $row['mime'];
        $f->size = $row['size'];
        $f->hash = $row['hash'];
        $f->original_filename = $row['original_filename'] ?? null;

        if (
            array_key_exists('width', $row) ||
            array_key_exists('height', $row) ||
            array_key_exists('duration', $row) ||
            array_key_exists('line_count', $row)
        ) {
            $m = new FileMetadata();
            $m->width = $row['width'] ?? null;
            $m->height = $row['height'] ?? null;
            $m->duration = $row['duration'] ?? null;
            $m->line_count = $row['line_count'] ?? null;
            $f->metadata = $m;
        }

        return $f;
    }

    public function file_name(): string
    {
        return $this->original_filename ?? "{$this->name}.{$this->extension}";
    }

    public function raw_url(): string|null
    {
        return match (CONFIG['storage']['type']) {
            "local" => sprintf("%s%s/%s.%s", CONFIG['instance']['url'], CONFIG['storage']['prefix'], $this->name, $this->extension),
            "s3" => sprintf("%s/%s.%s", CONFIG['storage']['prefix'], $this->name, $this->extension),
            default => null
        };
    }

    public function thumbnail_url(): string|null
    {
        return match (CONFIG['thumbnails']['type']) {
            "local" => sprintf("%s%s/%s.%s", CONFIG['instance']['url'], CONFIG['thumbnails']['prefix'], $this->name, CONFIG['thumbnails']['extension']),
            "s3" => sprintf("%s/%s.%s", CONFIG['thumbnails']['prefix'], $this->name, CONFIG['thumbnails']['extension']),
            default => null
        };
    }

    public function is_flash(): bool
    {
        return !empty(CONFIG['driver']['ruffle']) && $this->mime === 'application/x-shockwave-flash';
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->name,
            'name' => $this->original_filename,
            'extension' => $this->extension,
            'mime' => $this->mime,
            'size' => $this->size,
            'hash' => $this->hash,
            'urls' => [
                'download_url' => $this->raw_url(),
                'thumbnail_url' => $this->thumbnail_url()
            ]
        ];
    }
}

class Post implements JsonSerializable
{
    public string $id;
    public array $attachments = [];

    public DateTime|null $uploaded_at = null, $expires_at = null;
    public string|null $password = null, $description = null;
    public int|null $views = null;

    public static function from_array(array $res): Post
    {
        $o = new Post();
        $o->id = $res['id'];
        $o->password = $res['password'] ?? null;
        $o->description = $res['description'] ?? null;

        if (CONFIG['views']['enabled']) {
            $o->views = $res['views'] ?? null;
        }

        if (isset($res['uploaded_at'])) {
            $o->uploaded_at = new DateTime();

            if (is_numeric($res['uploaded_at'])) {
                $o->uploaded_at->setTimestamp(intval($res['uploaded_at']));
            } elseif (isset($res['uploaded_at']['date'])) {
                $o->uploaded_at->setTimestamp(strtotime($res['uploaded_at']['date']));
            } else {
                $o->uploaded_at->setTimestamp(strtotime($res['uploaded_at']));
            }
        } else {
            $o->uploaded_at = null;
        }

        if (isset($res['expires_at'])) {
            $o->expires_at = new DateTime();

            if (is_numeric($res['expires_at'])) {
                $o->expires_at->setTimestamp(intval($res['expires_at']));
            } elseif (isset($res['expires_at']['date'])) {
                $o->expires_at->setTimestamp(strtotime($res['expires_at']['date']));
            } else {
                $o->expires_at->setTimestamp(strtotime($res['expires_at']));
            }
        } else {
            $o->expires_at = null;
        }

        return $o;
    }

    public function url(): string
    {
        return CONFIG['instance']['url'] . "/{$this->id}";
    }

    public function jsonSerialize(): mixed
    {
        $d = [
            'id' => $this->id,
            'uploaded_at' => null,
            'urls' => [
                'download_url' => $this->url(),
                'deletion_url' => null
            ],
            'attachments' => $this->attachments
        ];

        if ($this->views) {
            $d['views'] = $this->views;
        }

        if ($this->password && password_get_info($this->password)['algo'] === null) {
            $d['urls']['deletion_url'] = "/delete?id={$this->id}&key={$this->password}";
        }

        if ($this->uploaded_at !== null) {
            $d['uploaded_at'] = $this->uploaded_at->getTimestamp();
        }

        return $d;
    }

    public function is_flash(): bool
    {
        $is_flash = false;
        foreach ($this->attachments as $a) {
            $is_flash = $a->is_flash();
            if ($is_flash) {
                break;
            }
        }
        return !empty(CONFIG['driver']['ruffle']) && $is_flash;
    }

    public function name(): string
    {
        if ($s = $this->single_attachment()) {
            return "{$this->id}.{$s->extension}";
        }
        return $this->id;
    }

    public function mime(): string
    {
        return $this->single_attachment()?->mime ?? "application/x-multi-upload";
    }

    public function thumbnail_url(): string
    {
        return $this->single_attachment()?->thumbnail_url() ?? (CONFIG['instance']['url'] . "/static/img/default/multi-upload.webp");
    }

    public function single_attachment(): BaseFile|null
    {
        return count($this->attachments) === 1 ? $this->attachments[0] : null;
    }
}

class FileTrackStatus
{
    public string|null $id;
    public int $ttl;

    public function __construct(string $id)
    {
        $this->id = $id == "0" ? null : "anonupload_track_$id";
        $this->ttl = CONFIG['files']['track_ttl'];
    }

    public function exists(): bool
    {
        $r = $this->get();
        return $r !== false && $r !== null;
    }

    public function get(): mixed
    {
        return json_decode(MEMCACHED?->get($this->id), true);
    }

    public function set(mixed $data)
    {
        if ($this->id)
            MEMCACHED?->set($this->id, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $this->ttl);
    }

    public function send(string $stage, string|null $message = null, mixed $data = null)
    {
        $this->set([
            'stage' => $stage,
            'message' => $message,
            'data' => $data
        ]);
    }
}