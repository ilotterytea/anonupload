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

function bbcode_parse(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $simple = [
        '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
        '/\[s\](.*?)\[\/s\]/is' => '<del>$1</del>',
        '/\[quote\](.*?)\[\/quote\]/is' => '<blockquote>$1</blockquote>',
        '/\[code\](.*?)\[\/code\]/is' => '<pre><code>$1</code></pre>',
        '/\[size=(\d+)\](.*?)\[\/size\]/is' => '<span style="font-size:$1px">$2</span>',
        '/\[color=(#[a-f0-9]{3,6}|[a-z]+)\](.*?)\[\/color\]/is' => '<span style="color:$1">$2</span>',
        '/\[h([1-6])\](.*?)\[\/h\1\]/is' => '<h$1>$2</h$1>'
    ];

    foreach ($simple as $pattern => $replace) {
        $text = preg_replace($pattern, $replace, $text);
    }

    // link with text
    $text = preg_replace_callback(
        '/\[url=(.*?)\](.*?)\[\/url\]/is',
        function ($m) {
            $url = filter_var($m[1], FILTER_SANITIZE_URL);
            return "<a href='$url' target='_blank' rel='noopener noreferrer'>{$m[2]}</a>";
        },
        $text
    );

    // link without text
    $text = preg_replace_callback(
        '/\[url\](.*?)\[\/url\]/is',
        function ($m) {
            $url = filter_var($m[1], FILTER_SANITIZE_URL);
            return "<a href='$url' target='_blank' rel='noopener noreferrer'>$url</a>";
        },
        $text
    );

    // lists
    $text = preg_replace_callback(
        '/\[list\](.*?)\[\/list\]/is',
        function ($m) {
            $items = preg_split('/\[\*\]/', $m[1], -1, PREG_SPLIT_NO_EMPTY);
            $out = '<ul>';
            foreach ($items as $item) {
                if (empty(trim($item)))
                    continue;
                $out .= '<li>' . trim($item) . '</li>';
            }
            $out .= '</ul>';
            return $out;
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[olist\](.*?)\[\/olist\]/is',
        function ($m) {
            $items = preg_split('/\[\*\]/', $m[1], -1, PREG_SPLIT_NO_EMPTY);
            $out = '<ol>';
            foreach ($items as $item) {
                if (empty(trim($item)))
                    continue;
                $out .= '<li>' . trim($item) . '</li>';
            }
            $out .= '</ol>';
            return $out;
        },
        $text
    );

    $paragraphs = preg_split("/\R\R+/", $text);
    foreach ($paragraphs as &$p) {
        $p = '<p>' . trim($p) . '</p>';
    }

    return implode("\n", $paragraphs);
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
}