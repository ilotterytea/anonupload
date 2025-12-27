<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/config.php";
define('IS_JSON_REQUEST', isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json');

function json_response(mixed $data, string|null $message, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'status_code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES);
}

function generate_random_char_sequence(array $chars, int $length): string
{
    $o = "";

    for ($i = 0; $i < $length; $i++) {
        $o .= $chars[random_int(0, count($chars) - 1)];
    }

    return $o;
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