<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(303);
    header('Location: /account/login.php');
    exit("You are not authorized!");
}

$user = $_SESSION['user'];
$user->token = bin2hex(random_bytes(16));
if (!USER->save($user)) {
    generate_alert('/account/index.php', 'Failed to log out! Try again later.');
}

unset($_SESSION['user']);
unset($_COOKIE['token']);

generate_alert('/account/index.php', 'Logged out!');