<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

USER->authorize_with_cookie();

if (!CONFIG["stats"]["enable"] && (!isset($_SESSION['user']) || $_SESSION['user']->role->as_value() < UserRole::Moderator->as_value())) {
    generate_alert('/account/', 'You are not allowed to access this page!', 403);
}

// uploaded files stats
$uploaded_files = STORAGE->get_upload_timeline();

// recent images & videos
if (CONFIG["stats"]["lastfiles"]) {
    $recent_images = STORAGE->get_recent_files_by_mime("image/%", 5);
    $recent_videos = STORAGE->get_recent_files_by_mime("video/%", 5);
}

// the most viewed files
if (CONFIG["stats"]["mostviewed"]) {
    $most_viewed_files = STORAGE->get_most_viewed_files(5);
}

// --- file stats
function format_filesize($file_size)
{
    $suffix = 'MB';
    $file_size /= 1024 * 1024; // MB

    if ($file_size >= 1024) {
        $file_size /= 1024;
        $suffix = 'GB';
    }

    return sprintf('%.2f%s', $file_size, $suffix);
}
$file_stats = STORAGE->get_stats();

if (CONFIG["stats"]["disksize"] > 0) {
    $file_stats['future_file_count'] = floor(CONFIG["stats"]["disksize"] / $file_stats['approx_filesize']);
    if ($file_stats['avg_upload_rate'] > 0.01) {
        $file_stats['estimated_time'] = floor((CONFIG["stats"]["disksize"] - $file_stats['active_content']) / $file_stats['avg_upload_rate']);
        $dt = new DateTime();
        $dt->modify("+{$file_stats['estimated_time']} minutes");
        $file_stats['estimated_time'] = $dt->format("F j, Y");
    }
}

$file_stats['active_content'] = format_filesize($file_stats['active_content']);
$file_stats['approx_filesize'] = format_filesize($file_stats['approx_filesize']);

?>
<!DOCTYPE html>
<html>

<head><?php html_head("Statistics"); ?></head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>Statistics</h1>
        <h2>Files uploaded &lpar;per quarter&rpar;</h2>
        <canvas id="uploaded-files-chart"></canvas>
        <?php if (isset($recent_images)): ?>
            <h2>Recent images</h2>
            <div class="wall">
                <?php foreach ($recent_images as $i): ?>
                    <?php html_file_brick($i); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($recent_videos)): ?>
            <h2>Recent videos</h2>
            <div class="wall">
                <?php foreach ($recent_videos as $i): ?>
                    <?php html_file_brick($i); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($most_viewed_files)): ?>
            <h2>The most viewed files</h2>
            <div class="wall">
                <?php foreach ($most_viewed_files as $file): ?>
                    <?php html_file_brick($file); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <h2>About files</h2>
        <div>
            <table class="vertical left">
                <tr>
                    <th>Serving files</th>
                    <td><?= $file_stats["file_count"] ?></td>
                </tr>
                <tr>
                    <th>Active content</th>
                    <td><?= $file_stats["active_content"] ?></td>
                </tr>
                <tr>
                    <th>Average filesize</th>
                    <td><?= $file_stats["approx_filesize"] ?></td>
                </tr>
                <tr>
                    <th>Average upload rate &lpar;per minute&rpar;</th>
                    <td><?= $file_stats["avg_upload_rate"] ?></td>
                </tr>
                <?php if (isset($file_stats["future_file_count"])): ?>
                    <tr>
                        <th>How many files can be uploaded</th>
                        <td>~<?= $file_stats["future_file_count"] ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (isset($file_stats["estimated_time"])): ?>
                    <tr>
                        <th>Estimated date of hard disk space running out</th>
                        <td><?= $file_stats["estimated_time"] ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php html_mini_footer(); ?>
    </main>
</body>

<?php if (isset(CONFIG['driver']['chart'])): ?>
    <script src="<?= CONFIG['driver']['chart'] ?>"></script>
    <script>
        new Chart("uploaded-files-chart", {
            type: "bar",
            data: {
                labels: <?= sprintf('[%s]', implode(', ', array_map(
                    fn($x) =>
                    sprintf("'%s-%s'", $x['year'], $x['quarter']),
                    $uploaded_files
                ))) ?>,
                datasets: [{
                    data: <?= sprintf('[%s]', implode(', ', array_map(
                        fn($x) => $x['file_count'],
                        $uploaded_files
                    ))) ?> }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    },
                }
            }
        });
    </script>
<?php endif; ?>

</html>