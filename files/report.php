<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

if (!CONFIG['report']['enable']) {
    generate_alert('/', 'File reporting is disabled on this instance.', 403);
}

USER->authorize_with_cookie();

if (!isset($_GET['id']) && !isset($_POST['id'])) {
    generate_alert('/', 'File ID must be provided!', 400);
}

$id = $_GET['id'] ?? $_POST['id'];

$file_name = parse_file_name($id);
if (!$file_name) {
    generate_alert('/', 'File ID must be provided!', 400);
}

$file = STORAGE->get_by_name_and_extension($file_name['name'], $file_name['extension']);
if (!$file) {
    generate_alert('/', 'File not found', 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = str_safe($_POST['reason'] ?? "Other", null);

    if ($reason !== "Other" && !in_array($reason, CONFIG['report']['reasons'])) {
        generate_alert("/files/report.php?id=$id", 'Invalid request.', 400);
    }

    if (!is_dir(CONFIG['report']['directory']) && !mkdir(CONFIG['report']['directory'], 0777, true)) {
        generate_alert("/files/report.php?id=$id", 'Failed to create a directory for file reports. Try again later!', 500);
    }

    $info = str_safe($_POST['explanation'] ?? "", null);
    $contents = "ID: $id\r\nReason: $reason\r\n\r\n\r\n$info";

    $id = generate_snowflake_id();

    if (!file_put_contents(CONFIG['report']['directory'] . "/$id.txt", $contents)) {
        generate_alert("/files/report.php?id=$id", 'Failed to save a file reports. Try again later!', 500);
    }

    generate_alert('/', 'Successfully reported the file!', 201);
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("Report {$file_name['name']}.{$file_name['extension']}"); ?>
</head>

<body>
    <?php html_mini_navbar() ?>
    <main>
        <?php display_alert() ?>
        <h1>Report file</h1>
        <hr>
        <form autocomplete="off" method="post" class="column gap-8">
            <fieldset class="block">
                <legend>Report file</legend>
                <div style="max-width: 256px;">
                    <label for="preview">File Preview:</label>
                    <?php html_file_brick($file); ?>
                </div>
                <label for="id">File ID:</label>
                <input type="text" name="id" id="id" value="<?= "{$file_name['name']}.{$file_name['extension']}" ?>"
                    disabled required>

                <label for="reason">Reason:</label>
                <select name="reason" id="reason">
                    <?php if (!empty(CONFIG['report']['reasons'])): ?>
                        <?php foreach (CONFIG['report']['reasons'] as $r): ?>
                            <option value="<?= $r ?>">
                                <?= $r ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <option value="Other">Other</option>
                </select>

                <label for="explanation">Additional information:</label>
                <textarea name="explanation" id="explanation" placeholder="Enter text here..."></textarea>
            </fieldset>
            <div>
                <button type="submit" class="fancy">Send</button>
            </div>
        </form>
    </main>
</body>

</html>