<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function html_big_navbar()
{
    echo '' ?>
    <section class="column justify-center align-center gap-8 navbar">
        <div class="column justify-center grow">
            <a href="/">
                <h1><img src="/static/img/brand/big.webp" alt="<?= INSTANCE_NAME ?>"></h1>
            </a>
        </div>

        <div class="row gap-8 justify-center">
            <a href="/">
                <button>Home</button>
            </a>
            <?php if (FILE_CATALOG_RANDOM): ?>
                <a href="/?random">
                    <button>I'm Feeling Lucky</button>
                </a>
            <?php endif; ?>
            <a href="/uploaders.php">
                <button>Uploaders</button>
            </a>
            <?php if (isset($_SESSION['is_moderator'])): ?>
                <a href="/mod.php">
                    <button>Moderation</button>
                </a>
            <?php endif; ?>
        </div>
    </section>
    <?php ;
}

function html_mini_navbar()
{
    echo '' ?>
    <section class="row align-center gap-8 navbar">
        <a href="/" class="row gap-8 align-bottom" style="text-decoration:none;color:inherit;">
            <img src="/static/img/brand/mini.webp" alt="">
            <h2><?= INSTANCE_NAME ?></h2>
        </a>

        <div class="row gap-8 align-bottom" style="height: 100%">
            <a href="/">
                <button>Home</button>
            </a>
            <?php if (FILE_CATALOG_RANDOM): ?>
                <a href="/?random">
                    <button>I'm Feeling Lucky</button>
                </a>
            <?php endif; ?>
            <a href="/uploaders.php">
                <button>Uploaders</button>
            </a>
            <?php if (isset($_SESSION['is_moderator'])): ?>
                <a href="/mod.php">
                    <button>Moderation</button>
                </a>
            <?php endif; ?>
        </div>
    </section>
    <?php ;
}

function html_footer()
{
    $db = new PDO(DB_URL, DB_USER, DB_PASS);
    $stmt = $db->query('SELECT COUNT(*) AS file_count, SUM(size) AS file_overall_size FROM files');
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $file_count = $row['file_count'];
    $file_size = $row['file_overall_size'];

    $suffix = 'MB';
    $file_size /= 1024 * 1024; // MB

    if ($file_size >= 1024) {
        $file_size /= 1024;
        $suffix = 'GB';
    }

    $file_size = sprintf('%.2f%s', $file_size, $suffix);

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
        <?php if (!empty(INSTANCE_DONATIONS) && is_array(INSTANCE_DONATIONS)): ?>
            <div class="row gap-8">
                <details>
                    <summary>Donate</summary>

                    <table class="vertical">
                        <?php foreach (INSTANCE_DONATIONS as $k => $v): ?>
                            <tr>
                                <th><?= $k ?></th>
                                <td><?= $v ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </details>
            </div>
        <?php elseif (!empty(INSTANCE_DONATIONS) && is_string(INSTANCE_DONATIONS)): ?>
            <p><a href="<?= INSTANCE_DONATIONS ?>">[Donate]</a></p>
        <?php endif; ?>
        <?php if (!empty(INSTANCE_TOR)): ?>
            <p><a href="<?= INSTANCE_TOR ?>">[Tor]</a></p>
        <?php endif; ?>
        <p>Serving <?= $file_count ?> files and <?= $file_size ?> of active content</p>
    </footer>
    <?php ;
}