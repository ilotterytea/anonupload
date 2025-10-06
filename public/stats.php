<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

session_start();

if (!STATS_PUBLIC && !isset($_SESSION['is_moderator'])) {
    http_response_code(403);
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

// uploaded files stats
$stmt = $db->query("SELECT YEAR(uploaded_at) AS year, QUARTER(uploaded_at) AS quarter, COUNT(*) AS file_count
    FROM files
    WHERE uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    GROUP BY YEAR(uploaded_at), QUARTER(uploaded_at)
    ORDER BY year, quarter
");
$uploaded_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// recent images & videos
if (STATS_LAST_FILES) {
    $stmt = $db->query("SELECT id, extension FROM files WHERE mime LIKE 'image/%' ORDER BY uploaded_at DESC LIMIT 5");
    $recent_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT id, extension FROM files WHERE mime LIKE 'video/%' ORDER BY uploaded_at DESC LIMIT 5");
    $recent_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// the most viewed files
if (STATS_MOST_VIEWED) {
    $stmt = $db->query("SELECT id, extension, mime FROM files ORDER BY views DESC LIMIT 5");
    $most_viewed_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($most_viewed_files as &$f) {
        if (str_starts_with($f['mime'], 'video/')) {
            $f['color'] = 'blue';
        } else if ($f['mime'] == 'application/x-shockwave-flash') {
            $f['color'] = 'red';
        }
    }
    unset($f);
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
$stmt = $db->query("SELECT COUNT(*) AS file_count, SUM(size) AS active_content, AVG(size) AS approx_filesize,
    COUNT(*) / TIMESTAMPDIFF(MINUTE, MIN(uploaded_at), MAX(uploaded_at)) AS avg_upload_rate
    FROM files
    WHERE id NOT IN (SELECT id FROM file_bans)
");
$file_stats = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];

if (STATS_DISKSIZE > 0) {
    $file_stats['future_file_count'] = floor(STATS_DISKSIZE / $file_stats['approx_filesize']);
    if ($file_stats['avg_upload_rate'] > 0.01) {
        $file_stats['estimated_time'] = floor((STATS_DISKSIZE - $file_stats['active_content']) / $file_stats['avg_upload_rate']);
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

<head>
    <title>Statistics - <?= INSTANCE_NAME ?></title>
    <meta name="description" content="The statistics of <?= INSTANCE_NAME ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="theme-color" content="#ffe1d4">
    <meta name="robots" content="noindex, nofollow">
</head>

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
                    <div class="brick">
                        <a href="/<?= sprintf('%s.%s', $i['id'], $i['extension']) ?>">
                            <img src="<?= sprintf('%s/%s.webp', FILE_THUMBNAIL_DIRECTORY_PREFIX, $i['id']) ?>"
                                alt="No thumbnail." loading="lazy">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($recent_videos)): ?>
            <h2>Recent videos</h2>
            <div class="wall">
                <?php foreach ($recent_videos as $i): ?>
                    <div class="brick blue">
                        <a href="/<?= sprintf('%s.%s', $i['id'], $i['extension']) ?>">
                            <img src="<?= sprintf('%s/%s.webp', FILE_THUMBNAIL_DIRECTORY_PREFIX, $i['id']) ?>"
                                alt="No thumbnail." loading="lazy">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($most_viewed_files)): ?>
            <h2>The most viewed files</h2>
            <div class="wall">
                <?php foreach ($most_viewed_files as $file): ?>
                    <div class="brick<?= isset($file['color']) ? " {$file['color']}" : '' ?>">
                        <a href="/<?= sprintf('%s.%s', $file['id'], $file['extension']) ?>">
                            <?php if (str_starts_with($file['mime'], 'image/') || str_starts_with($file['mime'], 'video/')): ?>
                                <img src="<?= sprintf('%s/%s.webp', FILE_THUMBNAIL_DIRECTORY_PREFIX, $file['id']) ?>"
                                    alt="No thumbnail." loading="lazy">
                            <?php elseif (str_starts_with($file['mime'], 'audio/')): ?>
                                <img src="/static/img/icons/file_audio.png" alt="No thumbnail." loading="lazy"
                                    class="thumbnail stock">
                            <?php elseif (str_starts_with($file['mime'], 'text/')): ?>
                                <img src="/static/img/icons/file_text.png" alt="No thumbnail." loading="lazy"
                                    class="thumbnail stock">
                            <?php elseif ($file['mime'] == 'application/x-shockwave-flash'): ?>
                                <img src="/static/img/icons/file_flash.png" alt="No thumbnail." loading="lazy"
                                    class="thumbnail stock">
                            <?php else: ?>
                                <img src="/static/img/icons/file.png" alt="No thumbnail." class="thumbnail stock">
                            <?php endif; ?>
                        </a>
                    </div>
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
    </main>
</body>

<?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/static/scripts/chart.js')): ?>
    <script src="/static/scripts/chart.js"></script>
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