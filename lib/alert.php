<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";

function generate_alert(string $redirect, string|null $message, int $code = 200, mixed $data = null, bool $json_only = false)
{
    $response = $message;

    if ($json_only || IS_JSON_REQUEST) {
        http_response_code($code);
        header('Content-Type: application/json');
        $response = json_encode([
            'status_code' => $code,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_SLASHES);
    } else {
        if (session_status() != PHP_SESSION_ACTIVE)
            session_start();
        $_SESSION['alert'] = [
            'code' => $code,
            'message' => $message
        ];
        http_response_code(303);
        header("Location: $redirect");
    }

    die($response);
}

function display_alert()
{
    if (!isset($_SESSION['alert']))
        return;

    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);

    echo '<section class="box alert';
    if ($alert['code'] > 399) {
        echo ' red';
    }
    echo '">';

    echo "<p>{$alert['message']}</p>";
    echo '</section>';
}