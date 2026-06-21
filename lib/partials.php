<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";

function get_big_icon(): string
{
    $brand_url = '/static/img/brand/big.webp';
    $folder_path = '/static/instances/' . CONFIG['instance']['name'] . '/img/brand/big';

    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $folder_path)) {
        $folder_path = '/static/img/brand/big';
    }

    if (is_dir($_SERVER['DOCUMENT_ROOT'] . $folder_path)) {
        $files = glob($_SERVER['DOCUMENT_ROOT'] . "$folder_path/*.*");

        if (!empty($files)) {
            $file = basename($files[random_int(0, count($files) - 1)]);
            $brand_url = "$folder_path/$file";
        }
    }

    return CONFIG['instance']['url'] . $brand_url;
}

function html_head(string $title = "", string|null $description = null, Post|null $post = null)
{
    if (!$description) {
        $description = CONFIG['instance']['name'] . " is a simple, free and anonymous file sharing site. We do not store anything other than the files you upload.";
    }

    if ($title) {
        $title .= ' - ';
    }

    $title .= CONFIG['instance']['name'] . ' - file sharing website';

    echo "<title>$title</title>";
    echo "<meta property=\"og:title\" content=\"$title\" />";
    echo '<meta property="og:type" content="website" />';
    echo "<meta property=\"og:description\" content=\"$description\" />";
    echo "<meta property=\"description\" content=\"$description\" />";

    $image_url = null;

    if (isset($post)) {
        echo "<meta property=\"og:url\" content=\"{$post->url()}\" />";
        $image_url = $image_url = $post->thumbnail_url();
    } else {
        $image_url = get_big_icon();
    }

    if ($image_url) {
        echo "<meta property=\"og:image\" content=\"$image_url\" />";
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
    $theme = $_COOKIE['theme'] ?? CONFIG['instance']['defaultstyle'];

    if (isset($theme) && $theme !== 'default') {
        if (in_array($theme, THEME_LIST, true)) {
            echo "<link rel='stylesheet' href='/static/themes/$theme/style.css' />";
        } else {
            setcookie("theme", "", 0, "/");
        }
    }
}

function html_mini_icon()
{
    $brand_url = '/static/img/brand/mini.webp';
    $folder_path = '/static/instances/' . CONFIG['instance']['name'] . '/img/brand/mini';

    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $folder_path)) {
        $folder_path = '/static/img/brand/mini';
    }

    if (is_dir($_SERVER['DOCUMENT_ROOT'] . $folder_path)) {
        $files = glob($_SERVER['DOCUMENT_ROOT'] . "$folder_path/*.*");

        if (!empty($files)) {
            $file = basename($files[random_int(0, count($files) - 1)]);
            $brand_url = "$folder_path/$file";
        }
    }

    echo "<a href='/'><img src='$brand_url' alt='' height='32'></a>";
}

function html_header()
{
    $brand_url = get_big_icon();

    echo '<a href="/">';
    echo "<img src='$brand_url' alt='" . CONFIG['instance']['name'] . "'/>";
    echo '</a>';
}

function html_footer()
{
    $dropdown_links = CONFIG['instance']['links'];
    $mirrors = CONFIG['instance']['mirrors'];

    ?>
    <ul class="horizontal links">
        <?php if (empty($mirrors)): ?>
            <li>
                <a href="/"><?= CONFIG['instance']['name'] ?></a>
            </li>
        <?php else: ?>
            <li class="dropdown">
                <button class="drop-button"><?= CONFIG['instance']['name'] ?></button>
                <div class="drop-content">
                    <?php foreach ($mirrors as $url => $name): ?>
                        <a href="<?= $url ?>" target="_blank"><?= $name ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endif; ?>
        <?php if (CONFIG['surpriseme']['enable']): ?>
            <li><a href="/?random">surprise me</a></li>
        <?php endif; ?>
        <li><a href="/history">history</a></li>
        <li><a href="/favorites">favorites</a></li>
        <li><a href="/sync">sync</a></li>
        <li><a href="/uploaders">uploaders</a></li>
        <?php if (CONFIG['stats']['enabled']): ?>
            <li><a href="/stats">statistics</a></li>
        <?php endif; ?>
        <li><a href="/preferences">preferences</a></li>
        <?php if (!empty(CONFIG['contact']['url'])): ?>
            <li><a href="<?= CONFIG['contact']['url'] ?>" referrerpolicy="origin"><?= CONFIG['contact']['name'] ?></a></li>
        <?php endif; ?>
        <?php if (!empty($dropdown_links)): ?>
            <li class="dropdown">
                <button class="drop-button"><?= CONFIG['instance']['linkname'] ?></button>
                <div class="drop-content">
                    <?php foreach ($dropdown_links as $url => $name): ?>
                        <a href="<?= $url ?>" target="_blank"><?= $name ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endif; ?>
    </ul>
    <?php ;
}

