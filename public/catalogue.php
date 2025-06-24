<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

session_start();

if (!isset($_SESSION['is_moderator']) && !FILE_CATALOG_PUBLIC) {
    http_response_code(403);
    exit;
}

$db = new PDO(DB_URL, DB_USER, DB_PASS);

$page = max(intval($_GET['p'] ?? '1') - 1, 0);
$limit = 20;

// counting max pages
$stmt = $db->query('SELECT COUNT(id) AS all_files FROM files WHERE id NOT IN (SELECT id FROM file_bans)');
$stmt->execute();

$max_pages = ceil(($stmt->fetch(PDO::FETCH_ASSOC)['all_files'] ?: 0) / $limit);
$page = min($page, $max_pages - 1);

// getting files
$offset = $page * $limit;

$stmt = $db->query("SELECT f.id, f.mime, f.extension
    FROM files f
    WHERE f.id NOT IN (SELECT id FROM file_bans)
    ORDER BY f.uploaded_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute();

$files = $stmt->fetchAll();

foreach ($files as &$f) {
    if (str_starts_with($f['mime'], 'video/')) {
        $f['color'] = 'blue';
    } else if ($f['mime'] == 'application/x-shockwave-flash') {
        $f['color'] = 'red';
    }

    $f['thumb_title'] = "{$f['mime']} ({$f['extension']})";
}
unset($f);
?>
<!DOCTYPE html>
<html>

<head>
    <title>File Catalogue &lpar;Page <?= $page + 1 ?>/<?= $max_pages ?>&rpar; - <?= INSTANCE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="theme-color" content="#ffe1d4">
</head>

<body>
    <main>
        <?php html_mini_navbar('Page ' . ($page + 1) . '/' . $max_pages) ?>

        <section class="row align-center">
            <?php if ($page - 1 >= 0): ?>
                <a href="/catalogue.php?p=<?= $page ?>">&larr; Previous page</a>
            <?php endif; ?>
            <?php if ($page + 2 <= $max_pages): ?>
                <a href="/catalogue.php?p=<?= $page + 2 ?>" style="margin-left:auto">&rarr; Next page</a>
            <?php endif; ?>
        </section>

        <section class="wall">
            <?php foreach ($files as $file): ?>
                <div class="brick<?= isset($file['color']) ? " {$file['color']}" : '' ?>">
                    <a href="/<?= sprintf('%s.%s', $file['id'], $file['extension']) ?>">
                        <i title="<?= $file['thumb_title'] ?>">
                            <?php if (str_starts_with($file['mime'], 'image/') || str_starts_with($file['mime'], 'video/')): ?>
                                <img src="<?= sprintf('%s/%s.webp', FILE_THUMBNAIL_DIRECTORY_PREFIX, $file['id']) ?>"
                                    alt="No thumbnail." loading="lazy">
                            <?php elseif (str_starts_with($file['mime'], 'audio/')): ?>
                                <img src="/static/img/icons/file_audio.png" alt="No thumbnail." loading="lazy" width="64"
                                    height="64">
                            <?php elseif (str_starts_with($file['mime'], 'text/')): ?>
                                <img src="/static/img/icons/file_text.png" alt="No thumbnail." loading="lazy" width="64"
                                    height="64">
                            <?php elseif ($file['mime'] == 'application/x-shockwave-flash'): ?>
                                <img src="/static/img/icons/file_flash.png" alt="No thumbnail." loading="lazy" width="64"
                                    height="64">
                            <?php else: ?>
                                <img src="/static/img/icons/file.png" alt="No thumbnail." width="64" height="64">
                            <?php endif; ?>
                        </i>
                    </a>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>

</html>