<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function html_big_navbar()
{
    echo '' ?>
    <section class="column justify-center align-center navbar">
        <div class="column justify-center grow">
            <h1><img src="/static/img/brand/big.webp" alt="<?= INSTANCE_NAME ?>"></h1>
        </div>

        <div class="row justify-center">

        </div>
    </section>
    <?php ;
}

function html_footer()
{
    $files = glob(FILE_DIRECTORY . "/*.*");
    $file_size = 0;

    foreach ($files as $file) {
        $file_size += filesize($file);
    }

    $file_size /= 1024 * 1024;

    echo '' ?>
    <footer class="column justify-center align-center gap-8">
        <?php if (array_key_exists(INSTANCE_URL, INSTANCE_MIRRORS)): ?>
            <p>You are using a mirror for <?= INSTANCE_MIRRORS[INSTANCE_URL] ?>. <a href="<?= INSTANCE_ORIGINAL_URL ?>">[ Check
                    out the origin website ]</a></p>
        <?php elseif (!empty(INSTANCE_MIRRORS)): ?>
            <div class="row gap-8">
                <p>Mirrors:</p>
                <ul class="row gap-4" style="list-style: none;">
                    <?php foreach (INSTANCE_MIRRORS as $url => $name): ?>
                        <li><a href="<?= $url ?>"><?= $name ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p>Serving <?= count($files) ?> files and <?= sprintf("%.2f", $file_size) ?>MB of active content</p>
    </footer>
    <?php ;
}