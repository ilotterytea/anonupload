<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

session_start();

if (!isset($_SESSION['is_moderator']) && !CONFIG["filecatalog"]["public"]) {
    http_response_code(403);
    exit;
}

$page = max(intval($_GET['p'] ?? '1') - 1, 0);
$limit = CONFIG["filecatalog"]["limit"];

$sort = $_GET['sort'] ?? 'recent';

// counting max pages
$max_pages = STORAGE->count_pages($limit);
$page = min($page, $max_pages - 1);

// getting files
$files = STORAGE->get_files($page, $sort);

foreach ($files as &$f) {
    $name = $f->title ?: "{$f->id}.{$f->extension}";
    $f->title = "{$f->id} // {$f->mime} ({$f->extension})";
}
unset($f);
?>
<!DOCTYPE html>
<html>

<head>
    <title>File Catalogue &lpar;Page <?= $page + 1 ?>/<?= $max_pages ?>&rpar; - <?= CONFIG["instance"]["name"] ?>
    </title>
    <meta name="description" content="Library of <?= CONFIG["instance"]["name"] ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffe1d4">
</head>

<body>
    <main class="full-size">
        <?php html_mini_navbar('Page ' . ($page + 1) . '/' . $max_pages, "Library of " . CONFIG["instance"]["name"]) ?>

        <div class="grow row gap-8">
            <!-- SIDE BAR -->
            <div class="column gap-8">
                <form action="/catalogue.php" method="get">
                    <div class="box">
                        <div class="tab">
                            Search
                        </div>
                        <div class="content column gap-8">
                            <label for="sort">Sort by</label>
                            <select name="sort" id="sort">
                                <option value="recent" <?= $sort == 'recent' ? 'selected' : '' ?>>Recent</option>
                                <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="most_viewed" <?= $sort == 'most_viewed' ? 'selected' : '' ?>>Most
                                    viewed</option>
                                <option value="least_viewed" <?= $sort == 'least_viewed' ? 'selected' : '' ?>>Least
                                    viewed</option>
                            </select>
                            <button type="submit">Search</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- CONTENT -->
            <div class="column gap-8 grow">
                <!-- FILES -->
                <div class="box">
                    <div class="tab">
                        <p>Files</p>
                    </div>

                    <div class="content wall">
                        <?php foreach ($files as $file): ?>
                            <div class="brick<?= isset($file->color) ? " {$file->color}" : '' ?>">
                                <a href="/<?= "{$file->id}.{$file->extension}" ?>">
                                    <i title="<?= $file->title ?>">
                                        <?php if (str_starts_with($file->mime, 'image/') || str_starts_with($file->mime, 'video/')): ?>
                                            <img src="<?= sprintf('%s/%s.webp', CONFIG["thumbnails"]["url"], $file->id) ?>"
                                                alt="No thumbnail." loading="lazy">
                                        <?php elseif (str_starts_with($file->mime, 'audio/')): ?>
                                            <img src="/static/img/icons/file_audio.png" alt="No thumbnail." loading="lazy"
                                                class="thumbnail stock">
                                        <?php elseif (str_starts_with($file->mime, 'text/')): ?>
                                            <img src="/static/img/icons/file_text.png" alt="No thumbnail." loading="lazy"
                                                class="thumbnail stock">
                                        <?php elseif ($file->mime == 'application/x-shockwave-flash'): ?>
                                            <img src="/static/img/icons/file_flash.png" alt="No thumbnail." loading="lazy"
                                                class="thumbnail stock">
                                        <?php else: ?>
                                            <img src="/static/img/icons/file.png" alt="No thumbnail." class="thumbnail stock">
                                        <?php endif; ?>
                                    </i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- SCROLL -->
                <div class="row">
                    <div class="box row gap-8">
                        <?php if ($page - 1 >= 0): ?>
                            <a href="/catalogue.php?p=<?= $page ?>&sort=<?= $sort ?>">
                                <button>Previous</button>
                            </a>
                        <?php endif; ?>
                        <?php if ($page + 2 <= $max_pages): ?>
                            <a href="/catalogue.php?p=<?= $page + 2 ?>&sort=<?= $sort ?>" style="margin-left:auto">
                                <button>Next</button>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php html_mini_footer() ?>
    </main>
</body>

</html>