<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/file.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

session_start();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if (FILE_CATALOG_RANDOM && isset($_GET['random'])) {
    $random_viewed_files = $_SESSION['random_viewed_files'] ?? [];

    $mime_filter = "";
    if (!empty(FILE_CATALOG_INCLUDE_MIMETYPES)) {
        $mime_filter = [];
        foreach (FILE_CATALOG_INCLUDE_MIMETYPES as $k) {
            array_push($mime_filter, "mime LIKE '$k'");
        }
        $mime_filter = '(' . implode(' OR ', $mime_filter) . ')';
    }

    $in = !empty($random_viewed_files) ? (str_repeat('?,', count($random_viewed_files) - 1) . '?') : '';
    $in_condition = !empty($random_viewed_files) ? ("id NOT IN ($in) " . ($mime_filter ? " AND " : "")) : "";
    $where_word = $in_condition || $mime_filter ? "WHERE" : "";
    $order_condition = FILE_CATALOG_RANDOM_ORDER ?: "rand()";

    do {
        $stmt = $db->prepare("SELECT id, extension FROM files $where_word $in_condition $mime_filter ORDER BY $order_condition LIMIT 1");
        if (empty($random_viewed_files)) {
            $stmt->execute();
        } else {
            $stmt->execute($random_viewed_files);
        }

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $file_id = $row['id'];
            $file_path = "{$row['id']}.{$row['extension']}";
        } else {
            $random_viewed_files = array_diff($random_viewed_files, $random_viewed_files);
            $in_condition = '';
        }
    } while (!$file_id || in_array($file_id, $random_viewed_files));

    array_push($random_viewed_files, $file_id);
    $_SESSION['random_viewed_files'] = $random_viewed_files;

    header("Location: /$file_path");
    exit;
}

$file = null;
$file_id = null;
$url = parse_url($_SERVER['REQUEST_URI']);

if (strlen($url['path']) > 1) {
    $file_id = basename($url['path']);
}

if (FILE_CATALOG_FANCY_VIEW && $file_id) {
    $file_id = explode('.', $file_id);
    if (count($file_id) != 2) {
        http_response_code(404);
        exit();
    }
    $file_ext = $file_id[1];
    $file_id = $file_id[0];

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $file_id) || !preg_match('/^[a-zA-Z0-9]+$/', $file_ext)) {
        http_response_code(404);
        exit();
    }

    $stmt = $db->prepare('SELECT fm.*, f.*,
        hb.reason AS ban_reason,
        CASE WHEN fb.hash_ban IS NOT NULL THEN 1 ELSE 0 END AS is_banned
        FROM files f
        LEFT JOIN file_metadata fm ON fm.id = f.id
        LEFT JOIN file_bans fb ON fb.id = f.id
        LEFT JOIN hash_bans hb ON hb.sha256 = fb.hash_ban
        WHERE f.id = ? AND f.extension = ?
    ');
    $stmt->execute([$file_id, $file_ext]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$file) {
        http_response_code(404);
        exit();
    }

    // counting views
    $viewed_file_ids = $_SESSION['viewed_file_ids'] ?? [];
    if (!in_array($file['id'], $viewed_file_ids)) {
        $file['views']++;
        array_push($viewed_file_ids, $file['id']);
        $db->prepare('UPDATE files SET views = ? WHERE id = ? AND extension = ?')->execute([$file['views'], $file['id'], $file['extension']]);
    }
    $_SESSION['viewed_file_ids'] = $viewed_file_ids;

    if (
        isset($file['expires_at']) &&
        (
            ($file['expires_at'] == $file['uploaded_at'] && $file['views'] > 1) ||
            ($file['expires_at'] != $file['uploaded_at'] && time() > strtotime($file['expires_at']))
        )
    ) {
        delete_file($file_id, $file_ext, $db);
        http_response_code(404);
        exit;
    }

    if (!FILE_SHOW_UPLOADTIME && !isset($_SESSION['is_moderator'])) {
        unset($file['uploaded_at']);
    }

    if (!FILE_SHOW_VIEWS && !isset($_SESSION['is_moderator'])) {
        unset($file['views']);
    }

    if (IS_JSON_REQUEST) {
        unset($file['password']);
        $file['urls'] = [
            'download_url' => INSTANCE_ORIGINAL_URL . "/{$file['id']}.{$file['extension']}"
        ];
        json_response($file, null);
        exit;
    }

    $file['full_url'] = FILE_UPLOAD_DIRECTORY_PREFIX . "/{$file['id']}.{$file['extension']}";

    // formatting the file size
    $size = $file['size'];
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($size) - 1) / 3);
    $file['size_formatted'] = sprintf("%.2f", $size / pow(1024, $factor)) . ' ' . $units[$factor];

    $file['name'] = $file['title'] ?? sprintf('%s.%s', $file['id'], $file['extension']);
    $file['download_name'] = $file['name'];
    if (!str_ends_with($file['download_name'], ".{$file['extension']}")) {
        $file['download_name'] .= ".{$file['extension']}";
    }

    $file['resolution'] = [];

    if (isset($file['width'], $file['height'])) {
        array_push($file['resolution'], sprintf('%sx%s', $file['width'], $file['height']));
    }

    if (isset($file['duration'])) {
        $dur = format_timestamp(new DateTime()->setTimestamp(time() + $file['duration']));
        array_push($file['resolution'], empty($file['resolution']) ? $dur : "($dur)");
    }

    if (isset($file['line_count'])) {
        array_push($file['resolution'], sprintf('%d lines', $file['line_count']));
    }

    $file['resolution'] = implode(' ', $file['resolution']) ?: null;

    $file['html_description'] = $file['mime'] . ' - ' . $file['extension'];
    if (isset($file['views'])) {
        $file['html_description'] .= " - {$file['views']} views";
    }
    if (isset($file['uploaded_at'])) {
        $file['html_description'] .= ' - Uploaded ' . format_timestamp($file['uploaded_at']) . ' ago';
    }
    if (isset($file['resolution'])) {
        $file['html_description'] .= " - {$file['resolution']}";
    }
}

