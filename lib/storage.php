<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/id.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/thumbnails.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;

interface FileStorage
{
    public function has_file(string $name): bool;
    public function get_file(string $name): BaseFile|null;
    public function save_file(string $name, string $input_path, FileOptions|null $metadata): BaseFile|null;
    public function delete_file(string $name): bool;
    public function ensure_file(BaseFile &$file): bool;
}

class LocalFileStorage implements FileStorage
{
    protected string $directory, $prefix;

    public function __construct(string $directory, string $prefix)
    {
        $this->directory = $directory;
        $this->prefix = $prefix;
    }

    public function has_file(string $name): bool
    {
        return is_file("{$this->directory}/$name");
    }

    public function get_file(string $name): BaseFile|null
    {
        if (!$this->has_file($name)) {
            return null;
        }

        $splitname = new SplitFilename($name);
        $path = "{$this->directory}/$name";

        $file = new BaseFile();
        $file->name = $splitname->name;
        $file->extension = $splitname->extension;
        $file->path = "local://$path";
        $file->size = filesize($path);
        $file->mime = CONFIG['upload']['acceptedmimetypes'][$file->extension];

        return $file;
    }

    public function save_file(string $name, string $input_path, FileOptions|null $options = null): BaseFile|null
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true)) {
            throw new RuntimeException("Failed to create a directory for files: {$this->directory}");
        }

        if (!move_uploaded_file($input_path, "{$this->directory}/$name")) {
            return null;
        }
        return $this->get_file($name);
    }

    public function delete_file(string $name): bool
    {
        $path = "{$this->directory}/$name";
        return is_file($path) && unlink($path);
    }

    public function ensure_file(BaseFile &$file): bool
    {
        $f = $this->get_file("{$file->name}.{$file->extension}");
        if (!$f) {
            return false;
        }

        $file->path = $f->path;
        return true;
    }
}

class S3FileStorage implements FileStorage
{
    private S3Client $s3;
    private string $bucket, $directory;

    public function __construct($data)
    {
        $this->s3 = new S3Client([
            'version' => $data['version'] ?? 'latest',
            'region' => $data['region'],
            'credentials' => [
                'key' => $data['access_key'],
                'secret' => $data['secret_key']
            ],
            'endpoint' => $data['endpoint'],
            'use_path_style_endpoint' => $data['use_path_style_endpoint'] ?? false
        ]);
        $this->bucket = $data['bucket'];
        $this->directory = $data['directory'];
    }

    public function has_file(string $name): bool
    {
        return $this->get_file($name) !== null;
    }

    public function get_file(string $name): BaseFile|null
    {
        try {
            $result = $this->s3->headObject([
                'Bucket' => $this->bucket,
                'Key' => "{$this->directory}/$name"
            ]);
        } catch (Exception $e) {
            return null;
        }

        $file = new BaseFile();

        $k = explode(".", $name, 2);

        $file->name = $k[0];
        $file->extension = $k[1];
        $file->mime = $result->get("ContentType");
        $file->size = $result->get("ContentLength");
        $file->path = "s3:///$name";

        return $file;
    }

    public function save_file(string $name, string $input_path, FileOptions|null $options = null): BaseFile|null
    {
        $m = [
            'Bucket' => $this->bucket,
            'Key' => "{$this->directory}/$name",
            'Params' => []
        ];

        if ($options?->content_type) {
            $m['Params']['ContentType'] = $options->content_type;
        }

        $uploader = new MultipartUploader($this->s3, $input_path, $m);

        $uploader->upload();

        $file = $this->get_file($name);
        if (!$file) {
            return null;
        }

        return $file;
    }

    public function delete_file(string $name): bool
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => "{$this->directory}/$name"
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function ensure_file(BaseFile &$file): bool
    {
        $f = $this->get_file("{$file->name}.{$file->extension}");
        if (!$f) {
            return false;
        }

        $file->path = $f->path;
        return true;
    }
}

define('FILESTORAGE', match (CONFIG['storage']['type']) {
    'local' => new LocalFileStorage(CONFIG['storage']['directory'], CONFIG['storage']['prefix']),
    's3' => new S3FileStorage(CONFIG['storage']),
    default => null
});