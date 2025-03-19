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