$tos_exists = is_file($_SERVER['DOCUMENT_ROOT'] . '/static/TOS.txt');
$privacy_exists = is_file($_SERVER['DOCUMENT_ROOT'] . '/static/PRIVACY.txt');
?>
<html>

<head>
    <?php if ($file): ?>
        <title><?= $file['name'] ?> - <?= INSTANCE_NAME ?></title>
        <meta property="og:title" content="<?= $file['name'] ?> - <?= INSTANCE_NAME ?>" />
        <meta property="og:description" content="<?= $file['html_description'] ?>" />
        <meta property="og:url" content="<?= sprintf("%s/%s.%s", INSTANCE_URL, $file['id'], $file['extension']) ?>" />
        <meta property="og:type" content="website" />
        <?php if (FILE_THUMBNAILS): ?>
            <meta property="og:image"
                content="<?= sprintf('%s%s/%s.webp', INSTANCE_URL, FILE_THUMBNAIL_DIRECTORY_PREFIX, $file['id']) ?>" />
        <?php endif; ?>
        <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
        <title><?= INSTANCE_NAME ?></title>
        <meta name="description"
            content="<?= INSTANCE_NAME ?> is a simple, free and anonymous file sharing site. We do not store anything other than the files you upload.">
        <meta name="robots" content="nofollow">
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="theme-color" content="#ffe1d4">
</head>

