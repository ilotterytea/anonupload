<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";

interface FileRegistry
{
    public function get_files(): array;
    public function get_file(string $name): ExtendedFile|null;
    public function get_random_file(): ExtendedFile|null;
    public function put_file(ExtendedFile $file): ExtendedFile|null;
    public function delete_file(ExtendedFile $file): bool;
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

    public function get_file(string $name): ExtendedFile|null
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

        $file = new ExtendedFile();
        $file->id = $res['system_id'];
        $file->alias_id = $res['id'];
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

        return $file;
    }

    public function get_random_file(): ExtendedFile|null
    {
        $stmt = $this->db->query("SELECT CONCAT(f.id, '.', f.extension) AS file_name FROM files f ORDER BY rand() LIMIT 1");
        $stmt->execute();

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $res ? $this->get_file($res['file_name']) : null;
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

    public function put_file(ExtendedFile $file): ExtendedFile|null
    {
        if ($file?->password) {
            $file->password = password_hash($file?->password, PASSWORD_DEFAULT);
        }

        $stmt = $this->db->prepare("INSERT INTO
            files(id, system_id, mime, extension, size, password, uploaded_at)
            VALUES(:id, :sid, :mime, :ext, :size, :pass, :uat)
            ON DUPLICATE KEY UPDATE
            size = :size, uploaded_at = :uat, system_id = :sid
        ");
        $stmt->execute([
            ':id' => $file->alias_id,
            ':sid' => $file->id,
            ':mime' => $file->mime,
            ':ext' => $file->extension,
            ':size' => $file->size,
            ':pass' => $file->password,
            ':uat' => $file->uploaded_at
        ]);

        return $this->get_file($file->name());
    }

    public function get_stats(): array|null
    {
        // -- basic info
        $stmt = $this->db->query("SELECT COUNT(*) AS serving_files, SUM(size) AS active_content, AVG(size) AS average_file_size,
                COUNT(*) / TIMESTAMPDIFF(MINUTE, MIN(uploaded_at), MAX(uploaded_at)) AS average_upload_rate
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
                FROM files
                WHERE uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                GROUP BY YEAR(uploaded_at), QUARTER(uploaded_at)
                ORDER BY year, quarter
            ");
        $res['timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // -- the most viewed files
        $stmt = $this->db->query("SELECT id, extension, mime, `size` FROM files ORDER BY views DESC LIMIT 5");

        $files = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $file = new ExtendedFile();
            $file->alias_id = $row['id'];
            $file->extension = $row['extension'];
            $file->mime = $row['mime'];
            $file->size = $row['size'];
            $file->url = "/{$file->id}.{$file->extension}";
            if (THUMBNAILER !== null) {
                $file->thumbnail_url = sprintf("%s/%s.%s", THUMBNAILER->get_thumbnail_root(), $file->id, THUMBNAILER->get_thumbnail_extension());
            }
            array_push($files, $file);
        }
        $res['most_viewed'] = $files;

        return $res;
    }

    public function delete_file(ExtendedFile $file): bool
    {
        $this->db->prepare('DELETE FROM files WHERE id = ? AND extension = ?')
            ->execute([$file->alias_id, $file->extension]);
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