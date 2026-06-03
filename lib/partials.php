<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";

function html_head(string|null $title = null, string|null $description = null, BaseFile|null $file = null)
{
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

function html_mini_icon()
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

    echo "<a href='/'><img src='$brand_url' alt=''></a>";
}

function html_header()
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

    ?>
    <a href="/">
        <img src="<?= $brand_url ?>" alt="<?= CONFIG['instance']['name'] ?>" />
    </a>
    <?php ;
}

function html_footer()
{
    ?>
    <ul class="horizontal links">
        <li><a href="/"><?= CONFIG['instance']['name'] ?></a></li>
        <?php if (CONFIG['surpriseme']['enable']): ?>
            <li><a href="/?random">surprise me</a></li>
        <?php endif; ?>
        <li><a href="/history">history</a></li>
        <li><a href="/favorites">favorites</a></li>
        <li><a href="/uploaders">uploaders</a></li>
        <li><a href="/preferences">preferences</a></li>
    </ul>
    <?php ;
}

function html_debug_info()
{
    $compile_time = (floor(microtime(true) * 1000) - GEN_TIMESTAMP) / 1000;

    $commit = null;

    if ($_SERVER['REQUEST_URI'] === '/') {
        $commit = explode(' ', trim(shell_exec('git show -s --format="%h %ct %s" HEAD')), 3);
        $commit = [
            'sha' => $commit[0],
            'timestamp' => (int) $commit[1],
            'message' => $commit[2]
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
                <a href="https://git.ilt.su/services/anonupload.git/commit/?id=<?= $commit['sha'] ?>"
                    title="<?= $commit['message'] ?>">
                    (commit <?= $commit['sha'] ?>)
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

function html_file_full(BaseFile $file)
{
    $loop = isset($_COOKIE['noloop']) ? '' : 'loop';
    $autoplay = isset($_COOKIE['noautoplay']) ? '' : 'autoplay';

    if (str_starts_with($file->mime, 'image/')) {
        echo "<img src='{$file->url}' alt='Image file.'>";
    } elseif (str_starts_with($file->mime, 'video/')) {
        echo "<video controls $autoplay $loop class='video-playback' file-id='{$file->id}'>";
        echo "<source src='{$file->url}' type='{$file->mime}'>";
        echo "</video>";

        echo '' ?>
        <div class="scan-bg unsupported-playback" file-id="<?= $file->id ?>" style="display:none">
            <p>This file uses the <?= $file->mime ?> (<?= $file->extension ?>) format which your browser cannot play.</p>
            <p>
                You can <a href="<?= $file->url ?>" download="<?= "{$file->id}.{$file->extension}" ?>">download the file</a>
                and watch it in a media player
                or <b>use a different browser</b> that supports this codec.
            </p>
        </div>

        <script>
            const video = document.querySelector(".video-playback[file-id=\"<?= $file->id ?>\"]");
            const msg = document.querySelector(".unsupported-playback[file-id=\"<?= $file->id ?>\"]");
            const mime = document.querySelector("*[file-id=\"<?= $file->id ?>\"]>.file-mime");

            if (mime && video && msg && !video.canPlayType(mime.textContent)) {
                video.style.display = 'none';
                msg.style.display = 'flex';
            }
        </script>
        <?php ;
    } elseif (str_starts_with($file->mime, 'audio/')) {
        echo "<audio controls $autoplay>";
        echo "<source src='{$file->url}' type='{$file->mime}'>";
        echo "</audio>";
    } else {
        echo '<p><i>This file cannot be displayed.</i></p>';
    }
}