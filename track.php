<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/lib/config.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/lib/file.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/lib/utils.php";

if (!MEMCACHED) {
    send_json_response(null, 'This feature is not available', 500);
}

if (isset($_GET['create'])) {
    $id = bin2hex(random_bytes(16));
    $status = new FileTrackStatus($id);
    $status->send('queue', 'waiting...');
    send_json_response([
        'id' => $id,
        'ttl' => $status->ttl,
    ], null, 201);
}

$status = new FileTrackStatus(trim($_GET['id'] ?? "0"));
if (!$status->exists()) {
    send_json_response(null, "File track ID {$status->id} not found", 404);
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

while (true) {
    $data = $status->get();
    if (!$data) {
        break;
    }

    echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

    ob_flush();
    flush();

    usleep(250000);
}