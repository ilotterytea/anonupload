<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['password'])) {
        generate_alert('/mod.php', 'No password set!', 400, null);
        exit();
    }

    if (!is_file(MOD_FILE) && !file_put_contents(MOD_FILE, '')) {
        generate_alert('/mod.php', 'Failed to create a file for mod passwords!', 500, null);
        exit();
    }

    $password_file = explode(PHP_EOL, file_get_contents(MOD_FILE));

    $is_authorized = false;

    foreach ($password_file as $p) {
        $is_authorized = password_verify($_POST['password'], $p);
        if ($is_authorized) {
            break;
        }
    }

    if (!$is_authorized) {
        generate_alert('/mod.php', 'Unauthorized!', 401, null);
        exit();
    }

    $_SESSION['is_moderator'] = $is_authorized;

    generate_alert('/mod.php', 'Authorized!', 200, null);
    exit();
}

$files = [];

$page = intval($_GET['fp'] ?? '1');
$max_pages = 0;

if (isset($_SESSION['is_moderator'])) {
    $quantity = 10;

    $filelist = glob(FILE_UPLOAD_DIRECTORY . '/*.*');
    usort($filelist, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $selected_files = array_slice($filelist, ($page - 1) * $quantity, $quantity);

    $max_pages = ceil(count($filelist) / $quantity);

    foreach ($selected_files as $f) {
        $name = basename($f);
        $id = explode('.', $name);
        array_push($files, [
            'name' => $name,
            'id' => $id[0],
            'extension' => $id[1]
        ]);
    }
}
?>
<html>

<head>
    <title>Moderation - <?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <?php if (isset($_SESSION['is_moderator'])): ?>
            <?php if (!empty($files)): ?>
                <section class="column gap-8">
                    <h2>Files (Page <?= $page ?> / <?= $max_pages ?>)</h2>
                    <hr>
                    <table class="left">
                        <tr>
                            <?php if (FILE_THUMBNAILS): ?>
                                <th style="width: 10%;"></th>
                            <?php endif; ?>
                            <th>File</th>
                            <th>Age</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach ($files as $f): ?>
                            <tr>
                                <td>
                                    <?php if (FILE_THUMBNAILS): ?>
                                        <img src="<?= sprintf('%s/%s.webp', FILE_THUMBNAIL_DIRECTORY_PREFIX, $f['id']) ?>" alt=""
                                            height="24">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/<?= $f['name'] ?>" target="_blank"><?= $f['name'] ?></a>
                                </td>
                                <td>
                                    <?= format_timestamp(time() - filemtime(sprintf('%s/%s', FILE_UPLOAD_DIRECTORY, $f['name']))) ?>
                                </td>
                                <td>
                                    <a href="/delete.php?f=<?= $f['name'] ?>&r=/mod.php">
                                        <button>
                                            <img src="/static/img/icons/delete.png" alt="Delete">
                                        </button>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <div class="row gap-8">
                        <?php if ($page - 1 >= 1): ?>
                            <a href="/mod.php?fp=<?= $page - 1 ?>">
                                <button>Previous</button>
                            </a>
                        <?php endif; ?>
                        <?php if ($page + 1 <= $max_pages): ?>
                            <a href="/mod.php?fp=<?= $page + 1 ?>">
                                <button>Next</button>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else: ?>
                <p><i>No files to moderate...</i></p>
            <?php endif; ?>
        <?php else: ?>
            <h1>Log in to the moderation system</h1>
            <hr>
            <form action="/mod.php" method="post">
                <table>
                    <tr>
                        <th>Password:</th>
                        <td><input type="password" name="password" required></td>
                    </tr>
                </table>
                <button type="submit">Log in</button>
            </form>
        <?php endif; ?>
    </main>
</body>

</html>