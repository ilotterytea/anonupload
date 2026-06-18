<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
define('IS_JSON_REQUEST', isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json');

function send_json_response(mixed $data, string|null $message = null, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    die(json_encode([
        'status_code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES));
}

function format_timestamp(string|DateTime $datetime)
{
    $dt = $datetime instanceof DateTime ? $datetime : new DateTime($datetime);
    $now = new DateTime();

    $diff = match ($dt->getTimestamp() < $now->getTimestamp()) {
        true => $dt->diff($now),
        false => $now->diff($dt)
    };

    $seconds = $diff->s;
    $days = $diff->d;
    $minutes = $diff->i;
    $hours = $diff->h;
    $years = $diff->y;
    $months = $diff->m;

    if ($years == 0 && $months == 0 && $days == 0 && $hours == 0 && $minutes == 0) {
        return "$seconds second" . ($seconds > 1 ? "s" : "");
    } else if ($years == 0 && $months == 0 && $days == 0 && $hours == 0) {
        return "$minutes minute" . ($minutes > 1 ? "s" : "");
    } else if ($years == 0 && $months == 0 && $days == 0) {
        return "$hours hour" . ($hours > 1 ? "s" : "");
    } else if ($years == 0 && $months == 0) {
        return "$days day" . ($days > 1 ? "s" : "");
    } else if ($years == 0) {
        return "$months month" . ($months > 1 ? "s" : "");
    } else {
        return "$years year" . ($years > 1 ? "s" : "");
    }
}

function format_filesize($file_size)
{
    $suffix = 'MB';
    $file_size /= 1024 * 1024; // MB

    if ($file_size >= 1024) {
        $file_size /= 1024;
        $suffix = 'GB';
    }

    return sprintf('%.2f%s', $file_size, $suffix);
}

function str_safe(string $s, int|null $max_length, bool $remove_new_lines = true): string
{
    $output = $s;

    if ($remove_new_lines) {
        $output = str_replace(PHP_EOL, "", $output);
    }

    $output = htmlspecialchars($output);
    $output = strip_tags($output);

    if ($max_length) {
        $output = substr($output, 0, $max_length);
    }

    $output = trim($output);

    return $output;
}

function get_commit(): array|null
{
    $commit = null;
    $sh = trim(shell_exec('git show -s --format="%h %ct %s" HEAD'));

    if ($sh) {
        $commit = explode(' ', $sh, 3);
        $commit = [
            'sha' => $commit[0],
            'timestamp' => (int) $commit[1],
            'message' => $commit[2]
        ];
    }

    return $commit;
}

class HTTPException extends Exception
{
    private int $statusCode;
    public function __construct(string $message = "", int $statusCode = 400)
    {
        parent::__construct($message, 0, null);
        $this->statusCode = $statusCode;
    }

    public function as_response()
    {
        http_response_code($this->statusCode);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}