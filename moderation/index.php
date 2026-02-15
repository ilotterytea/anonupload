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

if ($user->role->as_value() < UserRole::Moderator->as_value()) {
    generate_alert('/account/', 'You are not a moderator!', 401);
}
?>
<!DOCTYPE html>
<html>

<head><?php html_head("Moderation"); ?></head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>Moderation</h1>
        <hr>
        <div>
            <table class="vertical left">
                <tr>
                    <th><a href="/moderation/approve.php">[Media Pending Approval]</a></th>
                    <td>Approve files.</td>
                </tr>
                <tr>
                    <th><a href="/files/index.php">[File catalogue]</a></th>
                    <td>View all files</td>
                </tr>
                <tr>
                    <th><a href="/moderation/reports.php">[Report reviewal]</a></th>
                    <td>Review file reports</td>
                </tr>
            </table>
        </div>
    </main>
</body>

</html>