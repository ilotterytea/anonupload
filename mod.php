<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

session_start();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

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
?>
<html>

<head>
    <title>Moderation - <?= INSTANCE_NAME ?></title>
    <meta name="description" content="The moderation panel of <?= INSTANCE_NAME ?>">
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
        <?php if (isset($_SESSION['is_moderator'])): ?>
                <h1>Now you can access moderator related panels!</h1>
                <p><i>TODO: add more mod features here</i></p>
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
                    <button type="submit" class="fancy">Log in</button>
                </form>
        <?php endif; ?>
    </main>
</body>

</html>