<body>
    <main<?= $file ? ' class="full-size"' : '' ?>>
        <noscript>
            <p><b>No-JavaScript chad <img src="/static/img/icons/chad.png" alt="" width="16"></b></p>
            <p style="color:gray">no fancy features like local file saving</p>
        </noscript>

        <?php if ($file): ?>
            <?php html_mini_navbar() ?>

            <?php display_alert() ?>

            <?php if ($file['is_banned']): ?>
                <section class="box red">
                    <p>Sorry&comma; you cannot access this file as it violated the TOS and was banned from the
                        <?= INSTANCE_NAME ?> servers.
                    </p>
                    <?php if (isset($file['ban_reason'])): ?>
                        <p>Reason: <b><?= $file['ban_reason'] ?></b></p>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <div class="row grow justify-center">
                    <section class="file-preview-wrapper" <?= isset($file['width']) ? ('style="max-width:' . max($file['width'], 256) . 'px;"') : '' ?>>
                        <section class="box">
                            <div class="tab row wrap gap-8">
                                <div class="grow">
                                    <div style="display: none;">
                                        <p id="file-id"><?= $file['id'] ?></p>
                                        <p id="file-mime"><?= $file['mime'] ?></p>
                                        <p id="file-extension"><?= $file['extension'] ?></p>
                                        <p id="file-size"><?= $file['size'] ?></p>
                                    </div>
                                    <?php if (isset($file['title'])): ?>
                                        <p><i><?= $file['title'] ?></i></p>
                                    <?php else: ?>
                                        <p>File <?= sprintf('%s.%s', $file['id'], $file['extension']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="grow row gap-8 justify-end align-center wrap" id="file-tab-buttons">
                                    <?php if (isset($_SESSION['is_moderator'])): ?>
                                        <a href="/delete.php?f=<?= $file['id'] ?>.<?= $file['extension'] ?>">
                                            <button>Delete</button>
                                        </a>
                                        <?php if (MOD_BAN_FILES): ?>
                                            <form action="/ban.php" method="post" class="row gap-4">
                                                <input type="text" name="f" value="<?= $file['id'] ?>.<?= $file['extension'] ?>"
                                                    style="display:none">
                                                <input type="text" name="reason" placeholder="Ban reason">
                                                <button type="submit">Ban</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (FILE_REPORT): ?>
                                        <a href="/report.php?f=<?= $file['id'] ?>.<?= $file['extension'] ?>">
                                            <button>Report</button>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= $file['full_url'] ?>">
                                        <button>Full size</button>
                                    </a>
                                    <a href="<?= $file['full_url'] ?>" download="<?= $file['download_name'] ?>">
                                        <button>Download</button>
                                    </a>
                                </div>
                            </div>
                            <div class="content column file-preview">
                                <?php if (str_starts_with($file['mime'], 'image/')): ?>
                                    <img src="<?= $file['full_url'] ?>" alt="Image file.">
                                <?php elseif (str_starts_with($file['mime'], 'video/')): ?>
                                    <video controls autoplay loop>
                                        <source src="<?= $file['full_url'] ?>" type="<?= $file['mime'] ?>">
                                    </video>
                                <?php elseif (str_starts_with($file['mime'], 'audio/')): ?>
                                    <audio controls autoplay>
                                        <source src="<?= $file['full_url'] ?>" type="<?= $file['mime'] ?>">
                                    </audio>
                                <?php elseif (HTML_IFRAME_ENABLE && $file["extension"] === "html" && file_exists(sprintf("%s/%s/index.html", FILE_UPLOAD_DIRECTORY, $file["id"]))): ?>
                                    <iframe src="<?= sprintf("%s/%s/index.html", FILE_UPLOAD_DIRECTORY_PREFIX, $file["id"]) ?>"
                                        width="800" height="600" frameborder="0"></iframe>
                                <?php elseif (HTML_IFRAME_ENABLE && $file["extension"] === "html"): ?>
                                    <iframe
                                        src="<?= sprintf("%s/%s.%s", FILE_UPLOAD_DIRECTORY_PREFIX, $file["id"], $file["extension"]) ?>"
                                        width="800" height="600" frameborder="0"></iframe>
                                <?php elseif (str_starts_with($file['mime'], 'text/')): ?>
                                    <pre><?= file_get_contents(FILE_UPLOAD_DIRECTORY . "/{$file['id']}.{$file['extension']}") ?></pre>
                                <?php elseif ($file['mime'] == 'application/x-shockwave-flash' && !empty(RUFFLE_DRIVER_PATH)): ?>
                                    <noscript>JavaScript is required to play Flash</noscript>
                                    <object>
                                        <embed src="<?= $file['full_url'] ?>" width="<?= $file['width'] - 4 ?>"
                                            height="<?= $file['height'] ?>">
                                    </object>
                                <?php else: ?>
                                    <p><i>This file cannot be displayed.</i></p>
                                <?php endif; ?>
                            </div>

                        </section>

                        <div class="font-small row right wrap justify-end gap-8 align-bottom">
                            <p title="<?= $file['size'] ?>B"><?= $file['size_formatted'] ?></p>
                            <p><?= $file['mime'] ?> &#40;<?= $file['extension'] ?>&#41;</p>
                            <?php if (isset($file['resolution'])): ?>
                                <p><?= $file['resolution'] ?></p>
                            <?php endif; ?>
                            <?php if (isset($file['uploaded_at'])): ?>
                                <p title="<?= $file['uploaded_at'] ?>">Uploaded <?= format_timestamp($file['uploaded_at']) ?> ago
                                </p>
                            <?php endif; ?>
                            <?php if ((FILE_SHOW_VIEWS || isset($_SESSION['is_moderator'])) && isset($file['views'])): ?>
                                <p><?= $file['views'] ?> views</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
            <?php html_mini_footer(); ?>
        <?php else: ?>
            <?php html_big_navbar() ?>

            <?php display_alert() ?>

            <section class="box">
                <div class="tab">
                    <p>What is <?= INSTANCE_NAME ?>?</p>
                </div>
                <div class="content">
                    <p>
                        <?= INSTANCE_NAME ?> is a simple, free and anonymous file sharing site.
                        We do not store anything other than the files you upload.
                        They are stored <b>publicly</b> until the heat death of the universe occurs or you hit the DELETE
                        button.
                        Users do not need an account to start uploading.
                        <br><br>
                        Click the button below and share the files with your friends today!
                        <?php if ($tos_exists || $privacy_exists): ?>
                            <br>
                            But, read
                            <?php if ($tos_exists): ?>
                                <a href="/static/TOS.txt">TOS</a>
                            <?php endif; ?>
                            <?php if ($tos_exists && $privacy_exists): ?> and <?php endif; ?>
                            <?php if ($privacy_exists): ?>
                                <a href="/static/PRIVACY.txt">Privacy Policy</a>
                            <?php endif; ?>
                            before
                            interacting with the
                            website.
                        <?php endif; ?>
                    </p>
                </div>
            </section>

            <section class="box column" id="form-box">
                <div class="tab">
                    <p>Form Upload</p>
                </div>
                <div class="tab-category tabs" id="form-upload-tabs" style="display: none;">
                    <div class="form-upload-tab tab" id="form-tab-file">
                        <button onclick="showUploadType('file')" class="transparent">
                            <p>File Upload</p>
                        </button>
                    </div>
                    <div class="form-upload-tab tab disabled" id="form-tab-text">
                        <button onclick="showUploadType('text')" class="transparent">
                            <p>Text</p>
                        </button>
                    </div>
                </div>
                <div class="content">
                    <form class="column gap-8" action="/upload.php" method="post" enctype="multipart/form-data"
                        id="form-upload">
                        <p class="remove-script">File:</p>
                        <hr class="remove-script">
                        <input type="file" name="file"
                            accept="<?= implode(', ', array_unique(array_values(FILE_ACCEPTED_MIME_TYPES))) ?>" multiple
                            id="form-file">

                        <div class="column gap-8" id="form-upload-wrapper">
                            <button type="button" style="display: none;">
                                <h1>Click, drop, or paste files here</h1>
                            </button>
                            <?php if (FILEEXT_ENABLED): ?>
                                <div class="row gap-8">
                                    <p>URL:</p>
                                    <div class="column grow">
                                        <input type="url" name="url" id="form-url"
                                            placeholder="Instagram, YouTube and other links">
                                        <ul class="row gap-8 font-small" style="list-style:none">
                                            <li>
                                                <p>Max duration: <b><?= FILEEXT_MAX_DURATION / 60 ?> minutes</b></p>
                                            </li>
                                            <li><a href="https://github.com/yt-dlp/yt-dlp/blob/master/supportedsites.md"
                                                    target="_blank">Supported
                                                    platforms</a></li>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <ul class="row gap-8 font-small" style="list-style:none">
                                <li>
                                    <p class="font-small">Max file size:
                                        <b><?= get_cfg_var(option: 'upload_max_filesize') ?></b>
                                    </p>
                                </li>
                                <li><a href="/uploaders.php#supported-file-extensions" target="_blank">Supported file
                                        extensions</a></li>
                            </ul>
                        </div>

                        <div class="column" id="form-text-upload">
                            <p class="remove-script">Text:</p>
                            <hr class="remove-script">
                            <textarea name="paste" placeholder="Enter your text here..."></textarea>
                        </div>

                        <div class="column" id="form-record-upload" style="display: none;"></div>

                        <div class="column">
                            <p class="remove-script">Details:</p>
                            <hr class="remove-script">
                            <table class="vertical left" id="form-upload-options">
                                <?php if (FILE_CUSTOM_ID): ?>
                                    <tr>
                                        <th>File ID:</th>
                                        <td><input type="text" name="id" placeholder="Leave empty for a random ID"
                                                maxlength="<?= FILE_CUSTOM_ID_LENGTH ?>">
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Title:</th>
                                    <td>
                                        <input type="text" name="title" placeholder="Leave empty if you want a random title"
                                            maxlength="<?= FILE_TITLE_MAX_LENGTH ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Password<span class="help" title="For file deletion">[?]</span>:</th>
                                    <td><input type="text" name="password"
                                            placeholder="Leave empty if you want the file to be non-deletable"
                                            value="<?= generate_random_char_sequence(FILE_ID_CHARACTERS, FILE_DELETION_KEY_LENGTH) ?>">
                                    </td>
                                </tr>
                                <?php if (!empty(FILE_EXPIRATION)): ?>
                                    <tr>
                                        <th>File expiration:</th>
                                        <td>
                                            <select name="expires_in">
                                                <?php foreach (FILE_EXPIRATION as $v => $n): ?>
                                                    <option value="<?= $v ?>"><?= $n ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Preserve original filename:</th>
                                    <td><input type="checkbox" name="preserve_original_name" value="1"></td>
                                </tr>
                                <?php if (FILE_STRIP_EXIF): ?>
                                    <tr>
                                        <th>Strip EXIF data:</th>
                                        <td><input type="checkbox" name="strip_exif_data" value="1" checked></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (FILE_REMOVE_LETTERBOXES): ?>
                                    <tr>
                                        <th>Remove letterboxing<span class="help"
                                                title="Removes black bars from the video, may be inaccurate, and only applies to videos">[?]</span>:
                                        </th>
                                        <td><input type="checkbox" name="remove_letterbox" value="1"></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                            <button type="submit" class="fancy">Upload</button>
                    </form>
                </div>
                </div>
            </section>

            <section class="box column" style="display:none">
                <div class="tab-category tabs" id="file-tabs">
                    <div class="tab" id="uploaded-files-tab">
                        <button class="transparent">
                            <p>Uploaded files<span title="Your file ownership is stored locally."
                                    style="cursor:help">*</span></p>
                        </button>
                    </div>
                    <div class="tab" id="favorite-files-tab">
                        <button class="transparent">
                            <p>Favorites<span title="Favorite files are stored locally." style="cursor:help">*</span>
                            </p>
                        </button>
                    </div>
                </div>
                <div class="content grid grid-3 gap-8" id="uploaded-files">
                </div>
                <div class="content grid grid-3 gap-8" id="favorite-files" style="display: none;">
                </div>
            </section>

            <?php html_footer() ?>
        <?php endif; ?>
        </main>
</body>

<?php if ($file && $file['mime'] == 'application/x-shockwave-flash' && !empty(RUFFLE_DRIVER_PATH)): ?>
    <script src="<?= RUFFLE_DRIVER_PATH ?>"></script>
<?php endif; ?>

<?php if ($file): ?>
    <script>
        const fileTabButtons = document.getElementById('file-tab-buttons');
        fileTabButtons.innerHTML += `<button onclick="navigator.clipboard.writeText('${window.location.href}')">Copy URL</button>`;
    </script>
    <script src="/static/scripts/favorites.js"></script>
<?php endif; ?>

<?php if ($file && !isset($_SESSION['is_moderator'])): ?>
    <script>
        // adding deletion button
        const files = JSON.parse(localStorage.getItem('uploaded_files') ?? '[]');
        const file = files.find((x) => x.id === '<?= $file['id'] ?>');
        if (file && file.urls && file.urls.deletion_url) {
            fileTabButtons.innerHTML = `<a href='${file.urls.deletion_url}'><button>Delete</button></a>` + fileTabButtons.innerHTML;
        }
    </script>
<?php elseif (!$file): ?>
    <script>
        const formTabs = document.getElementById('form-upload-tabs');
        const thumbnailPathPrefix = "<?= FILE_THUMBNAIL_DIRECTORY_PREFIX ?>";
    </script>
    <script src="/static/scripts/audiorecorder.js"></script>
    <script src="/static/scripts/options.js"></script>
    <script src="/static/scripts/tabs.js"></script>
    <script src="/static/scripts/upload.js"></script>
    <script src="/static/scripts/favorites.js"></script>
    <script src="/static/scripts/form.js"></script>
    <script>
        document.querySelectorAll(".remove-script").forEach((x) => {
            x.remove();
        });

        formTabs.style.display = 'flex';

        document.querySelector('#form-box>.tab').remove();

        const formDetails = document.getElementById('form-upload-options');

        document.getElementById('form-text-upload').style.display = 'none';

        const uploadedFiles = document.getElementById('uploaded-files');
        const fileUploadWrapper = document.querySelector('#form-upload-wrapper>button');
        fileUploadWrapper.style.display = 'block';

        let files = [];

        const formUpload = document.getElementById('form-upload');
        formUpload.addEventListener('submit', (event) => {
            event.preventDefault();
            displayTab('file-tabs', 'uploaded-files');
            if (files.length > 0) {
                for (const file of files) {
                    const form = new FormData(formUpload);
                    form.set("file", file);
                    form.delete("paste");
                    form.delete("url");
                    uploadData(form);
                }
                files = [];
            } else {
                const form = new FormData(formUpload);
                form.delete("file");
                uploadData(form);
            }
            files = [];

            fileUploadWrapper.innerHTML = '<h1>Click, drop, or paste files here</h1>';
            setFormDetailsVisiblity(false);
            showFile(null);
            fileUploadWrapper.style.display = 'block';
            fileURL.value = '';

            document.querySelector('#uploaded-files').parentElement.style.display = 'grid';
        });

        const fileURLWrapper = document.querySelector('#form-upload-wrapper>div');
        const fileURL = document.getElementById('form-url');
        fileURL.addEventListener('keyup', () => {
            fileUploadWrapper.style.display = fileURL.value.length == 0 ? 'block' : 'none';
            setFormDetailsVisiblity(fileURL.value.length > 0);
        });

        const textArea = document.querySelector('#form-text-upload>textarea');
        textArea.addEventListener('keyup', () => {
            setFormDetailsVisiblity(textArea.value.length > 0);
        });

        const formSubmitButton = document.querySelector('#form-upload button[type=submit]');

        const formFile = document.getElementById('form-file');
        formFile.style.display = 'none';
        formFile.addEventListener("change", (e) => {
            files = [];
            for (const file of e.target.files) {
                files.push(file);
            }
            showFile(files);
        });

        fileUploadWrapper.addEventListener("click", () => formFile.click());

        // ---------------------
        // DRAG AND DROP FEATURE
        // ---------------------
        fileUploadWrapper.addEventListener("drop", (e) => {
            e.preventDefault();
            files = [];
            if (e.dataTransfer.items) {
                for (const item of e.dataTransfer.items) {
                    if (item.kind === "file") {
                        file = item.getAsFile();
                        files.push(file);
                    }
                }
                showFile(files);
            }
        });
        fileUploadWrapper.addEventListener("dragover", (e) => {
            e.preventDefault();
            fileUploadWrapper.innerHTML = '<h1>Drop files here</h1>';
            fileURLWrapper.style.display = 'none';
        });
        fileUploadWrapper.addEventListener("dragleave", (e) => {
            showFile(files);
        });

        setFormDetailsVisiblity(false);
        const uploadedFileCount = getUploadedFiles().length;
        const favoriteFileCount = getFavoriteFiles().length;
        if (uploadedFileCount > 0) {
            document.querySelector('#uploaded-files').parentElement.style.display = 'grid';
            displayTab('file-tabs', 'uploaded-files');
        }

        if (favoriteFileCount > 0) {
            document.querySelector('#favorite-files').parentElement.style.display = 'grid';
            if (uploadedFileCount == 0) {
                displayTab('file-tabs', 'favorite-files');
            }
        }
    </script>
<?php endif; ?>

</html>