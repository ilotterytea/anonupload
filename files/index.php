<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

USER->authorize_with_cookie();

if (!CONFIG["filecatalog"]["public"] && (!isset($_SESSION['user']) || $_SESSION['user']->role->as_value() < UserRole::Moderator->as_value())) {
    generate_alert('/account/', 'You are not allowed to access this page!', 403);
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
                <form action="/files/index.php" method="get">
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
                            <?php html_file_brick($file); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- SCROLL -->
                <div class="row">
                    <div class="box row gap-8">
                        <?php if ($page - 1 >= 0): ?>
                            <a href="/files/index.php?p=<?= $page ?>&sort=<?= $sort ?>">
                                <button>Previous</button>
                            </a>
                        <?php endif; ?>
                        <?php if ($page + 2 <= $max_pages): ?>
                            <a href="/files/index.php?p=<?= $page + 2 ?>&sort=<?= $sort ?>" style="margin-left:auto">
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