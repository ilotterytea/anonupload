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
    if (!isset($_POST['password'], $_POST['username'])) {
        generate_alert('/account/login.php', 'No credentials set!', 400);
    }

    $user = USER->get_user_by_name($_POST['username']);
    if ($user === null || !password_verify($_POST['password'], $user->password)) {
        generate_alert('/account/login.php', 'Invalid credentials!', 401);
    }

    $_SESSION['user'] = $user;
    setcookie('token', $user->token, time() + CONFIG['users']['cookietime'], '/');

    generate_alert('/account/index.php', 'Authorized!');
}
?>
<!DOCTYPE html>
<html>

<head><?php html_head("Log in to account"); ?></head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>
            Log in to <?= CONFIG['instance']['name'] ?> account
        </h1>
        <hr>
        <form action="/account/login.php" method="post">
            <table class="vertical left">
                <tr>
                    <th>Username:</th>
                    <td><input type="text" name="username" required></td>
                </tr>
                <tr>
                    <th>Password:</th>
                    <td><input type="password" name="password" required></td>
                </tr>
            </table>
            <div class="row gap-8 align-center">
                <button type="submit" class="fancy">Log in</button>
                <?php if (CONFIG['users']['allowregistration'] || !file_exists(CONFIG['users']['path'])): ?>
                    <a href="/account/register.php">Register</a>
                <?php endif; ?>
            </div>
        </form>
    </main>
</body>

</html>