<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/id.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;

interface FileStorage
{
    public function has_file(string $name): bool;
    public function get_file(string $name): BaseFile|null;
    public function get_files(): array;
    public function get_random_file(): BaseFile|null;
    public function save_file(string $name, string $input_path, FileMetadata|null $metadata): BaseFile|null;
    public function delete_file(string $name): bool;
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

        $file = new BaseFile();
        $file->id = $splitname->name;
        $file->extension = $splitname->extension;
        $file->system_path = "{$this->directory}/$name";
        $file->size = filesize($file->system_path);
        $file->mime = CONFIG['upload']['acceptedmimetypes'][$file->extension];
        $file->uploaded_at = (new DateTime())->setTimestamp(filemtime($file->system_path));
        $file->password = null;
        $file->url = "{$this->prefix}/{$file->id}.{$file->extension}";
        $file->thumbnail_url = null;

        return $file;
    }

    public function get_files(): array
    {
        $iter = new DirectoryIterator($this->directory);
        $count = 0;
        $arr = [];

        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $count++;
            array_push($arr, $file->getFilename());
        }

        return [
            'count' => $count,
            'filenames' => $arr
        ];
    }

    public function get_random_file(): BaseFile|null
    {
        $files = $this->get_files();
        return $this->get_file($files['filenames'][random_int(0, $files['count'] - 1)]);
    }

    public function save_file(string $name, string $input_path, FileMetadata|null $metadata = null): BaseFile|null
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
}

class SQLFileStorage extends LocalFileStorage
{
    private PDO $db;

    public function __construct(string $local_directory, string $prefix, string $database_url, string $database_user, string $database_password)
    {
        parent::__construct($local_directory, $prefix);
        $this->db = new PDO($database_url, $database_user, $database_password);
    }

    public function has_file(string $name): bool
    {
        $name = new SplitFilename($name);

        $stmt = $this->db->prepare('SELECT id FROM files WHERE id = ? AND extension = ?');
        $stmt->execute([$name->name, $name->extension]);

        return $stmt->rowCount() > 0;
    }

    public function get_file(string $name): BaseFile|null
    {
        $name = new SplitFilename($name);

        $stmt = $this->db->prepare('SELECT fm.*, f.*
        FROM files f
        LEFT JOIN file_metadata fm ON fm.id = f.id
        WHERE f.id = ? AND f.extension = ?
        ');
        $stmt->execute([$name->name, $name->extension]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$res) {
            return null;
        }

        $file = new BaseFile();
        $file->id = $res['id'];
        $file->extension = $res['extension'];
        $file->mime = $res['mime'];
        $file->size = $res['size'];
        $file->password = $res['password'] ?? null;

        if (isset($res['uploaded_at'])) {
            $file->uploaded_at = new DateTime();

            if (is_numeric($res['uploaded_at'])) {
                $file->uploaded_at->setTimestamp(intval($res['uploaded_at']));
            } elseif (isset($res['uploaded_at']['date'])) {
                $file->uploaded_at->setTimestamp(strtotime($res['uploaded_at']['date']));
            } else {
                $file->uploaded_at->setTimestamp(strtotime($res['uploaded_at']));
            }
        } else {
            $file->uploaded_at = null;
        }

        $file->url = "{$this->prefix}/{$file->id}.{$file->extension}";
        $file->system_path = null;
        $file->thumbnail_url = null;

        return $file;
    }

    public function get_files(): array
    {
        $stmt = $this->db->query("SELECT CONCAT(f.id, '.', f.extension) AS file_name FROM files f ORDER BY uploaded_at ASC");
        $stmt->execute();

        $count = 0;
        $arr = [];

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            array_push($arr, $res['file_name']);
        }

        return [
            'count' => $count,
            'filenames' => $arr
        ];
    }

    public function get_random_file(): BaseFile|null
    {
        $stmt = $this->db->query("SELECT CONCAT(f.id, '.', f.extension) AS file_name FROM files f ORDER BY rand() LIMIT 1");
        $stmt->execute();

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $res ? $this->get_file($res['file_name']) : null;
    }

    public function save_file(string $name, string $input_path, FileMetadata|null $metadata = null): BaseFile|null
    {
        $base = parent::save_file($name, $input_path, $metadata);
        if (!$base) {
            return null;
        }

        if ($metadata?->password) {
            $base->password = password_hash($metadata?->password, PASSWORD_DEFAULT);
        }

        $query = null;
        $params = null;

        if ($this->has_file($name)) {
            $query = 'INSERT INTO files(id, mime, extension, `size`, `password`, uploaded_at) VALUES (?, ?, ?, ?, ?, ?)';
            $params = [
                $base->id,
                $base->mime,
                $base->extension,
                $base->size,
                $base->password,
                $base->uploaded_at
            ];
        } else {
            $query = 'UPDATE files SET `size` = ?, `uploaded_at` = ? WHERE id = ? AND extension = ?';
            $params = [
                $base->size,
                $base->uploaded_at,
                $base->id,
                $base->extension
            ];
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $this->get_file($name);
    }

    public function delete_file(string $name): bool
    {
        if (!parent::delete_file($name)) {
            return false;
        }

        $name = new SplitFilename($name);

        $this->db->prepare('DELETE FROM files WHERE id = ? AND extension = ?')
            ->execute([$name->name, $name->extension]);

        return true;
    }
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

        if ($metadata = $result->get("Metadata")) {
            $file->password = $metadata['Password'] ?? null;
        }

        return $file;
    }

    public function get_files(): array
    {
        $result = $this->s3->listObjectsV2([
            'Bucket' => $this->bucket
        ]);

        $arr = [
            'count' => $result['KeyCount'],
            'filenames' => []
        ];

        foreach ($result['Contents'] as $content) {
            array_push($arr['filenames'], $content['Key']);
        }

        return $arr;
    }

    public function get_random_file(): BaseFile|null
    {
        $files = $this->get_files();
        if ($files['count'] == 0) {
            return null;
        }

        return $this->get_file($files['filenames'][random_int(0, $files['count'] - 1)]);
    }

    public function save_file(string $name, string $input_path, FileMetadata|null $metadata = null): BaseFile|null
    {
        $m = [
            'Bucket' => $this->bucket,
            'Key' => $name,
            'Params' => []
        ];

        if ($metadata?->content_type) {
            $m['Params']['ContentType'] = $metadata->content_type;
        }

        if ($metadata?->password) {
            $m['Params']['Metadata'] = [
                'Password' => password_hash($metadata?->password, PASSWORD_DEFAULT)
            ];
        }

        $uploader = new MultipartUploader($this->s3, $input_path, $m);

        $result = $uploader->upload();
        $key = $result->get("Key");

        $file = $this->get_file($key);
        if (!$file) {
            return null;
        }

        $file->password = $metadata?->password;
        $file->url = "{$this->web_host}/$key";

        return $file;
    }

    public function delete_file(string $name): bool
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $name
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}

define('FILESTORAGE', match (CONFIG['storage']['type']) {
    'local' => new LocalFileStorage(CONFIG['storage']['directory'], CONFIG['storage']['prefix']),
    'sql' => new SQLFileStorage(
        CONFIG['storage']['directory'],
        CONFIG['storage']['prefix'],
        CONFIG['storage']['url'],
        CONFIG['storage']['user'],
        CONFIG['storage']['pass'],
    ),
    's3' => new S3FileStorage(CONFIG['storage']),
    default => null
});