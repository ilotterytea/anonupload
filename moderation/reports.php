<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

if (!CONFIG['report']['enable']) {
    generate_alert('/', 'File reporting is disabled on this instance.', 403);
}

if (!USER->authorize_with_cookie()) {
    http_response_code(303);
    header('Location: /account/login.php');
    exit("You must be authorized!");
}

$user = $_SESSION['user'];

if ($user->role->as_value() < UserRole::Moderator->as_value()) {
    generate_alert('/account/', 'You are not a moderator!', 401);
}

$reports = [];
foreach (glob(CONFIG['report']['directory'] . "/*.txt") as $name) {
    if (false !== $contents = file_get_contents($name)) {
        $parts = explode("\r\n\r\n", $contents, 2);
        $report = [];
        foreach (explode("\r\n", $parts[0]) as $line) {
            $p = explode(":", $line, 2);
            $report[strtolower($p[0])] = trim($p[1]);
        }
        $report["internal_id"] = basename($name, ".txt");
        $report["info"] = trim($parts[1]) ?: null;
        array_push($reports, $report);
    }
}

$report = null;

if (!empty($reports)) {
    if (null !== $name = $_GET['id'] ?? $_POST['id'] ?? null) {
        foreach ($reports as $x) {
            if ($x["internal_id"] === $name) {
                $report = $x;
                break;
            }
        }
        if ($report === null) {
            generate_alert('/moderation/reports.php', "Report $name not found!", 404);
        }
    } else {
        $report = $reports[0];
        $report['auto_selected'] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id'], $_POST['action']) || isset($report['auto_selected'])) {
        generate_alert('/moderation/reports.php', 'Invalid request.', 400);
    }

    $action = $_POST['action'];
    $message = null;

    $file = File::load($report['id']);
    if ($file === null) {
        generate_alert('/moderation/reports.php', "File not found", 404);
    }

    switch ($action) {
        case "delete":
            if (!STORAGE->delete_file($file)) {
                generate_alert('/moderation/reports.php', 'Failed to delete the file!', 500);
            }
            $message = "File report {$report['internal_id']} has been accepted (deleted)!";
            break;
        case "ban":
            $file_sha = hash_file('sha256', $file->path);
            if (!STORAGE->ban_file($file, $file_sha, $report['reason'])) {
                generate_alert('/moderation/reports.php', 'Failed to ban the file!', 500);
            }
            $message = "File report {$report['internal_id']} has been accepted (banned)!";
            break;
        case "deny":
            $message = "File report {$report['internal_id']} has been denied!";
            break;
        default:
            generate_alert('/moderation/reports.php', 'Invalid request.', 400);
            break;
    }

    if (!unlink(CONFIG['report']['directory'] . "/{$report['internal_id']}.txt")) {
        generate_alert('/moderation/reports.php', 'Failed to delete the report!', 500);
    }

    generate_alert('/moderation/reports.php', $message, 200);
}
?>
<!DOCTYPE html>
<html>

<head><?php html_head("Report reviewal"); ?></head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>Report reviewal</h1>
        <hr>
        <?php if (isset($report)): ?>
            <div class="column file-preview">
                <a href="/<?= $report['id'] ?>" target="_blank">
                    <?php html_file_full(File::load($report['id'])); ?>
                </a>
            </div>
            <table class="vertical left">
                <tr>
                    <th>File ID:</th>
                    <td><input type="text" name="id" value="<?= $report['id'] ?>" disabled></td>
                </tr>
                <tr>
                    <th>Reason:</th>
                    <td><input type="text" name="reason" value="<?= $report['reason'] ?>" disabled></td>
                </tr>
                <tr>
                    <th>Additional information:</th>
                    <td><textarea name="explanation" placeholder="No info." disabled><?= $report['info'] ?></textarea></td>
                </tr>
            </table>
            <div class="row gap-8">
                <form method="post" class="row grow">
                    <input type="text" name="id" value="<?= $report['internal_id'] ?>" style="display:none">
                    <input type="text" name="action" value="ban" style="display:none">
                    <button type="submit" class="fancy grow">Ban File</button>
                </form>
                <form method="post" class="row grow">
                    <input type="text" name="id" value="<?= $report['internal_id'] ?>" style="display:none">
                    <input type="text" name="action" value="delete" style="display:none">
                    <button type="submit" class="fancy grow">Delete File</button>
                </form>
                <form method="post" class="row grow">
                    <input type="text" name="id" value="<?= $report['internal_id'] ?>" style="display:none">
                    <input type="text" name="action" value="deny" style="display:none">
                    <button type="submit" class="fancy grow">Deny Report</button>
                </form>
            </div>
            <hr>
        <?php endif; ?>
        <?php if (empty($reports)): ?>
            <div class="box red">
                <p>No reports to review!</p>
            </div>
        <?php else: ?>
            <h2>Reports</h2>
            <table>
                <tr>
                    <th>File ID</th>
                    <th>Reason</th>
                    <th></th>
                </tr>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td>
                            <img src="<?= CONFIG['thumbnails']['url'] ?>/<?= parse_file_name($r['id'])['name'] ?>.webp" alt=""
                                width="16px">
                            <a href="/<?= $r['id'] ?>" target="_blank">
                                <?= $r['id'] ?>
                            </a>
                        </td>
                        <td><?= $r['reason'] ?></td>
                        <td><a href="/moderation/reports.php?id=<?= $r['internal_id'] ?>">[Open]</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </main>
</body>

</html>