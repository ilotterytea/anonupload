<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';

$accepted_mime_types = [];

foreach (FILE_ACCEPTED_MIME_TYPES as $k => $v) {
    $m = [];

    foreach ($v as $z) {
        array_push($m, "$k/$z");
    }

    array_push($accepted_mime_types, implode(', ', $m));
}

$accepted_mime_types = implode(', ', $accepted_mime_types);
?>
<html>

<head>
    <title><?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <main>
        <?php html_big_navbar() ?>

        <section class="box column">
            <div class="tab">
                <p>File Upload</p>
            </div>
            <div class="content">
                <form action="/upload.php" method="post" enctype="multipart/form-data" class="column gap-8">
                    <input type="file" name="file" required accept="<?= $accepted_mime_types ?>">
                    <button type="submit">Upload</button>
                </form>
            </div>
        </section>
    </main>
</body>

</html>