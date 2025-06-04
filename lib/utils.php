<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

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

function format_timestamp(int $timestamp_secs)
{
    $days = (int) floor($timestamp_secs / (60.0 * 60.0 * 24.0));
    $hours = (int) floor(round($timestamp_secs / (60 * 60)) % 24);
    $minutes = (int) floor(round($timestamp_secs % (60 * 60)) / 60);
    $seconds = (int) floor($timestamp_secs % 60);

    if ($days == 0 && $hours == 0 && $minutes == 0) {
        return "$seconds second" . ($seconds > 1 ? "s" : "");
    } else if ($days == 0 && $hours == 0) {
        return "$minutes minute" . ($minutes > 1 ? "s" : "");
    } else if ($days == 0) {
        return "$hours hour" . ($hours > 1 ? "s" : "");
    } else {
        return "$days day" . ($days > 1 ? "s" : "");
    }
}