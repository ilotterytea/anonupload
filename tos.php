<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";

$file_path = "{$_SERVER['DOCUMENT_ROOT']}/TOS.txt";
$data = [
    'content' => file_get_contents($file_path) ?: "Don't upload anything bad.",
    'lastupdated' => (new DateTime())->setTimestamp(filemtime($file_path) ?: 0)
];

if ($data['lastupdated']->getTimestamp() === 0)
    $data['lastupdated'] = null;
?>
<!DOCTYPE html>
<html>

<head><?php html_head("Terms of Service"); ?></head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <h1>Terms of Service</h1>
        <?php if ($data['lastupdated']): ?>
            <p><i>Last updated: <?= $data['lastupdated']->format('M d, Y') ?>
                    (<?= format_timestamp($data['lastupdated']) ?> ago)</i></p>
        <?php endif; ?>
        <hr>
        <pre><?= $data['content'] ?></pre>
    </main>
</body>

</html>