<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'] . '/lib/file.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;

interface FileStorage
{
    public function has_file(string $name): bool;
    public function get_file(string $name): BaseFile|null;
    public function get_files(): array;
    public function get_random_file(): BaseFile|null;
    public function save_file(string $name, string $input_path, FileMetadata|null $metadata): BaseFile|null;
}

class S3FileStorage implements FileStorage
{
    private S3Client $s3;
    private string $bucket, $web_host;

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
        $this->web_host = $data['web_endpoint'];
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
                'Key' => $name
            ]);
        } catch (Exception $e) {
            return null;
        }

        $file = new BaseFile();

        $k = explode(".", $name, 2);

        $file->id = $k[0];
        $file->extension = $k[1];
        $file->mime = $result->get("ContentType");
        $file->size = $result->get("ContentLength");
        $file->uploaded_at = new DateTime($result->get("LastModified"));
        $file->url = "{$this->web_host}/$name";

        return $file;
    }

    public function get_files(): array
    {
        $result = $this->s3->listObjectsV2([
            'Bucket' => $this->bucket
        ]);

        $arr = [];

        foreach ($result['Contents'] as $content) {
            array_push($arr, $content['Key']);
        }

        return $arr;
    }

    public function get_random_file(): BaseFile|null
    {
        $files = $this->get_files();
        $c = count($files);
        if ($c == 0) {
            return null;
        }

        return $this->get_file($files[random_int(0, $c - 1)]);
    }

    public function save_file(string $name, string $input_path, FileMetadata|null $metadata = null): BaseFile|null
    {
        $m = [
            'Bucket' => $this->bucket,
            'Key' => $name,
        ];

        if ($metadata?->content_type) {
            $m['Params'] = [
                'ContentType' => $metadata->content_type
            ];
        }

        $uploader = new MultipartUploader($this->s3, $input_path, $m);

        $result = $uploader->upload();
        $key = $result->get("Key");

        $file = $this->get_file($key);
        if (!$file) {
            return null;
        }

        $file->url = "{$this->web_host}/$key";

        return $file;
    }
}

if (
    isset(
    CONFIG['s3']['access_key'],
    CONFIG['s3']['secret_key'],
    CONFIG['s3']['region'],
    CONFIG['s3']['bucket']
)
) {
    define('FILESTORAGE', new S3FileStorage(CONFIG['s3']));
}