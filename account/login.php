<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

session_start();

if (isset($_SESSION['user'])) {
    http_response_code(303);
    header('Location: /account/index.php');
    exit("You've already authorized!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['password'])) {
        generate_alert('/account/login.php', 'No password set!', 400);
    }

    if (!isset($_POST['username'])) {
        generate_alert('/account/login.php', 'No username set!', 400);
    }

    if (
        USER->get_type() !== FileStorageType::Database &&
        !is_file(CONFIG['users']['path']) &&
        !file_put_contents(CONFIG['users']['path'], '')
    ) {
        generate_alert('/account/login.php', 'Failed to create a file for mod passwords!', 500);
    }

    $user = USER->get_user_by_name($_POST['username']);
    if ($user === null || !password_verify($_POST['password'], $user->password)) {
        generate_alert('/account/login.php', 'Invalid credentials!', 401);
    }

    $_SESSION['user'] = $user;

    generate_alert('/account/index.php', 'Authorized!');
}
?>
<html>

<head>
    <title>Log in to <?= CONFIG["instance"]["name"] ?></title>
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
        <h1>Log in to account</h1>
        <hr>
        <form action="/account/login.php" method="post">
            <table class="vertical">
                <tr>
                    <th>Username:</th>
                    <td><input type="text" name="username" required></td>
                </tr>
                <tr>
                    <th>Password:</th>
                    <td><input type="password" name="password" required></td>
                </tr>
            </table>
            <button type="submit" class="fancy">Log in</button>
        </form>
    </main>
</body>

</html>