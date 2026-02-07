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

if (!CONFIG['users']['allowregistration'] && file_exists(CONFIG['users']['path'])) {
    generate_alert('/', 'Account registration is disabled on this instance!', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['password'], $_POST['username'], $_POST['tos'])) {
        generate_alert('/account/register.php', 'Invalid request!', 400);
    }

    $usernamelength = strlen($_POST['username']);

    if (
        !preg_match(CONFIG['users']['usernameregex'], $_POST['username'])
        || $usernamelength < CONFIG['users']['usernameminlength'] ||
        $usernamelength > CONFIG['users']['usernamemaxlength']
    ) {
        generate_alert('/account/register.php', 'Incorrect username!', 400);
    }

    if (strlen($_POST['password']) < CONFIG['users']['passwordminlength']) {
        generate_alert('/account/register.php', 'Incorrect password!', 400);
    }

    $user = USER->get_user_by_name($_POST['username']);
    if ($user !== null) {
        generate_alert('/account/register.php', 'This username is already taken!', 409);
    }

    if (
        !USER->create(
            $_POST['username'],
            $_POST['password'],
            file_exists(CONFIG['users']['path']) ? UserRole::User : UserRole::Administrator
        )
    ) {
        generate_alert('/account/register.php', 'Failed to create a new user! Try again later.', 500);
    }

    generate_alert('/account/index.php', 'Created! Now you need to log in.', 201);
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Register a new account -
        <?= CONFIG["instance"]["name"] ?>
    </title>
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
        <?php if (!file_exists(CONFIG['users']['path'])): ?>
            <div class="box">
                <p>The first registered user will be the administrator.</p>
            </div>
        <?php endif; ?>
        <h1>
            Register a new
            <?= CONFIG['instance']['name'] ?> account
        </h1>
        <hr>
        <form action="/account/register.php" method="post">
            <table class="vertical">
                <tr>
                    <th>Username:</th>
                    <td>
                        <input type="text" name="username" required>
                        <p class="hint">Must be between <?= CONFIG['users']['usernameminlength'] ?> and
                            <?= CONFIG['users']['usernamemaxlength'] ?> characters.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Password:</th>
                    <td>
                        <input type="password" name="password" required>
                        <p class="hint">Must be at least <?= CONFIG['users']['passwordminlength'] ?> characters.</p>
                        <p class="hint">Your account won't be recovered if you forget your password.</p>
                    </td>
                </tr>
                <tr>
                    <th><input type="checkbox" name="tos" value="1" required></th>
                    <td>I accept the <a href="/tos.php">TOS</a> and <a href="/privacy.php">Privacy Policy</a></td>
                </tr>
            </table>
            <div class="row gap-8 align-center">
                <button type="submit" class="fancy">Register</button>
            </div>
        </form>
    </main>
</body>

</html>