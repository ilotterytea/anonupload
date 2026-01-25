<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";

enum UserRole
{
    case User;
    case Moderator;

    public static function parse(string $name): UserRole|null
    {
        return match ($name) {
            "user" => UserRole::User,
            "moderator" => UserRole::Moderator,
            default => null
        };
    }
}

class User
{
    final public int $id;
    final public string $name, $password, $token;
    final public UserRole $role;

    public function __construct(int $id, string $name, string $password, UserRole $role, string $token)
    {
        $this->id = $id;
        $this->name = $name;
        $this->password = $password;
        $this->role = $role;
        $this->token = $token;
    }
}

class UserManager
{
    private FileStorageType $type;
    private PDO|null $db;

    public function __construct(FileStorageType $type)
    {
        $this->type = $type;
        if ($type === FileStorageType::Database) {
            $this->db = new PDO(CONFIG['database']['url'], CONFIG['database']['name'], CONFIG['database']['pass']);
        }
    }

    public function get_user_by_name(string $name): User|null
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->prepare('SELECT id, `name`, `password`, `role`, token FROM users WHERE `name` = ?');
            $stmt->execute([$name]);
            $user = null;
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $user = new User(
                    $row['id'],
                    $row['name'],
                    $row['password'],
                    UserRole::parse($row['role']),
                    $row['token']
                );
            }
            return $user;
        } elseif ($users = $this->load_local_users()) {
            return array_find($users, fn($x) => $x->name === $name);
        }

        return null;
    }

    public function get_user_by_token(string $token): User|null
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->prepare('SELECT id, `name`, `password`, `role`, token FROM users WHERE `token` = ?');
            $stmt->execute([$token]);
            $user = null;
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $user = new User(
                    $row['id'],
                    $row['name'],
                    $row['password'],
                    UserRole::parse($row['role']),
                    $row['token']
                );
            }
            return $user;
        } elseif ($users = $this->load_local_users()) {
            return array_find($users, fn($x) => $x->token === $token);
        }

        return null;
    }

    public function is_not_authorized(): bool
    {
        if (!isset($_COOKIE['token'])) {
            return true;
        }

        $user = $this->get_user_by_token($_COOKIE['token']);
        if ($user === null) {
            return true;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user'] = $user;

        return false;
    }

    public function get_type(): FileStorageType
    {
        return $this->type;
    }

    private function load_local_users(): array|null
    {
        if (!file_exists(CONFIG['users']['path'])) {
            return null;
        }

        $users = [];

        $lines = explode(PHP_EOL, file_get_contents(CONFIG['users']['path']));
        foreach ($lines as $line) {
            [$id, $name, $password, $role, $token] = explode(" ", $line);
            $user = new User(
                $id,
                $name,
                $password,
                UserRole::parse($role),
                $token
            );
            array_push($users, $user);
        }

        return $users;
    }
}

define(
    "USER",
    new UserManager(
        match (CONFIG['storage']['type']) {
            'file' => FileStorageType::File,
            'json' => FileStorageType::Json,
            'database' => FileStorageType::Database,
            default => null
        }
    )
);