<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

if (!FILE_REPORT) {
    http_response_code(403);
    exit('No reports allowed!');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id'], $_POST['reason'])) {
        http_response_code(400);
        exit('Not enough data.');
    }

    $file_id = $_POST['id'];
    $file_id = explode('.', $file_id);
    $file_ext = $file_id[1];
    $file_id = $file_id[0];

    if (!is_file(FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_ext}")) {
        http_response_code(404);
        exit('Invalid file.');
    }

    $reason = trim($_POST['reason'] ?? '');

    if (empty($reason)) {
        http_response_code(400);
        exit('Report reason is empty');
    }

    $email = $_POST['email'] ?? '(Anonymous)';
    if (empty($email)) {
        $email = '(Anonymous)';
    }

    if (!is_dir(FILE_REPORT_DIRECTORY) && !mkdir(FILE_REPORT_DIRECTORY, 0777, true)) {
        http_response_code(500);
        exit('Failed to create a folder for reports. Try again later.');
    }

    do {
        $report_id = generate_random_char_sequence(FILE_ID_CHARACTERS, 16);
    } while (is_file(FILE_REPORT_DIRECTORY . "/{$report_id}.txt"));

    $contents = "File ID: {$file_id}.{$file_ext}
Feedback Email: {$email}

Reason:
{$reason}";

    if (!file_put_contents(FILE_REPORT_DIRECTORY . "/{$report_id}.txt", $contents)) {
        http_response_code(500);
        exit("Failed to save the report. Try again later!");
    }

    json_response(['id' => $report_id], 'Sent!', 201);
    exit();
}

$file_id = $_GET['f'] ?? '';

if (!is_file(FILE_UPLOAD_DIRECTORY . "/{$file_id}")) {
    $file_id = null;
}

?>
<html>

<head>
    <title>Report - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <h1>Report a file</h1>
        <hr>
        <form action="/report.php" method="post">
            <table class="vertical">
                <tr>
                    <th>File ID with extension:</th>
                    <td><input type="text" name="id" value="<?= $file_id ?>" placeholder="XXXXX.png" required></td>
                </tr>
                <tr>
                    <th>What is wrong with that file?</th>
                    <td><textarea name="reason" placeholder="..." required></textarea></td>
                </tr>
                <tr>
                    <th>Feedback E-Mail:</th>
                    <td><input type="email" name="email" placeholder="Optional"></td>
                </tr>
                <tr>
                    <th></th>
                    <td><button type="submit">Send</button></td>
                </tr>
            </table>
        </form>
    </main>
</body>

</html>