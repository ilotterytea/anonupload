<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/id.php";

interface FileRegistry
{
    public function get_files(): array;

    public function get_posts_by_hash(string $hash): array;

    public function has_post(string $id): bool;
    public function get_post(string $id): Post|null;
    public function put_post(Post $post): bool;
    public function delete_post(Post $file): bool;
    public function attach_to_post(Post &$post, BaseFile $file): bool;
    public function get_random_post(): Post|null;

    public function get_file_by_hash(string $hash): BaseFile|null;

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

    public function has_post(string $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function get_post(string $id): Post|null
    {
        $name = new SplitFilename($id);

        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$name->name]);

        if ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $post = Post::from_array($res);

            // fetching attachments
            $stmt = $this->db->prepare('SELECT
                fm.*, f.*, pa.original_filename
            FROM files f
            JOIN post_attachments pa ON pa.post_id = ?
            LEFT JOIN file_metadata fm ON fm.id = f.id
            WHERE f.id = pa.file_id
            ');
            $stmt->execute([$post->id]);

            while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($post->attachments, BaseFile::from_array($res));
            }

            if ($post->expires_at && $post->expires_at->getTimestamp() <= time()) {
                if (!$this->delete_post($post)) {
                    throw new RuntimeException("Failed to delete expired post");
                }
                return null;
            }

            return $post;
        }

        return null;
    }

    public function get_file_by_hash(string $hash): BaseFile|null
    {
        $stmt = $this->db->prepare('SELECT * FROM files WHERE hash = ?');
        $stmt->execute([$hash]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? BaseFile::from_array($res) : null;
    }

    public function get_random_post(): Post|null
    {
        $stmt = $this->db->query("SELECT id FROM posts ORDER BY rand() LIMIT 1");
        $stmt->execute();

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $res ? $this->get_post($res['id']) : null;
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

    public function put_post(Post $post): bool
    {
        $password = $post->password ? password_hash($post->password, PASSWORD_DEFAULT) : null;

        // saving post data
        $stmt = $this->db->prepare("INSERT INTO
            posts(id, uploaded_at, expires_at, description, password)
            VALUES(:id, :uat, :eat, :des, :password)
            ON DUPLICATE KEY UPDATE
            views = :vc
        ");
        $stmt->execute([
            ':id' => $post->id,
            ':uat' => $post->uploaded_at?->format('Y-m-d H:i:s'),
            ':eat' => $post->expires_at?->format('Y-m-d H:i:s'),
            ':des' => $post->description,
            ':password' => $password,
            ':vc' => $post->views ?? 0
        ]);

        return true;
    }

    public function attach_to_post(Post &$post, BaseFile $file): bool
    {
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

        if ($file->metadata) {
            $stmt = $this->db->prepare("INSERT IGNORE INTO
                file_metadata(id, width, height, duration, line_count)
                VALUES(:id, :w, :h, :d, :l)
            ");
            $stmt->execute([
                ':id' => $file->name,
                ':w' => $file->metadata->width,
                ':h' => $file->metadata->height,
                ':d' => $file->metadata->duration,
                ':l' => $file->metadata->line_count
            ]);
        }

        $stmt = $this->db->prepare("INSERT INTO
                post_attachments(post_id, file_id, original_filename)
                VALUES(:pid, :fid, :ofn)
            ");
        $stmt->execute([
            ':pid' => $post->id,
            ':fid' => $file->name,
            ':ofn' => $file->original_filename
        ]);

        array_push($post->attachments, $file);

        return true;
    }

    public function get_stats(): array|null
    {
        if ($cached_stats = MEMCACHED?->get(CONFIG['instance']['id'] . "_stats")) {
            $res = json_decode($cached_stats, true);

            $files = [];
            foreach ($res['most_viewed'] as $row) {
                array_push($files, Post::from_array($row));
            }
            $res['most_viewed'] = $files;

            return $res;
        }

        // -- basic info
        $stmt = $this->db->query("SELECT COUNT(*) AS serving_files, SUM(size) AS active_content, AVG(size) AS average_file_size
                FROM files
            ");

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            return null;
        }

        $stmt = $this->db->query('SELECT
            COUNT(*) AS serving_posts,
            COUNT(*) / TIMESTAMPDIFF(MINUTE, MIN(uploaded_at), MAX(uploaded_at)) AS average_upload_rate,
            MIN(uploaded_at) AS first_uploaded_at,
            MAX(uploaded_at) AS last_uploaded_at
            FROM posts
        ');
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['serving_posts'] = $row['serving_posts'];
            $res['average_upload_rate'] = $row['average_upload_rate'];
            $res['first_uploaded_at'] = $row['first_uploaded_at'];
            $res['last_uploaded_at'] = $row['last_uploaded_at'];
        }

        // calculating the count of future files
        $serving_future_files = null;
        if (CONFIG['stats']['disk_size'] > 0 && $res['average_file_size'] > 0) {
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
        $stmt = $this->db->query("SELECT p.id
        FROM posts p
        ORDER BY views DESC LIMIT 9");

        $files = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($post = $this->get_post($row['id'])) {
                array_push($files, $post);
            }
        }
        $res['most_viewed'] = $files;

        if (MEMCACHED) {
            $res['last_update'] = time();
            MEMCACHED->set(CONFIG['instance']['id'] . '_stats', json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), CONFIG['stats']['ttl']);
        }

        return $res;
    }

    public function delete_post(Post $post): bool
    {
        $this->db->prepare('DELETE FROM posts WHERE id = ?')
            ->execute([$post->id]);

        foreach ($post->attachments as $file) {
            $similar_posts = $this->get_posts_by_hash($file->hash);
            if ($similar_posts['count'] == 0 && defined("FILESTORAGE")) {
                if (!FILESTORAGE->delete_file("{$file->name}.{$file->extension}")) {
                    return false;
                }

                $this->db->prepare('DELETE FROM files WHERE id = ?')
                    ->execute([$file->name]);
            }
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