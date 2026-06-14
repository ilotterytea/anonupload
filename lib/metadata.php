<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

interface MetadataStorage
{
    public function has_storage_data(string $id): bool;
    public function get_storage_data(string $id): mixed;
    public function put_storage_data(string $id, mixed $data): bool;
}

class SQLMetadataStorage implements MetadataStorage
{
    private PDO $db;

    public function __construct(string $url, string $user, string $password)
    {
        $this->db = new PDO($url, $user, $password);
        $sql = file_get_contents("{$_SERVER['DOCUMENT_ROOT']}/database.sql");
        $this->db->exec($sql);
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

define('METASTORAGE', match (CONFIG['metadata']['type']) {
    'sql' => new SQLMetadataStorage(
        CONFIG['metadata']['url'],
        CONFIG['metadata']['user'],
        CONFIG['metadata']['pass'],
    ),
    default => null
});