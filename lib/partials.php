<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

function html_head(string|null $title = null, string|null $description = null, File|null $file = null)
{
    USER->authorize_with_cookie();

    $ititle = CONFIG['instance']['name'];
    if (!$description) {
        $description = "$ititle is a simple, free and anonymous file sharing site. We do not store anything other than the files you upload.";
    }

    $title = ($title ? "$title - " : '') . $ititle;
    echo "<title>$title</title>";
    echo "<meta property='og:title' content='$title' />";
    echo '<meta property="og:type" content="website" />';
    echo "<meta property='og:description' content='$description' />";
    echo "<meta property='description' content='$description' />";


    if (isset($file)) {
        echo '<meta property="og:url" content="' . sprintf("%s/%s.%s", CONFIG["instance"]["url"], $file->id, $file->extension) . '" />';
        if (CONFIG['thumbnails']['enable']) {
            echo '<meta property="og:image" content="' . sprintf("%s%s/%s.webp", CONFIG["instance"]["url"], CONFIG["thumbnails"]["url"], $file->id) . '" />';
        }
    }

    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<link rel="stylesheet" href="/static/style.css" />';
    echo '<link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon" />';
    echo '<meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />';
    echo '<meta name="theme-color" content="#ffe1d4" />';

    if ($_SERVER['PHP_SELF'] === '/index.php') {
        echo '<meta name="robots" content="nofollow" />';
    } else {
        echo '<meta name="robots" content="noindex, nofollow" />';
    }

    // themes
    if (isset($_COOKIE['theme'])) {
        if (in_array($_COOKIE['theme'], THEME_LIST, true)) {
            echo "<link rel='stylesheet' href='/static/themes/{$_COOKIE['theme']}/style.css' />";
        } else {
            setcookie("theme", "", 0, "/");
        }
    }
}

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
        <main>
            <div class="brand">
                <a href="/" id="brand-button">
                    <h1><img src="<?= $brand_url ?>" alt="<?= CONFIG["instance"]["name"] ?>"></h1>
                </a>
                <?php if (isset($line)): ?>
                    <p><i>&quot;<?= $line ?>&quot;</i></p>
                <?php endif; ?>
            </div>

            <div class="links">
                <a href="/" class="button">Home</a>
                <?php if (CONFIG["filecatalog"]["public"] || (isset($_SESSION['user']) && $_SESSION['user']->role->as_value() >= UserRole::Moderator->as_value())): ?>
                    <a href="/files/index.php" class="button" id="file-catalogue-button">Catalogue</a>
                <?php endif; ?>
                <?php if (CONFIG["supriseme"]["enable"]): ?>
                    <?php if (isset($_COOKIE['doomscrolling'])): ?>
                        <a href="/doomscrolling.php" class="button" id="doomscrolling-button">
                            Burn My Receptors
                        </a>
                    <?php else: ?>
                        <a href="/?random" id="surprise-me-button" class="button">
                            Surprise Me
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="/uploaders.php" class="button" id="uploaders-button">
                    Uploaders
                </a>
                <a href="/account/index.php" class="button" id="account-button">
                    Account
                </a>
            </div>

            <?php if (isset($_SESSION['user'])): ?>
                <div class="account-info">
                    <p>Signed in as <span class="username <?= $_SESSION['user']->role->name ?>">
                            <?= $_SESSION['user']->name ?>
                        </span></p>
                    <a href="/account/logout.php"><img src="/static/img/icons/logout.png" alt="[Log out]" title="Log out"></a>
                </div>
            <?php endif; ?>
        </main>
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
        <main>
            <div class="brand">
                <a href="/" class="row gap-8 align-bottom" style="text-decoration:none;color:inherit;">
                    <img src="<?= $brand_url ?>" alt="">
                    <div class="column">
                        <?php if ($subtitle): ?>
                            <p class="font-small"><?= $subtitle ?></p>
                        <?php endif; ?>
                        <h2><?= $title ?></h2>
                    </div>
                </a>
            </div>

            <div class="links">
                <a href="/" class="button" id="home-button">
                    Home
                </a>
                <?php if (CONFIG["filecatalog"]["public"] || (isset($_SESSION['user']) && $_SESSION['user']->role->as_value() >= UserRole::Moderator->as_value())): ?>
                    <a href="/files/index.php" class="button" id="file-catalogue-button">
                        Catalogue
                    </a>
                <?php endif; ?>
                <?php if (CONFIG["supriseme"]["enable"]): ?>
                    <?php if (isset($_COOKIE['doomscrolling'])): ?>
                        <a href="/doomscrolling.php" class="button" id="doomscrolling-button">
                            Burn My Receptors
                        </a>
                    <?php else: ?>
                        <a href="/?random" class="button" id="surprise-me-button">
                            Surprise Me
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="/uploaders.php" class="button" id="uploaders-button">
                    Uploaders
                </a>
                <a href="/account/" class="button" id="account-button">
                    Account
                </a>
            </div>

            <?php if (isset($_SESSION['user'])): ?>
                <div class="account-info">
                    <p>Signed in as <span
                            class="username <?= $_SESSION['user']->role->name ?>"><?= $_SESSION['user']->name ?></span></p>
                    <a href="/account/logout.php"><img src="/static/img/icons/logout.png" alt="[Log out]" title="Log out"></a>
                </div>
            <?php endif; ?>
        </main>
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
    <footer>
        <main>
            <?php if (array_key_exists(CONFIG["instance"]["url"], CONFIG["instance"]["mirrors"])): ?>
                <p>You are using a mirror for
                    <?= CONFIG["instance"]["mirrors"][CONFIG["instance"]["url"]] ?>. <a
                        href="<?= CONFIG["instance"]["url"] ?>">[ Check
                        out the origin website ]</a>
                </p>
            <?php elseif (!empty(CONFIG["instance"]["mirrors"])): ?>
                <div class="row gap-8">
                    <p>Mirrors:</p>
                    <ul class="no-style row gap-4" style="list-style: none;">
                        <?php foreach (CONFIG["instance"]["mirrors"] as $url => $name): ?>
                            <li><a href="<?= $url ?>">
                                    <?= $name ?>
                                </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="row gap-8">
                <?php foreach (CONFIG["instance"]["footerlinks"] as $title => $link): ?>
                    <p><a href="<?= $link ?>">
                            <?= $title ?>
                        </a></p>
                <?php endforeach; ?>
            </div>
            <p>
                Serving
                <?= $file_count ?> files and
                <?= $file_size ?> of active content
                <?php if (CONFIG["stats"]["enable"]): ?>
                    <a href="/stats.php">
                        <img src="/static/img/icons/stats.png" alt="[Stats]">
                    </a>
                <?php endif; ?>
            </p>
            <?php html_debug_info(); ?>
        </main>
    </footer>
    <?php ;
}

