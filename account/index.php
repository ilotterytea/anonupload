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
<!DOCTYPE html>
<html>

<head><?php html_head("Account"); ?></head>

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
                    <?php if ($user->role->as_value() >= UserRole::Moderator->as_value()): ?>
                        <a href="/moderation/index.php">Moderation</a>
                    <?php endif; ?>
                    <?php if ($user->role->as_value() >= UserRole::Administrator->as_value()): ?>
                        <a href="/system/index.php">System</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>