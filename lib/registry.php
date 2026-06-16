<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/id.php";

interface FileRegistry
{
    public function get_files(): array;
    public function get_posts_by_hash(string $hash): array;

    public function get_file_by_hash(string $hash): BaseFile|null;
    public function get_file_by_post_id(string $post_id): ExtendedFile|null;
    public function get_file(string $name): ExtendedFile|null;

    public function get_random_file(): ExtendedFile|null;
    public function put_file(ExtendedFile $file): ExtendedFile|null;
    public function delete_post(ExtendedFile $file): bool;
    public function get_stats(): array|null;

    public function has_storage_data(string $id): bool;
    public function get_storage_data(string $id): mixed;
    public function put_storage_data(string $id, mixed $data): bool;
}

class SQLFileRegistry implements FileRegistry
{
    private PDO $db;

    public function __construct(string $url, string $user, string $password)
    {
        $this->db = new PDO($url, $user, $password);
        $sql = file_get_contents("{$_SERVER['DOCUMENT_ROOT']}/database.sql");
        $this->db->exec($sql);
    }

    public function get_file_by_post_id(string $post_id): ExtendedFile|null
    {
        $name = new SplitFilename($post_id);

        $stmt = $this->db->prepare('SELECT
        f.id AS file_id, f.mime, f.extension, f.size, f.hash,
        fm.*, p.*
        FROM posts p
        JOIN post_attachments pa ON pa.post_id = p.id
        JOIN files f ON f.id = pa.file_id
        LEFT JOIN file_metadata fm ON fm.id = f.id
        WHERE p.id = ? AND f.extension = ?
        ');
        $stmt->execute([$name->name, $name->extension]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $res ? ExtendedFile::from_array($res) : null;
    }

    public function get_file(string $name): ExtendedFile|null
    {
        $name = new SplitFilename($name);

        $stmt = $this->db->prepare('SELECT
        f.id AS file_id, f.mime, f.extension, f.size, f.hash,
        fm.*, p.*
        FROM posts p
        JOIN post_attachments pa ON pa.post_id = p.id
        JOIN files f ON f.id = pa.file_id
        LEFT JOIN file_metadata fm ON fm.id = f.id
        WHERE p.id = ? AND f.extension = ?
        ');
        $stmt->execute([$name->name, $name->extension]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $res ? ExtendedFile::from_array($res) : null;
    }

    public function get_file_by_hash(string $hash): BaseFile|null
    {
        $stmt = $this->db->prepare('SELECT * FROM files WHERE hash = ?');
        $stmt->execute([$hash]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? BaseFile::from_array($res) : null;
    }

    public function get_random_file(): ExtendedFile|null
    {
        $stmt = $this->db->query("SELECT
            CONCAT(p.id, '.', f.extension) AS post_name
        FROM posts p
        JOIN post_attachments pa ON pa.post_id = p.id
        JOIN files f ON f.id = pa.file_id
        ORDER BY rand()
        LIMIT 1");
        $stmt->execute();

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $res ? $this->get_file($res['post_name']) : null;
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

    public function get_posts_by_hash(string $hash): array
    {
        $stmt = $this->db->prepare("SELECT
            CONCAT(p.id, '.', f.extension) AS post_name
        FROM posts p
        JOIN post_attachments pa ON pa.post_id = p.id
        JOIN files f ON f.id = pa.file_id
        WHERE f.hash = ?");
        $stmt->execute([$hash]);

        $count = 0;
        $arr = [];

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            array_push($arr, $res['post_name']);
        }

        return [
            'count' => $count,
            'filenames' => $arr
        ];
    }

    public function put_file(ExtendedFile $file): ExtendedFile|null
    {
        if ($file?->password) {
            $file->password = password_hash($file?->password, PASSWORD_DEFAULT);
        }

        // saving file data
        $stmt = $this->db->prepare("INSERT IGNORE INTO
            files(id, mime, extension, size, hash)
            VALUES(:id, :mime, :ext, :size, :hash)
        ");
        $stmt->execute([
            ':id' => $file->name,
            ':mime' => $file->mime,
            ':ext' => $file->extension,
            ':size' => $file->size,
            ':hash' => $file->hash
        ]);

        // saving post data
        $stmt = $this->db->prepare("INSERT INTO
            posts(id, uploaded_at, password)
            VALUES(:id, :uat, :password)
        ");
        $stmt->execute([
            ':id' => $file->id,
            ':uat' => $file->uploaded_at->format('Y-m-d H:i:s'),
            ':password' => $file->password
        ]);

        // linking file to the post
        $stmt = $this->db->prepare("INSERT INTO
            post_attachments(post_id, file_id)
            VALUES(:pid, :fid)
        ");
        $stmt->execute([
            ':pid' => $file->id,
            ':fid' => $file->name
        ]);

        return $this->get_file_by_post_id($file->name());
    }

    public function get_stats(): array|null
    {
        // -- basic info
        $stmt = $this->db->query("SELECT COUNT(*) AS serving_files, SUM(size) AS active_content, AVG(size) AS average_file_size
                FROM files
            ");

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            return null;
        }

        // calculating the count of future files
        $serving_future_files = null;
        if (CONFIG['stats']['disk_size'] > 0) {
            $size = CONFIG['stats']['disk_size'];
            $serving_future_files = floor($size / $res['average_file_size']);
        }
        $res['serving_future_files'] = $serving_future_files;

        // -- timeline
        $stmt = $this->db->query("SELECT YEAR(uploaded_at) AS year, QUARTER(uploaded_at) AS quarter, COUNT(*) AS file_count
                FROM posts
                WHERE uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                GROUP BY YEAR(uploaded_at), QUARTER(uploaded_at)
                ORDER BY year, quarter
            ");
        $res['timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // -- the most viewed files
        $stmt = $this->db->query("SELECT f.id AS file_id, p.id, f.extension, f.mime, f.size, f.hash
        FROM posts p
        JOIN post_attachments pa ON pa.post_id = p.id
        JOIN files f ON f.id = pa.file_id
        ORDER BY views DESC LIMIT 5");

        $files = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($files, ExtendedFile::from_array($row));
        }
        $res['most_viewed'] = $files;

        return $res;
    }

    public function delete_post(ExtendedFile $file): bool
    {
        $this->db->prepare('DELETE FROM posts WHERE id = ?')
            ->execute([$file->id]);

        $similar_posts = $this->get_posts_by_hash($file->hash);
        if ($similar_posts['count'] == 0 && defined("FILESTORAGE")) {
            if (!FILESTORAGE->delete_file("{$file->name}.{$file->extension}")) {
                return false;
            }

            $this->db->prepare('DELETE FROM files WHERE id = ?')
                ->execute([$file->name]);
        }

        return true;
    }

    public function has_storage_data(string $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM storages WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() >= 1;
    }

    public function get_storage_data(string $id): mixed
    {
        $stmt = $this->db->prepare('SELECT data FROM storages WHERE id = ?');
        $stmt->execute([$id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return json_decode($row['data'], true);
        }

        return null;
    }

    public function put_storage_data(string $id, mixed $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO storages(id, data)
        VALUES (:id, :data)
        ON DUPLICATE KEY UPDATE
            data = :data');
        $stmt->execute([':id' => $id, ':data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        return true;
    }
}

define('FILEREGISTRY', match (CONFIG['metadata']['type']) {
    'sql' => new SQLFileRegistry(
        CONFIG['metadata']['url'],
        CONFIG['metadata']['user'],
        CONFIG['metadata']['pass'],
    ),
    default => null
});