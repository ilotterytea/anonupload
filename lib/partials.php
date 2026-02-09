<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

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
    <navbar class="large">
        <div class="column align-center justify-center grow">
            <a href="/">
                <h1><img src="<?= $brand_url ?>" alt="<?= CONFIG["instance"]["name"] ?>"></h1>
            </a>
            <?php if (isset($line)): ?>
                <p><i>&quot;<?= $line ?>&quot;</i></p>
            <?php endif; ?>
        </div>

        <div class="links row gap-8 justify-center">
            <a href="/">
                <button>Home</button>
            </a>
            <?php if (CONFIG["filecatalog"]["public"] || (isset($_SESSION['user']) && $_SESSION['user']->role->as_value() >= UserRole::Moderator->as_value())): ?>
                <a href="/files/index.php">
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
            <a href="/account/index.php">
                <button>Account</button>
            </a>
        </div>

        <?php if (isset($_SESSION['user'])): ?>
            <div class="row gap-8 justify-center">
                <p>Signed in as <span class="username <?= $_SESSION['user']->role->name ?>">
                        <?= $_SESSION['user']->name ?>
                    </span></p>
                <a href="/account/logout.php"><img src="/static/img/icons/logout.png" alt="[Log out]" title="Log out"></a>
            </div>
        <?php endif; ?>
    </navbar>
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
    <navbar>
        <a href="/" class="row gap-8 align-bottom" style="text-decoration:none;color:inherit;">
            <img src="<?= $brand_url ?>" alt="">
            <div class="column">
                <?php if ($subtitle): ?>
                    <p class="font-small"><?= $subtitle ?></p>
                <?php endif; ?>
                <h2><?= $title ?></h2>
            </div>
        </a>

        <div class="links row gap-8 align-bottom">
            <a href="/">
                <button>Home</button>
            </a>
            <?php if (CONFIG["filecatalog"]["public"] || (isset($_SESSION['user']) && $_SESSION['user']->role->as_value() >= UserRole::Moderator->as_value())): ?>
                <a href="/files/index.php">
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
            <a href="/account/">
                <button>Account</button>
            </a>
        </div>

        <?php if (isset($_SESSION['user'])): ?>
            <div>
                <p>Signed in as <span
                        class="username <?= $_SESSION['user']->role->name ?>"><?= $_SESSION['user']->name ?></span></p>
                <a href="/account/logout.php"><img src="/static/img/icons/logout.png" alt="[Log out]" title="Log out"></a>
            </div>
        <?php endif; ?>
    </navbar>
    <?php ;
}

