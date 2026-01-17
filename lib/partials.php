<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

function html_big_navbar()
{
    $brand_url = '/static/img/brand/big.webp';
    $static_folder = '/static/img/brand/big';
    $brand_folder = $_SERVER['DOCUMENT_ROOT'] . $static_folder;

    if (is_dir($brand_folder)) {
        $files = glob("$brand_folder/*.*");

        if (!empty($files)) {
            $file = basename($files[random_int(0, count($files) - 1)]);
            $brand_url = "$static_folder/$file";
        }
    }

    $line = null;
    $line_path = $_SERVER['DOCUMENT_ROOT'] . '/../MOTD.txt';
    if (file_exists($line_path) && $contents = file_get_contents($line_path)) {
        $lines = explode("\n", trim($contents));
        $line_count = count($lines);
        if ($line_count > 0) {
            $line = $lines[intval(date('j')) % $line_count];
        }
    }

    echo '' ?>
    <section class="column justify-center align-center gap-8 navbar">
        <div class="column align-center justify-center grow">
            <a href="/">
                <h1><img src="<?= $brand_url ?>" alt="<?= CONFIG["instance"]["name"] ?>"></h1>
            </a>
            <?php if (isset($line)): ?>
                <p><i>&quot;<?= $line ?>&quot;</i></p>
            <?php endif; ?>
        </div>

        <div class="row gap-8 justify-center">
            <a href="/">
                <button>Home</button>
            </a>
            <?php if (CONFIG["filecatalog"]["public"] || isset($_SESSION['is_moderator'])): ?>
                <a href="/catalogue.php">
                    <button>Catalogue</button>
                </a>
            <?php endif; ?>
            <?php if (CONFIG["supriseme"]["enable"]): ?>
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

function html_mini_navbar(string|null $subtitle = null, string $title = CONFIG['instance']['name'])
{
    $brand_url = '/static/img/brand/mini.webp';
    $static_folder = '/static/img/brand/mini';
    $brand_folder = $_SERVER['DOCUMENT_ROOT'] . $static_folder;

    if (is_dir($brand_folder)) {
        $files = glob("$brand_folder/*.*");

        if (!empty($files)) {
            $file = basename($files[random_int(0, count($files) - 1)]);
            $brand_url = "$static_folder/$file";
        }
    }

    echo '' ?>
    <section class="row align-bottom gap-8 navbar wrap">
        <a href="/" class="row gap-8 align-bottom" style="text-decoration:none;color:inherit;">
            <img src="<?= $brand_url ?>" alt="">
            <div class="column">
                <?php if ($subtitle): ?>
                    <p class="font-small"><?= $subtitle ?></p>
                <?php endif; ?>
                <h2><?= $title ?></h2>
            </div>
        </a>

        <div class="row gap-8 align-bottom">
            <a href="/">
                <button>Home</button>
            </a>
            <?php if (CONFIG["filecatalog"]["public"] || isset($_SESSION['is_moderator'])): ?>
                <a href="/catalogue.php">
                    <button>Catalogue</button>
                </a>
            <?php endif; ?>
            <?php if (CONFIG["supriseme"]["enable"]): ?>
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
    $db = new PDO(CONFIG["database"]["url"], CONFIG["database"]["user"], CONFIG["database"]["pass"]);
    $stmt = $db->query('SELECT COUNT(*) AS file_count, SUM(size) AS file_overall_size FROM files WHERE id NOT IN (SELECT id FROM file_bans)');
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

    $commit = [
        'timestamp' => (int) trim(shell_exec('git show -s --format=%ct HEAD')),
        'sha' => trim(shell_exec('git rev-parse HEAD'))
    ];

    echo '' ?>
    <footer class="column justify-center align-center gap-8">
        <?php if (array_key_exists(CONFIG["instance"]["url"], CONFIG["instance"]["mirrors"])): ?>
            <p>You are using a mirror for <?= CONFIG["instance"]["mirrors"][CONFIG["instance"]["url"]] ?>. <a href="<?= CONFIG["instance"]["url"] ?>">[ Check
                    out the origin website ]</a></p>
        <?php elseif (!empty(CONFIG["instance"]["mirrors"])): ?>
            <div class="row gap-8">
                <p>Mirrors:</p>
                <ul class="row gap-4" style="list-style: none;">
                    <?php foreach (CONFIG["instance"]["mirrors"] as $url => $name): ?>
                        <li><a href="<?= $url ?>"><?= $name ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="row gap-8">
            <?php foreach (CONFIG["instance"]["footerlinks"] as $title => $link): ?>
                <p><a href="<?= $link ?>"><?= $title ?></a></p>
            <?php endforeach; ?>
        </div>
        <p>
            Serving <?= $file_count ?> files and <?= $file_size ?> of active content
            <?php if (CONFIG["stats"]["enable"]): ?>
                <a href="/stats.php">
                    <img src="/static/img/icons/stats.png" alt="[Stats]">
                </a>
            <?php endif; ?>
        </p>
        <?php if (isset($commit)): ?>
            <p style="font-size:10px;">
                Last updated <?= format_timestamp((new DateTime())->setTimestamp($commit['timestamp'])) ?> ago
                <a href="https://git.ilt.su/services/anonupload.git/commit/?id=<?= $commit['sha'] ?>">
                    (commit <?= substr($commit['sha'], 0, 7) ?>)
                </a>
            </p>
        <?php endif; ?>
    </footer>
    <?php ;
}

function html_mini_footer()
{
    echo '' ?>
    <footer class="column justify-center align-bottom gap-8 font-small mini-footer">
        <p>
            All trademarks and copyrights belong to their respective owners.
            The uploader is responsible for any content shared here.
        </p>
    </footer>
    <?php ;
}