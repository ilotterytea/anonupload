<?php
function json_response(mixed $data, string|null $message, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'status_code' => $code,
        'message' => $message,
        'data' => $data
    ]);
}