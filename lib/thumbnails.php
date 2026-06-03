<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';

interface Thumbnailer
{
    public function get_thumbnail_root(): string;
    public function get_thumbnail_extension(): string;
    public function generate_thumbnail(string $input_path, int $width, int $height): string;
    public function generate_thumbnails(mixed $data): array;
}

class LocalThumbnailer implements Thumbnailer
{
    private string $directory, $prefix, $extension;

    public function __construct(string $directory, string $prefix)
    {
        $this->directory = $directory;
        $this->prefix = $prefix;
        $this->extension = "webp";

        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true)) {
            throw new RuntimeException("Failed to create directory for thumbnails: {$this->directory}");
        } else if (!IMAGEMAGICK_COMMAND) {
            throw new RuntimeException("No ImageMagick installed");
        }
    }

    public function get_thumbnail_root(): string
    {
        return $this->prefix;
    }

    public function get_thumbnail_extension(): string
    {
        return $this->extension;
    }

    public function generate_thumbnail(string $input_path, int $width, int $height): string
    {
        $code = -1;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (false === $ext = array_search($finfo->file($input_path), CONFIG["upload"]["acceptedmimetypes"], true)) {
            throw new RuntimeException("Invalid file format.");
        }

        if (!array_key_exists($ext, CONFIG['upload']['acceptedmimetypes'])) {
            throw new RuntimeException("Unknown extension: $ext");
        }

        $id = pathinfo($input_path, PATHINFO_FILENAME);
        $dst_path = "{$this->directory}/$id.{$this->extension}";

        $mime = CONFIG['upload']['acceptedmimetypes'][$ext];
        if (str_starts_with($mime, 'image/')) {
            $code = $this->generate_image_thumbnail($input_path, $dst_path, $width, $height);
        } else if (str_starts_with($mime, 'video/')) {
            $code = $this->generate_video_thumbnail($input_path, $dst_path, $width, $height);
        }

        if ($code !== 0) {
            throw new RuntimeException("Program returned exit code $code");
        }

        return $dst_path;
    }

    public function generate_thumbnails(mixed $data): array
    {
        $paths = [];

        foreach ($data as $f) {
            if (!isset($f['input_path'], $f['width'], $f['height'])) {
                throw new RuntimeException("Files must have input_path, width, height");
            }

            $id = pathinfo($f['input_path'], PATHINFO_FILENAME);
            $paths[$id] = $this->generate_thumbnail($f['input_path'], $f['width'], $f['height']);
        }

        return $paths;
    }

    private function generate_image_thumbnail(string $src_path, string $dst_path, int $width, int $height): int
    {
        $input_path = escapeshellarg($src_path);
        $output_path = escapeshellarg($dst_path);

        $result_code = null;

        exec(command: IMAGEMAGICK_COMMAND['convert'] . " $input_path -resize {$width}x{$height} -loop 0 $output_path", result_code: $result_code);

        return $result_code;
    }

    private function generate_video_thumbnail(string $src_path, string $dst_path, int $width, int $height): int
    {
        $folder_path = "/tmp/anonuploadthumbnail_" . bin2hex(random_bytes(8));
        if (!is_dir($folder_path) && !mkdir($folder_path, 0770, true)) {
            throw new RuntimeException("Failed to create a temporary folder: $folder_path");
        }

        $input_path = escapeshellarg($src_path);
        $output_path = escapeshellarg($dst_path);

        $ffmpeg_command = "ffmpeg -i $input_path -vf \"fps=4,scale=320:-1:flags=lanczos\" -t 10 $folder_path/frames_%04d.png 2>&1";
        $magick_command = IMAGEMAGICK_COMMAND['convert'] . " $folder_path/frames_*.png -loop 0 -delay 60 -resize {$width}x{$height} $output_path 2>&1";

        exec($ffmpeg_command, $ffmpeg_output, $ffmpeg_result_code);
        exec($magick_command, $magick_output, $magick_result_code);

        array_map('unlink', array_filter((array) glob("$folder_path/*.*")));
        rmdir($folder_path);

        return $ffmpeg_result_code === 0 && $magick_result_code === 0 ? 0 : -1;
    }
}

class S3ProxyThumbnailer implements Thumbnailer
{
    private string $url, $bucket, $output_bucket, $extension, $prefix;
    private string|null $authorization_key;

    public function __construct(string $url, string $prefix, string $bucket, string $output_bucket, string|null $authorization_key = null)
    {
        $this->url = $url;
        $this->bucket = $bucket;
        $this->output_bucket = $output_bucket;
        $this->authorization_key = $authorization_key;
        $this->prefix = $prefix;
        $this->extension = "webp";
    }

    public function get_thumbnail_root(): string
    {
        return $this->prefix;
    }

    public function get_thumbnail_extension(): string
    {
        return $this->extension;
    }

    public function generate_thumbnail(string $input_path, int $width, int $height): string
    {
        $headers = [
            'Content-Type: application/json'
        ];

        if ($this->authorization_key) {
            array_push($headers, "Authorization: {$this->authorization_key}");
        }

        $ch = curl_init("{$this->url}/generate");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            [
                'key' => basename($input_path),
                'input_bucket' => $this->bucket,
                'output_bucket' => $this->output_bucket,
                'width' => $width,
                'height' => $height,
                'extension' => $this->extension
            ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code !== 202) {
            throw new RuntimeException("Failed to generate thumbnail for $input_path ($code)");
        }

        return json_decode($response, true)[0];
    }

    public function generate_thumbnails(mixed $data): array
    {
        $payload = [];
        foreach ($data as $f) {
            if (!isset($f['input_path'], $f['width'], $f['height'])) {
                throw new RuntimeException("Files must have input_path, width, height");
            }
            array_push($payload, [
                'key' => basename($f['input_path']),
                'input_bucket' => $this->bucket,
                'output_bucket' => $this->output_bucket,
                'width' => $f['width'],
                'height' => $f['height'],
                'extension' => $this->extension
            ]);
        }

        $headers = [
            'Content-Type: application/json'
        ];

        if ($this->authorization_key) {
            array_push($headers, "Authorization: {$this->authorization_key}");
        }

        $ch = curl_init("{$this->url}/generate");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code !== 202) {
            throw new RuntimeException("Failed to generate thumbnails ($code)");
        }

        return json_decode($response, true);
    }
}

define("THUMBNAILER", match (true) {
    CONFIG['thumbnails']['type'] === "s3" && CONFIG['storage']['type'] === "s3" => new S3ProxyThumbnailer(
        CONFIG['thumbnails']['url'],
        CONFIG['thumbnails']['prefix'],
        CONFIG['thumbnails']['bucket'],
        CONFIG['thumbnails']['output_bucket'],
        CONFIG['thumbnails']['authorization_key']
    ),
    CONFIG['thumbnails']['type'] === "local" && CONFIG['storage']['type'] !== "s3" => new LocalThumbnailer(CONFIG['thumbnails']['directory'], CONFIG['thumbnails']['prefix']),
    default => null
});