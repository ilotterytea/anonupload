<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";

enum UserRole
{
    case User;
    case Moderator;
    case Administrator;

    public function as_value(): int
    {
        return match ($this) {
            UserRole::Moderator => 10,
            UserRole::Administrator => 99,
            default => 1
        };
    }

    public function as_string(): string
    {
        return match ($this) {
            UserRole::Moderator => "moderator",
            UserRole::Administrator => "administrator",
            default => "user"
        };
    }

    public static function parse(mixed $name): UserRole|null
    {
        if (is_string($name)) {
            return match (strtolower($name)) {
                "user" => UserRole::User,
                "moderator" => UserRole::Moderator,
                "administrator" => UserRole::Administrator,
                default => null
            };
        } elseif (is_numeric($name)) {
            return match (intval($name)) {
                1 => UserRole::User,
                10 => UserRole::Moderator,
                99 => UserRole::Administrator
            };
        }

        return null;
    }
}

class User
{
    public int $id;
    public string $name, $password, $token;
    public UserRole $role;

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
            $this->db = new PDO(CONFIG['database']['url'], CONFIG['database']['user'], CONFIG['database']['pass']);
        }
    }

    public function save(User $user): bool
    {
        if ($this->type === FileStorageType::Database) {
            $stmt = $this->db->prepare('INSERT INTO users(id, `name`, `password`, `role`, token)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `name` = ?,
                    `password` = ?,
                    `role` = ?,
                    token = ?
            ');
            $stmt->execute([
                $user->id,
                $user->name,
                $user->password,
                $user->role->name,
                $user->token,
                $user->name,
                $user->password,
                $user->role->name,
                $user->token
            ]);
            return true;
        } else {
            if (!is_file(CONFIG['users']['path']) && !file_put_contents(CONFIG['users']['path'], '')) {
                return false;
            }

            $contents = "";

            $lines = explode(PHP_EOL, file_get_contents(CONFIG['users']['path']));

            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                [$id, $name, $password, $role, $token] = explode(" ", $line);

                if ($id == $user->id) {
                    $name = $user->name;
                    $password = $user->password;
                    $role = $user->role->name;
                    $token = $user->token;
                }

                $contents .= "$id $name $password $role $token" . PHP_EOL;
            }

            file_put_contents(CONFIG['users']['path'], $contents);
        }

        return true;
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
            foreach ($users as $user) {
                if ($user->name === $name) {
                    return $user;
                }
            }
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
            foreach ($users as $user) {
                if ($user->token === $token) {
                    return $user;
                }
            }
        }

        return null;
    }

    public function create(string $username, string $password, UserRole $role = UserRole::User): bool
    {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(16));
        if ($this->type === FileStorageType::Database) {
            $this->db->prepare('INSERT INTO users(`name`, `password`, `role`, token) VALUES (?, ?, ?, ?)')
                ->execute([
                    $username,
                    $password,
                    $role->as_value(),
                    $token
                ]);
            return true;
        } else {
            $users = $this->load_local_users() ?? [];
            $next_id = count($users) + 1;

            return file_put_contents(CONFIG['users']['path'], "$next_id $username $password {$role->as_string()} $token" . PHP_EOL, FILE_APPEND);
        }
    }

    public function authorize_with_cookie(): bool
    {
        if (!isset($_COOKIE['token'])) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $this->get_user_by_token($_COOKIE['token']);
        if ($user === null) {
            unset($_SESSION['user']);
            unset($_COOKIE['token']);
            return false;
        }

        $_SESSION['user'] = $user;

        return true;
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
            if (empty($line)) {
                continue;
            }
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