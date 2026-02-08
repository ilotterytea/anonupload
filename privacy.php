<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";

$file_path = "{$_SERVER['DOCUMENT_ROOT']}/PRIVACY.txt";
$data = [
    'content' => file_get_contents($file_path) ?: "We don't store anything personal about you.",
    'lastupdated' => (new DateTime())->setTimestamp(filemtime($file_path) ?: 0)
];

if ($data['lastupdated']->getTimestamp() === 0)
    $data['lastupdated'] = null;
?>
<!DOCTYPE html>
<html>

<head>
    <title>Privacy Policy - <?= CONFIG["instance"]["name"] ?></title>
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
        <h1>Privacy Policy</h1>
        <?php if ($data['lastupdated']): ?>
            <p><i>Last updated: <?= $data['lastupdated']->format('M d, Y') ?>
                    (<?= format_timestamp($data['lastupdated']) ?> ago)</i></p>
        <?php endif; ?>
        <hr>
        <pre><?= $data['content'] ?></pre>
    </main>
</body>

</html>