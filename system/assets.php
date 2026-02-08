<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

if (!USER->authorize_with_cookie()) {
    generate_alert('/account/', 'You must be authorized!', 303);
}

if ($_SESSION['user']->role->as_value() < UserRole::Administrator->as_value()) {
    generate_alert('/account/', 'You are not allowed to make changes on this page!', 401);
}

$assets = [
    "banner" => "/static/img/brand/big",
    "logo" => "/static/img/brand/mini",
    "404" => "/static/img/404",
    "403" => "/static/img/403"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($assets as $asset => $path) {
        if (isset($_FILES[$asset])) {
            $full_path = "{$_SERVER['DOCUMENT_ROOT']}/$path";
            if (!is_dir($full_path) && !mkdir($full_path, 0777, true)) {
                generate_alert('/system/assets.php', "Failed to make a $asset directory", 500);
            }

            $name = basename($_FILES[$asset]['name']);

            if (!move_uploaded_file($_FILES[$asset]['tmp_name'], "$full_path/$name")) {
                generate_alert('/system/assets.php', "Failed to move the $asset image", 500);
            }

            generate_alert('/system/assets.php', "Uploaded a new $asset image!", 201);
        }
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case "deletebanner":
                if (!isset($_POST['name']) || empty($_POST['name'])) {
                    generate_alert('/system/assets.php', 'Banner name must be specified!', 400);
                }

                $name = $_POST['name'];
                if (!unlink("$full_banner_path/$name")) {
                    generate_alert('/system/assets.php', 'Failed to remove the banner', 500);
                }

                if (count(glob("$full_banner_path/*.*")) === 0 && !rmdir($full_banner_path)) {
                    generate_alert('/system/assets.php', 'Failed to remove the banner directory', 500);
                }

                generate_alert('/system/assets.php', "Deleted banner $name!", 200);
            case "deletelogo":
                if (!isset($_POST['name']) || empty($_POST['name'])) {
                    generate_alert('/system/assets.php', 'Logo name must be specified!', 400);
                }

                $name = $_POST['name'];
                if (!unlink("$full_logo_path/$name")) {
                    generate_alert('/system/assets.php', 'Failed to remove the logo', 500);
                }

                if (count(glob("$full_logo_path/*.*")) === 0 && !rmdir($full_logo_path)) {
                    generate_alert('/system/assets.php', 'Failed to remove the logo directory', 500);
                }

                generate_alert('/system/assets.php', "Deleted logo $name!", 200);
            default:
                generate_alert('/system/assets.php', 'Unsupported action', 400);
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>System assets - <?= CONFIG["instance"]["name"] ?></title>
    <meta name="description" content="The system panel of <?= CONFIG["instance"]["name"] ?>">
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
        <h1>System assets</h1>
        <hr>
        <?php foreach ($assets as $asset => $path): ?>
            <h2><?= $asset ?></h2>
            <?php $files = array_map(fn($x) => basename($x), glob("{$_SERVER['DOCUMENT_ROOT']}/$path/*.*")); ?>
            <?php if (empty($files)): ?>
                <p>There are no <?= $asset ?> files yet.</p>
            <?php endif; ?>
            <div class="wall">
                <?php foreach ($files as $file): ?>
                    <div class="box column">
                        <div class="row grow justify-center align-center">
                            <img src="<?= "$path/$file" ?>" alt="<?= $file ?>" width="128">
                        </div>
                        <h3><?= $file ?></h3>
                        <form action="/system/assets.php" method="post">
                            <input type="text" name="action" value="delete<?= $asset ?>" style="display: none">
                            <input type="text" name="name" value="<?= $file ?>" style="display: none">
                            <button type="submit"><img src="/static/img/icons/cross.png" alt="Delete"></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form action="/system/assets.php" method="post" enctype="multipart/form-data">
                <label for="<?= $asset ?>">Upload a new <?= $asset ?>:</label>
                <input type="file" name="<?= $asset ?>" id="<?= $asset ?>" accept="image/*" required>
                <button type="submit">Upload</button>
            </form>
        <?php endforeach; ?>
    </main>
</body>

</html>