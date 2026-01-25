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

unset($_SESSION['user']);
unset($_COOKIE['token']);

generate_alert('/account/index.php', 'Logged out!');