function html_mini_footer()
{
    echo '' ?>
    <footer class="mini">
        <main>
            <?php html_debug_info(); ?>
            <p>
                All trademarks and copyrights belong to their respective owners.
                The uploader is responsible for any content shared here.
            </p>
        </main>
    </footer>
    <?php ;
}

function html_debug_info()
{
    $compile_time = (floor(microtime(true) * 1000) - GEN_TIMESTAMP) / 1000;

    $commit = null;

    if ($_SERVER['REQUEST_URI'] === '/') {
        $commit = [
            'timestamp' => (int) trim(shell_exec('git show -s --format=%ct HEAD')),
            'sha' => trim(shell_exec('git rev-parse HEAD'))
        ];
    }

    $new_tos = false;
    $tos_exists = false;
    if (file_exists("{$_SERVER['DOCUMENT_ROOT']}/TOS.txt")) {
        $new_tos = time() - filemtime("{$_SERVER['DOCUMENT_ROOT']}/TOS.txt") < 86400 * 3;
        $tos_exists = true;
    }

    $new_privacy = false;
    $privacy_exists = false;
    if (file_exists("{$_SERVER['DOCUMENT_ROOT']}/PRIVACY.txt")) {
        $new_privacy = time() - filemtime("{$_SERVER['DOCUMENT_ROOT']}/PRIVACY.txt") < 86400 * 3;
        $privacy_exists = true;
    }

    echo '' ?>
    <div class="debug-info">
        <p>Page generated in <?= $compile_time ?>s</p>
        <?php if (isset($commit) && !empty($commit['sha'])): ?>
            <p>
                Last updated
                <?= format_timestamp((new DateTime())->setTimestamp($commit['timestamp'])) ?> ago
                <a href="https://git.ilt.su/services/anonupload.git/commit/?id=<?= $commit['sha'] ?>">
                    (commit
                    <?= substr($commit['sha'], 0, 7) ?>)
                </a>
            </p>
        <?php endif; ?>
        <?php if ($tos_exists): ?>
            <a href="/tos.php">Terms of Service<?= $new_tos ? " (NEW)" : "" ?></a>
        <?php endif; ?>
        <?php if ($privacy_exists): ?>
            <a href="/privacy.php">Privacy Policy<?= $new_privacy ? " (NEW)" : "" ?></a>
        <?php endif; ?>
        <p><a href="/preferences.php"><img src="/static/img/icons/preferences.png" alt="Preferences"
                    title="Customize your experience" height="12"></a></p>
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
            <i title="<?= "{$file->id}.{$file->extension} // {$file->mime} ({$file->extension})" ?>">
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
    $loop = isset($_COOKIE['noloop']) ? '' : 'loop';
    $autoplay = isset($_COOKIE['noautoplay']) ? '' : 'autoplay';

    $file_full_url = CONFIG["files"]["url"] . "/{$file->id}.{$file->extension}";
    if (str_starts_with($file->mime, 'image/')) {
        echo "<img src='$file_full_url' alt='Image file.'>";
    } elseif (str_starts_with($file->mime, 'video/')) {
        echo "<video controls $autoplay $loop id='video-playback'>";
        echo "<source src='$file_full_url' type='{$file->mime}'>";
        echo "</video>";
    } elseif (str_starts_with($file->mime, 'audio/')) {
        echo "<audio controls $autoplay>";
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