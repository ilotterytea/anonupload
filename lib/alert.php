<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";

function generate_alert(string $redirect, string|null $message, int $code = 200, mixed $data = null)
{
    if (IS_JSON_REQUEST) {
        json_response($data, $message, $code);
    } else if (isset($message)) {
        header("Location: $redirect" . (str_contains($redirect, "?") ? "&" : "?") . "es=$code&er=" . urlencode($message));
    } else {
        header("Location: $redirect");
    }
}

function display_alert()
{
    if (!isset($_GET["es"], $_GET["er"])) {
        return;
    }

    $status = $_GET["es"];
    $reason = urldecode($_GET['er']);
    $ok = substr($status, 0, 1) == '2';

    echo '' ?>
    <section class="box alert<?= !$ok ? ' red' : '' ?>">
        <p><?= $reason ?></p>
    </section>
    <?php ;
}