function html_legal()
{
    echo '' ?>
    <ul class="horizontal links">
        <?php if (file_exists("{$_SERVER['DOCUMENT_ROOT']}/QA.txt")): ?>
            <li><a href="/qa">q&a</a></li>
        <?php endif; ?>
        <?php if (file_exists("{$_SERVER['DOCUMENT_ROOT']}/TOS.txt")): ?>
            <li><a href="/tos">terms of service</a></li>
        <?php endif; ?>
        <?php if (file_exists("{$_SERVER['DOCUMENT_ROOT']}/PRIVACY.txt")): ?>
            <li><a href="/privacy">privacy policy</a></li>
        <?php endif; ?>
    </ul>
    <?php ;
}

function html_motd()
{
    $path = "{$_SERVER['DOCUMENT_ROOT']}/MOTD.txt";
    $motd = null;

    if (is_file($path)) {
        $lines = explode(PHP_EOL, trim(file_get_contents($path)));
        $count = count($lines);
        if ($count > 0) {
            $motd = $lines[intval(date('j')) % $count];
        }
    }

    if ($motd) {
        echo "<p class='motd'>$motd</p>";
    }
}

function html_file_brick(Post $post)
{
    $name = $post->name();
    $mime = $post->mime();

    echo '<div class="file item brick" style="background-image: url(\'';
    echo match (true) {
        $mime === "application/x-multi-upload" => "/static/img/icons/file_multi.png",
        $mime === "application/x-shockwave-flash" => "/static/img/icons/file_flash.png",
        str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/') => $post->thumbnail_url(),
        str_starts_with($mime, 'audio/') => "/static/img/icons/file_audio.png",
        str_starts_with($mime, 'text/') => "/static/img/icons/file_text.png",
        default => "/static/img/icons/file.png"
    };
    echo '\')"><div class="base">';

    echo "<p class='name'>$name</p>";
    echo '<ul class="details">';
    if ($s = $post->single_attachment()) {
        echo '<li>' . format_filesize($s->size) . '</li>';
    } else {
        echo '<li>multiple attachments</li>';
    }
    echo '</ul>';

    echo '<div class="buttons">';
    echo "<a href='{$post->url()}' target='_blank' class='button'>open</a>";
    echo '</div>';

    echo '</div></div>';
}

function html_file_full(BaseFile $file)
{
    $loop = isset($_COOKIE['noloop']) ? '' : 'loop';
    $autoplay = isset($_COOKIE['noautoplay']) ? '' : 'autoplay';

    echo '<div class="file-preview" ';
    echo "file-name='{$file->name}' ";
    echo "file-pathname='{$file->file_name()}' ";
    echo "file-size='{$file->size}' ";
    echo "file-mime='{$file->mime}' ";
    echo "file-ext='{$file->extension}' ";
    echo "file-raw-url='{$file->raw_url()}' ";
    echo "file-thumb-url='{$file->thumbnail_url()}' ";
    echo '>';

    // -- contents
    echo '<div class="file-contents">';

    if (str_starts_with($file->mime, 'image/')) {
        echo "<img src='{$file->raw_url()}' alt='Image file.'>";
    } elseif (str_starts_with($file->mime, 'video/')) {
        echo "<video controls $autoplay $loop class='video-playback' file-name='{$file->name}'>";
        echo "<source src='{$file->raw_url()}' type='{$file->mime}'>";
        echo "</video>";
    } elseif (str_starts_with($file->mime, 'audio/')) {
        echo "<audio controls $autoplay>";
        echo "<source src='{$file->raw_url()}' type='{$file->mime}'>";
        echo "</audio>";
    } else if ($file->is_flash()) {
        echo '<noscript>JavaScript is required to play Flash</noscript>';
        echo '<object>';
        // TODO: change flash resolution
        echo "<embed src='{$file->raw_url()}' width='800' height='600'>";
        echo '</object>';
    } else {
        echo '<div class="unsupported-playback box">';
        echo '<p><i>This file cannot be displayed.</i></p>';
        echo '</div>';
    }

    echo '</div>';

    // -- metadata
    echo '<div class="file-metadata">';
    echo '<p class="file-size">' . format_filesize($file->size) . '</p>';
    echo "<p class='file-mime'>{$file->mime} (<span class='file-extension'>{$file->extension}</span>)</p>";

    if ($m = $file->metadata) {
        if ($m->width && $m->height) {
            echo "<p class='file-resolution'>{$m->width}x{$m->height}</p>";
        }
        if ($m->duration) {
            echo "<p class='file-duration'>";
            echo format_timestamp((new DateTime())->setTimestamp(time() + $m->duration));
            echo "</p>";
        }
        if ($m->line_count) {
            echo "<p class='file-line-count'>{$m->line_count} lines</p>";
        }
    }

    echo '</div>';

    echo '</div>';
}