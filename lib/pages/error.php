<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';

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

// -- choosing error image
$brand_url = null;
$folder_path = '/static/instances/' . CONFIG['instance']['name'] . "/img/$error_code";

if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $folder_path)) {
    $folder_path = "/static/img/$error_code";
}

if (is_dir($_SERVER['DOCUMENT_ROOT'] . $folder_path)) {
    $files = glob($_SERVER['DOCUMENT_ROOT'] . "$folder_path/*.*");

    if (!empty($files)) {
        $file = basename($files[random_int(0, count($files) - 1)]);
        $brand_url = "$folder_path/$file";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("$error_code $error_reason"); ?>
    <meta http-equiv="refresh" content="5; url=<?= CONFIG['instance']['url'] ?>">
</head>

<body>
    <main>
        <section class="box error-page">
            <div class="tab">
                <p>
                    <?= "$error_code $error_reason" ?>
                </p>
            </div>
            <div class="content">
                <?php if (isset($brand_url)): ?>
                    <img src="<?= $brand_url ?>" alt="There is nothing to see here.">
                <?php else: ?>
                    <p>There is nothing to see here.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <footer>
        <?php html_footer(); ?>
    </footer>
</body>

</html>