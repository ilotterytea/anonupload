<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'] . '/lib/file.php';

use Aws\S3\S3Client;

interface FileStorage
{
    public function get_file(string $name): BaseFile|null;
    public function get_files(): array;
    public function get_random_file(): BaseFile|null;
}

class S3FileStorage implements FileStorage
{
    private S3Client $s3;
    private string $bucket;

    public function __construct($data)
    {
        $this->s3 = new S3Client($data);
        $this->bucket = $data['bucket'];
    }

    public function get_file(string $name): BaseFile|null
    {
        $result = $this->s3->headObject([
            'Bucket' => $this->bucket,
            'Key' => $name
        ]);

        $file = new BaseFile();

        $k = explode(".", $name, 2);

        $file->id = $k[0];
        $file->ext = $k[1];
        $file->mime = $result->get("ContentType");
        $file->size = $result->get("ContentLength");
        $file->uploaded_at = new DateTime($result->get("LastModified"));

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
}