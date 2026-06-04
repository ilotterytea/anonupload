<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

function generate_snowflake_id(int $machineId = 1, int $epoch = 1609459200000): int
{
    static $last_timestamp = 0;
    static $sequence = 0;

    $machine_id_bits = 10;
    $sequence_bits = 12;

    $max_machine_id = (1 << $machine_id_bits) - 1;
    $max_sequence = (1 << $sequence_bits) - 1;

    if ($machineId < 0 || $machineId > $max_machine_id) {
        throw new InvalidArgumentException("Machine ID must be between 0 and $max_machine_id");
    }

    $timestamp = (int) floor(microtime(true) * 1000);

    if ($timestamp < $last_timestamp) {
        throw new RuntimeException("Clock moved backwards. Refusing to generate ID.");
    }

    if ($timestamp === $last_timestamp) {
        $sequence = ($sequence + 1) & $max_sequence;

        if ($sequence === 0) {
            do {
                $timestamp = (int) floor(microtime(true) * 1000);
            } while ($timestamp <= $last_timestamp);
        }
    } else {
        $sequence = 0;
    }

    $last_timestamp = $timestamp;

    return
        (($timestamp - $epoch) << ($machine_id_bits + $sequence_bits)) |
        ($machineId << $sequence_bits) |
        $sequence;
}


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

function convert_file(string $input_path, string $output_path)
{
    $input_ext = pathinfo($input_path, PATHINFO_EXTENSION);
    $output_ext = pathinfo($output_path, PATHINFO_EXTENSION);

    $input_mime = CONFIG['upload']['acceptedmimetypes'][$input_ext] ?? null;
    $output_mime = CONFIG['upload']['acceptedmimetypes'][$output_ext] ?? null;

    if (str_starts_with($input_mime, 'video/') && str_starts_with($output_mime, 'video/')) {
        $copy_supported = match (strtolower($output_ext)) {
            'mp4', 'mov', 'mkv', 'webm', 'avi' => true,
            default => false,
        };

        $cmd = $copy_supported
            ? sprintf('ffmpeg -i %s -c copy -y %s 2>&1', escapeshellarg($input_path), escapeshellarg($output_path))
            : sprintf('ffmpeg -i %s -c:v libx264 -c:a aac -y %s 2>&1', escapeshellarg($input_path), escapeshellarg($output_path));

        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            throw new RuntimeException("Conversion failed: " . implode("\n", $output));
        }
    } else {
        throw new RuntimeException("MIME types must be matched: $input_mime -> $output_mime");
    }
}

class FileMetadata
{
    public string|null $content_type;
    public string|null $password;
}

class BaseFile implements JsonSerializable
{
    public string $id, $extension, $mime;
    public int $size;
    public DateTime|null $uploaded_at = null;
    public string|null $password = null;
    public string|null $url = null, $thumbnail_url = null;
    public string|null $system_path = null;

    public function jsonSerialize(): mixed
    {
        $d = [
            'id' => $this->id,
            'extension' => $this->extension,
            'mime' => $this->mime,
            'size' => $this->size,
            'uploaded_at' => null,
            'urls' => [
                'download_url' => CONFIG['instance']['url'] . "/{$this->id}.{$this->extension}",
                'thumbnail_url' => $this->thumbnail_url,
                'raw_url' => $this->url,
                'deletion_url' => null
            ]
        ];

        if ($this->password && password_get_info($this->password)['algo'] === null) {
            $d['urls']['deletion_url'] = "/delete?id={$this->id}.{$this->extension}&key={$this->password}";
        }

        if ($this->uploaded_at !== null) {
            $d['uploaded_at'] = $this->uploaded_at->getTimestamp();
        }

        return $d;
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
        return MEMCACHED?->get($this->id);
    }

    public function set(mixed $data)
    {
        if ($this->id)
            MEMCACHED?->set($this->id, $data, $this->ttl);
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