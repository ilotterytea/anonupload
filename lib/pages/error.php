<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

USER->authorize_with_cookie();

$error_code = null;
$error_reason = null;

if (isset($error)) {
    $error = explode(" ", $error, 2);
    $error_code = intval($error[0]);
    $error_reason = $error[1];
} else {
    $error_code = 999;
    $error_reason = "Something went wrong";
}

if (IS_JSON_REQUEST) {
    http_response_code($error_code);
    exit(json_encode([
        'status_code' => $error_code,
        'message' => $error_reason,
        'data' => null
    ], JSON_UNESCAPED_SLASHES));
}

$image_name = null;
$dir_path = "{$_SERVER['DOCUMENT_ROOT']}/static/img/$error_code";
if (is_dir($dir_path)) {
    $names = glob("$dir_path/*.*");
    $c = count($names);
    if ($c > 0) {
        $image_name = basename($names[random_int(0, $c - 1)]);
    }
}
?>
<html>

<head>
    <title>
        <?= "$error_code $error_reason" ?> - <?= CONFIG["instance"]["name"] ?>
    </title>
    <meta name="description" content="<?= CONFIG["instance"]["name"] ?> is a simple, free and anonymous file sharing
    site. We do not store anything other than the files you upload.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="theme-color" content="#ffe1d4">
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <main class="full-size">
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>

        <div class="row grow justify-center">
            <section class="file-preview-wrapper" style="max-width: 256px;">
                <section class="box">
                    <div class="tab">
                        <p style="text-align:center"><?= "$error_code $error_reason" ?></p>
                    </div>
                    <div class="content column file-preview">
                        <?php if (isset($image_name)): ?>
                            <img src="/static/img/<?= "$error_code/$image_name" ?>" alt="There is nothing to see here.">
                        <?php else: ?>
                            <p>There is nothing to see here.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </section>
        </div>

        <?php html_mini_footer(); ?>
    </main>
</body>

</html>