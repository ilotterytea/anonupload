<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

if (!USER->authorize_with_cookie()) {
    http_response_code(303);
    header('Location: /account/login.php');
    exit("You must be authorized!");
}

$user = $_SESSION['user'];
?>
<html>

<head>
    <title>Account - <?= CONFIG["instance"]["name"] ?></title>
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
        <h1>Hello, <?= $user->name ?></h1>
        <hr>
        <?php if ($user->role->as_value() > UserRole::User->as_value()): ?>
            <div>
                <h2>Related links</h2>
                <div class="row gap-8">
                    <?php if ($user->role === UserRole::Moderator): ?>
                        <a href="/moderation/index.php">Moderation</a>
                    <?php endif; ?>
                    <?php if ($user->role === UserRole::Administrator): ?>
                            <a href="/system/index.php">System</a>
                        <?php endif; ?>
                    </div>
                </div>
        <?php endif; ?>
    </main>
</body>

</html>