function html_footer()
{
    $out = STORAGE->count_file_and_size();

    $file_count = $out['file_count'];
    $file_size = $out['file_overall_size'];

    $suffix = 'MB';
    $file_size /= 1024 * 1024; // MB

    if ($file_size >= 1024) {
        $file_size /= 1024;
        $suffix = 'GB';
    }

    $file_size = sprintf('%.2f%s', $file_size, $suffix);

    echo '' ?>
    <footer class="column justify-center align-center gap-8">
        <?php if (array_key_exists(CONFIG["instance"]["url"], CONFIG["instance"]["mirrors"])): ?>
            <p>You are using a mirror for <?= CONFIG["instance"]["mirrors"][CONFIG["instance"]["url"]] ?>. <a
                    href="<?= CONFIG["instance"]["url"] ?>">[ Check
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
        <?php html_debug_info(); ?>
    </footer>
    <?php ;
}

function html_mini_footer()
{
    echo '' ?>
    <footer class="mini">
        <?php html_debug_info(); ?>
        <div class="right">
            <p>
                All trademarks and copyrights belong to their respective owners.
                The uploader is responsible for any content shared here.
            </p>
        </div>
    </footer>
    <?php ;
}

function html_debug_info()
{
    $compile_time = (floor(microtime(true) * 1000) - GEN_TIMESTAMP) / 1000;

    $commit = [
        'timestamp' => (int) trim(shell_exec('git show -s --format=%ct HEAD')),
        'sha' => trim(shell_exec('git rev-parse HEAD'))
    ];

    echo '' ?>
    <div class="debug-info">
        <p>Page generated in <?= $compile_time ?>s</p>
        <?php if (!empty($commit['sha'])): ?>
            <p>
                Last updated
                <?= format_timestamp((new DateTime())->setTimestamp($commit['timestamp'])) ?> ago
                <a href="https://git.ilt.su/services/anonupload.git/commit/?id=<?= $commit['sha'] ?>">
                    (commit
                    <?= substr($commit['sha'], 0, 7) ?>)
                </a>
            </p>
        <?php endif; ?>
        <a href="/tos.php">Terms of Service</a>
        <a href="/privacy.php">Privacy Policy</a>
    </div>
    <?php ;
}

function html_file_brick(File $file, string|null $custom_url = null)
{
    if ($custom_url === null) {
        $custom_url = "/{$file->id}.{$file->extension}";
    }
    echo '' ?>
    <div class="brick<?= isset($file->color) ? " {$file->color}" : '' ?>">
        <a href="<?= $custom_url ?>">
            <i title="<?= $file->title ?>">
                <?php if (str_starts_with($file->mime, 'image/') || str_starts_with($file->mime, 'video/')): ?>
                    <img src="<?= sprintf('%s/%s.webp', CONFIG["thumbnails"]["url"], $file->id) ?>" alt="No thumbnail."
                        loading="lazy">
                <?php elseif (str_starts_with($file->mime, 'audio/')): ?>
                    <img src="/static/img/icons/file_audio.png" alt="No thumbnail." loading="lazy" class="thumbnail stock">
                <?php elseif (str_starts_with($file->mime, 'text/')): ?>
                    <img src="/static/img/icons/file_text.png" alt="No thumbnail." loading="lazy" class="thumbnail stock">
                <?php elseif ($file->mime == 'application/x-shockwave-flash'): ?>
                    <img src="/static/img/icons/file_flash.png" alt="No thumbnail." loading="lazy" class="thumbnail stock">
                <?php else: ?>
                    <img src="/static/img/icons/file.png" alt="No thumbnail." class="thumbnail stock">
                <?php endif; ?>
            </i>
        </a>
    </div>
    <?php ;
}

function html_file_full(File $file)
{
    $file_full_url = CONFIG["files"]["url"] . "/{$file->id}.{$file->extension}";
    if (str_starts_with($file->mime, 'image/')) {
        echo "<img src='$file_full_url' alt='Image file.'>";
    } elseif (str_starts_with($file->mime, 'video/')) {
        echo "<video controls autoplay loop id='video-playback'>";
        echo "<source src='$file_full_url' type='{$file->mime}'>";
        echo "</video>";
    } elseif (str_starts_with($file->mime, 'audio/')) {
        echo "<audio controls autoplay>";
        echo "<source src='$file_full_url' type='{$file->mime}'>";
        echo "</audio>";
    } elseif (CONFIG["files"]["displayhtml"] && $file->extension === "html" && file_exists(sprintf("%s/%s/index.html", CONFIG["files"]["directory"], $file->id))) {
        $src = sprintf("%s/%s/index.html", CONFIG["files"]["url"], $file->id);
        echo "<iframe src='$src' width='800' height='600' frameborder='0'></iframe>";
    } elseif (CONFIG["files"]["displayhtml"] && $file->extension === "html") {
        $src = sprintf("%s/%s.%s", CONFIG["files"]["url"], $file->id, $file->extension);
        echo "<iframe src='$src' width='800' height='600' frameborder='0'></iframe>";
    } elseif (str_starts_with($file->mime, 'text/')) {
        echo '<pre>';
        echo file_get_contents(CONFIG["files"]["directory"] . "/{$file->id}.{$file->extension}");
        echo '</pre>';
    } elseif ($file->mime == 'application/x-shockwave-flash' && !empty(CONFIG["driver"]["ruffle"])) {
        $width = $file->width - 4;
        echo '<noscript>JavaScript is required to play Flash</noscript>';
        echo '<object>';
        echo "<embed src='$file_full_url' width='$width' height='{$file->height}'>";
        echo '</object>';
    } else {
        echo '<p><i>This file cannot be displayed.</i></p>';
    }
}