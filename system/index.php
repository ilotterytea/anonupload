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

$registered_files = STORAGE->count_registered_files();
$uploaded_files = STORAGE->count_uploaded_files();
?>
<!DOCTYPE html>
<html>

<head>
    <title>System - <?= CONFIG["instance"]["name"] ?></title>
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
        <h1>System panel</h1>
        <hr>
        <div>
            <table class="vertical left">
                <tr>
                    <th><a href="/system/reindex.php">[Re-index files]</a></th>
                    <td>
                        <p>Register new files and delete missing ones.</p>
                        <p style="font-size:12px">
                            <?= $registered_files ?> of
                            <?= $uploaded_files ?> files registered.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <a href="/system/assets.php">[System assets]</a>
                    </th>
                    <td>Change instance's logo, banners and error images.</td>
                </tr>
                <tr>
                    <th>
                        <a href="/system/config.php">[System configuration]</a>
                    </th>
                    <td>Accept more file types, change the file directories, file storage and more...</td>
                </tr>
            </table>
        </div>
    </main>
</body>

</html>