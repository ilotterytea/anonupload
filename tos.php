<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

$file_path = "{$_SERVER['DOCUMENT_ROOT']}/TOS.txt";
if (!file_exists($file_path)) {
    generate_alert('/', 'TOS is not set!', 500);
}

$data = [
    'content' => bbcode_parse(file_get_contents($file_path) ?: "Don't upload anything bad."),
    'last_updated' => (new DateTime())->setTimestamp(filemtime($file_path) ?: 0)
];

if ($data['last_updated']->getTimestamp() === 0)
    $data['last_updated'] = null;
?>
<!DOCTYPE html>
<html>

<head><?php html_head("terms of service"); ?></head>

<body>
    <header>
        <?php html_header(); ?>
        <h2>terms of service</h2>
        <?php if ($data['last_updated']): ?>
            <p>last updated: <?= $data['last_updated']->format('M d, Y') ?></p>
        <?php endif; ?>
    </header>
    <main>
        <?= $data['content'] ?>
    </main>
    <footer>
        <?php html_footer(); ?>
        <?php html_legal(); ?>
        <?php html_motd(); ?>
    </footer>
</body>

</html>