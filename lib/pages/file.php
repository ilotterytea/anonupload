<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils.php';

$file_name = "{$file->id}.{$file->extension}";
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head($file_name); ?>
</head>

<body>
    <section class="file-preview">
        <section class="preview">
            <?php html_file_full($file); ?>
        </section>

        <section class="control-panel">
            <div class="icon">
                <?= html_mini_icon(); ?>
            </div>
            <div class="metadata">
                <p>
                    <span id="file-id"><?= $file->id ?></span>.<span id="file-extension"><?= $file->extension ?></span>
                    (<span id="file-mime"><?= $file->mime ?></span>)
                </p>
                <p>uploaded <?= format_timestamp($file->uploaded_at) ?> ago</p>
            </div>
            <div class="control-buttons">
                <a href="<?= $file->url ?>" download="<?= $file_name ?>" class="button">
                    <img src="/static/img/icons/download.png" alt="download" title="download file" />
                </a>
                <a href="<?= $file->url ?>" class="button" target="_blank">
                    <img src="/static/img/icons/fullsize.png" alt="full size" title="open in full size" />
                </a>
            </div>
        </section>
    </section>
</body>

</html>