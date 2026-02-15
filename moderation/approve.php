<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

if (!USER->authorize_with_cookie()) {
    http_response_code(303);
    header('Location: /account/login.php');
    exit("You must be authorized!");
}

$user = $_SESSION['user'];

if ($user->role->as_value() < UserRole::Moderator->as_value()) {
    generate_alert('/account/', 'You are not a moderator!', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id'], $_POST['action'])) {
        generate_alert('/moderation/approve.php', 'Invalid request.', 400);
    }

    $action = $_POST['action'];
    $message = null;
    switch ($action) {
        case "approve":
            $message = "File $id has been approved!";
            break;
        case "unlist":
            $message = "File $id has been unlisted!";
            break;
        default:
            generate_alert('/moderation/approve.php', 'Invalid request.', 400);
            break;
    }

    $id = $_POST['id'];
    $file = File::load($id);
    if ($file === null) {
        generate_alert('/moderation/approve.php', "File not found", 404);
    }

    $file->visibility = $action === "approve" ? 1 : 0;
    if (!STORAGE->save($file)) {
        generate_alert('/moderation/approve.php', "Failed to save the file", 500);
    }

    generate_alert('/moderation/approve.php', $message, 200);
}

$files = STORAGE->get_files_by_visibility(2);
$file = null;

if (!empty($files)) {
    $name = parse_file_name($_GET['id'] ?? null);

    if ($name !== null) {
        foreach ($files as $x) {
            if ($x->id === $name['name'] && $x->extension === $name['extension']) {
                $file = $x;
                break;
            }
        }
        if ($file === null) {
            generate_alert('/moderation/approve.php', "File {$name['name']}.{$name['extension']} not found!", 404);
        }
    } else {
        $file = $files[0];
    }
}
?>
<!DOCTYPE html>
<html>

<head><?php html_head("Media Pending Approval"); ?></head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>Media Pending Approval</h1>
        <hr>
        <?php if (isset($file)): ?>
            <div class="column file-preview">
                <a href="/<?= "{$file->id}.{$file->extension}" ?>" target="_blank">
                    <?php html_file_full($file); ?>
                </a>
            </div>
            <div class="row gap-8">
                <form action="/moderation/approve.php" method="post" class="row grow">
                    <input type="text" name="id" value="<?= "{$file->id}.{$file->extension}" ?>" style="display:none">
                    <input type="text" name="action" value="approve" style="display:none">
                    <button type="submit" class="fancy grow">Approve</button>
                </form>
                <form action="/moderation/approve.php" method="post" class="row grow">
                    <input type="text" name="id" value="<?= "{$file->id}.{$file->extension}" ?>" style="display:none">
                    <input type="text" name="action" value="unlist" style="display:none">
                    <button type="submit" class="fancy grow">Unlist</button>
                </form>
                <a href="/files/delete.php?id=<?= urlencode("{$file->id}.{$file->extension}") ?>&r=%2Fmoderation%2Fapprove.php"
                    class="row grow">
                    <button type="submit" class="fancy grow">Delete</button>
                </a>
                <form action="/files/ban.php?r=%2Fmoderation%2Fapprove.php" method="post" class="row grow">
                    <input type="text" name="id" value="<?= "{$file->id}.{$file->extension}" ?>" style="display:none">
                    <button type="submit" class="fancy grow">Ban</button>
                </form>
            </div>
        <?php elseif (empty($files)): ?>
            <div class="box red">
                <p>No files to approve!</p>
            </div>
        <?php else: ?>
            <div class="box red">
                <p>Not found.</p>
            </div>
        <?php endif; ?>
        <div class="wall">
            <?php foreach ($files as $file): ?>
                <?php html_file_brick($file, "/moderation/approve.php?id=" . urlencode("{$file->id}.{$file->extension}")); ?>
            <?php endforeach; ?>
        </div>
    </main>
</body>

</html>