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

    $in = !empty($random_viewed_files) ? (str_repeat('?,', count($random_viewed_files) - 1) . '?') : '';
    $in_condition = !empty($random_viewed_files) ? "WHERE id NOT IN ($in)" : "";

    do {
        $stmt = $db->prepare("SELECT id, extension FROM files $in_condition ORDER BY rand() LIMIT 1");
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

    $file_exists = is_file(FILE_UPLOAD_DIRECTORY . "/$file_id.$file_ext");

    // counting views
    $viewed_file_ids = $_SESSION['viewed_file_ids'] ?? [];
    if (!in_array($file['id'], $viewed_file_ids)) {
        $file['views']++;
        array_push($viewed_file_ids, $file['id']);
        $db->prepare('UPDATE files SET views = ? WHERE id = ? AND extension = ?')->execute([$file['views'], $file['id'], $file['extension']]);
    }
    $_SESSION['viewed_file_ids'] = $viewed_file_ids;

    if (
        $file_exists &&
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
}

$tos_exists = is_file($_SERVER['DOCUMENT_ROOT'] . '/static/TOS.txt');
$privacy_exists = is_file($_SERVER['DOCUMENT_ROOT'] . '/static/PRIVACY.txt');
?>
<html>

<head>
    <?php if ($file): ?>
        <title><?= $file['name'] ?> - <?= INSTANCE_NAME ?></title>
        <meta property="og:title" content="<?= $file['name'] ?> - <?= INSTANCE_NAME ?>" />
        <meta property="og:description" content="<?= $file['size_formatted'] ?> - <?= $file['mime'] ?> &#40;<?= $file['extension'] ?>&#41;
                        <?php if (isset($file['resolution'])): ?>
                            - <?= $file['resolution'] ?><?php endif; ?>" />
        <meta property="og:url" content="<?= sprintf("%s/%s.%s", INSTANCE_URL, $file['id'], $file['extension']) ?>" />
        <meta property="og:type" content="website" />
        <?php if (FILE_THUMBNAILS): ?>
            <meta property="og:image"
                content="<?= sprintf('%s%s/%s.webp', INSTANCE_URL, FILE_THUMBNAIL_DIRECTORY_PREFIX, $file['id']) ?>" />
        <?php endif; ?>
    <?php else: ?>
        <title><?= INSTANCE_NAME ?></title>
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="theme-color" content="#ffe1d4">
</head>

<body>
    <main>
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
            <?php endif; ?>

            <?php if ($file_exists): ?>
                <section class="file-preview-wrapper">
                    <section class="box">
                        <div class="tab row wrap gap-8">
                            <div class="grow">
                                <?php if (isset($file['title'])): ?>
                                    <p><i><?= $file['title'] ?></i></p>
                                <?php else: ?>
                                    <p>File <?= sprintf('%s.%s', $file['id'], $file['extension']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="grow row gap-8 justify-end align-center" id="file-tab-buttons">
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
                                <a href="<?= $file['full_url'] ?>" download="<?= $file['name'] ?>">
                                    <button>Download</button>
                                </a>
                            </div>
                        </div>
                        <div class="content column file-preview">
                            <?php if (str_starts_with($file['mime'], 'image/')): ?>
                                <img src="<?= $file['full_url'] ?>" alt="Image file.">
                            <?php elseif (str_starts_with($file['mime'], 'video/')): ?>
                                <video controls autoplay>
                                    <source src="<?= $file['full_url'] ?>" type="<?= $file['mime'] ?>">
                                </video>
                            <?php elseif (str_starts_with($file['mime'], 'audio/')): ?>
                                <audio controls autoplay>
                                    <source src="<?= $file['full_url'] ?>" type="<?= $file['mime'] ?>">
                                </audio>
                            <?php elseif (str_starts_with($file['mime'], 'text/')): ?>
                                <pre><?= file_get_contents(FILE_UPLOAD_DIRECTORY . "/{$file['id']}.{$file['extension']}") ?></pre>
                            <?php else: ?>
                                <p><i>This file cannot be displayed.</i></p>
                            <?php endif; ?>
                        </div>

                    </section>

                    <div class="font-small row wrap justify-end gap-16 align-bottom">
                        <p title="<?= $file['size'] ?>B"><?= $file['size_formatted'] ?></p>
                        <p><?= $file['mime'] ?> &#40;<?= $file['extension'] ?>&#41;</p>
                        <?php if (isset($file['resolution'])): ?>
                            <p><?= $file['resolution'] ?></p>
                        <?php endif; ?>
                        <p title="<?= $file['uploaded_at'] ?>">Uploaded <?= format_timestamp($file['uploaded_at']) ?> ago</p>
                        <?php if (FILE_COUNT_VIEWS && isset($file['views'])): ?>
                            <p><?= $file['views'] ?> views</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
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
                <div class="tabs" id="form-upload-tabs" style="display: none;">
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
                            accept="<?= implode(', ', array_unique(array_values(FILE_ACCEPTED_MIME_TYPES))) ?>"
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
                            </table>
                            <button type="submit" class="fancy">Upload</button>
                    </form>
                </div>
                </div>
            </section>

            <section class="box column" style="display:none">
                <div class="tab">
                    <p>Uploaded files<span title="Your file ownership is stored locally." style="cursor:help">*</span></p>
                </div>
                <div class="content grid grid-3 gap-8" id="uploaded-files">
                </div>
            </section>

            <?php html_footer() ?>
        <?php endif; ?>
    </main>
</body>

<?php if ($file): ?>
    <script>
        const fileTabButtons = document.getElementById('file-tab-buttons');
        fileTabButtons.innerHTML += `<button onclick="navigator.clipboard.writeText('${window.location.href}')">Copy URL</button>`;
    </script>
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
    </script>
    <script src="/static/scripts/audiorecorder.js"></script>
    <script src="/static/scripts/options.js"></script>
    <script>
        document.querySelectorAll(".remove-script").forEach((x) => {
            x.remove();
        });

        formTabs.style.display = 'flex';

        document.querySelector('#form-box>.tab').remove();

        const formDetails = document.getElementById('form-upload-options');

        document.getElementById('form-text-upload').style.display = 'none';
        let file = null;

        const uploadedFiles = document.getElementById('uploaded-files');
        <?php if (FILEEXT_ENABLED): ?>
            const fileURL = document.getElementById('form-url');
        <?php endif; ?>

        const formUpload = document.getElementById('form-upload');
        formUpload.addEventListener('submit', (event) => {
            event.preventDefault();
            <?php if (FILEEXT_ENABLED): ?>
                fileUpload(fileURL.value.length != 0);
            <?php else: ?>
                fileUpload(false);
            <?php endif; ?>
        });

        const fileUploadWrapper = document.querySelector('#form-upload-wrapper>button');
        fileUploadWrapper.style.display = 'block';

        <?php if (FILEEXT_ENABLED): ?>
            const fileURLWrapper = document.querySelector('#form-upload-wrapper>div');
            fileURL.addEventListener('keyup', () => {
                fileUploadWrapper.style.display = fileURL.value.length == 0 ? 'block' : 'none';
                setFormDetailsVisiblity(fileURL.value.length > 0);
            });
        <?php endif; ?>

        const textArea = document.querySelector('#form-text-upload>textarea');
        textArea.addEventListener('keyup', () => {
            setFormDetailsVisiblity(textArea.value.length > 0);
        });

        const formSubmitButton = document.querySelector('#form-upload button[type=submit]');

        const formFile = document.getElementById('form-file');
        formFile.style.display = 'none';
        formFile.addEventListener("change", (e) => {
            file = e.target.files[0];
            showFile(file);
        });

        fileUploadWrapper.addEventListener("click", () => formFile.click());
        fileUploadWrapper.addEventListener("drop", (e) => {
            e.preventDefault();
            if (e.dataTransfer.items) {
                for (const item of e.dataTransfer.items) {
                    if (item.kind === "file") {
                        file = item.getAsFile();
                        showFile(file);
                        break;
                    }
                }
            }
        });
        fileUploadWrapper.addEventListener("dragover", (e) => {
            e.preventDefault();
            fileUploadWrapper.innerHTML = '<h1>Drop files here</h1>';
            <?php if (FILEEXT_ENABLED): ?>
                fileURLWrapper.style.display = 'none';
            <?php endif; ?>
        });
        fileUploadWrapper.addEventListener("dragleave", (e) => {
            showFile(file);
        });

        setFormDetailsVisiblity(false);

        if (textArea.value.length > 0) {
            setFormDetailsVisiblity(true);
            showUploadType('text');
        }

        function fileUpload(is_url) {
            if (textArea.value.length > 0) {
                file = null;
                formFile.value = null;
            }

            const form = new FormData(formUpload);

            if (file) {
                form.set('file', file);
            }

            if (is_url) {
                fileUploadWrapper.innerHTML = `<h1>Uploading ${fileURL.value}</h1><p>This might take a while...</p>`;
            } else if (file) {
                fileUploadWrapper.innerHTML = `<h1>Uploading ${file.name}...</h1><p>This might take a while...</p>`;
            } else {
                fileUploadWrapper.innerHTML = `<h1>Uploading...</h1>`;
            }
            fileUploadWrapper.style.display = 'block';
            <?php if (FILEEXT_ENABLED): ?>
                fileURLWrapper.style.display = 'none';
                fileURL.value = '';
            <?php endif; ?>
            file = null;
            setFormDetailsVisiblity(false);

            fetch(formUpload.getAttribute('action'), {
                'body': form,
                'method': 'POST',
                'headers': {
                    'Accept': 'application/json'
                }
            })
                .catch((err) => {
                    console.error(err);
                    alert('Failed to send a file. More info in the console...');
                    <?php if (FILEEXT_ENABLED): ?>
                        fileURLWrapper.style.display = 'flex';
                    <?php endif; ?>
                    fileUploadWrapper.style.display = 'block';
                    fileUploadWrapper.innerHTML = '<h1>Click, drop, or paste files here</h1>';
                })
                .then((r) => r.json())
                .then((json) => {
                    fileUploadWrapper.innerHTML = '<h1>Click, drop, or paste files here</h1>';
                    <?php if (FILEEXT_ENABLED): ?>
                        fileURLWrapper.style.display = 'flex';
                    <?php endif; ?>
                    fileUploadWrapper.style.display = 'block';

                    if (json.status_code != 201) {
                        alert(`${json.message} (${json.status_code})`);
                        return;
                    }

                    uploadedFiles.innerHTML = addUploadedFile(json.data) + uploadedFiles.innerHTML;
                    uploadedFiles.parentElement.style.display = 'flex';
                    textArea.value = '';

                    addFileLocally(json.data);

                    formUpload.reset();
                });
        }

        function addUploadedFile(file) {
            let file_url = `/${file.id}.${file.extension}`;
            if (file.urls && file.urls.download_url) {
                file_url = file.urls.download_url;
            }
            let file_deletion = '';
            if (file.urls && file.urls.deletion_url) {
                file_deletion = `<button onclick="deleteUploadedFile('${file.urls.deletion_url}', '${file.id}')" title="Delete">
                    <img src="/static/img/icons/cross.png" alt="Delete">
                </button>`;
            }

            <?php if (FILE_THUMBNAILS): ?>
                let thumbnailPath = `<?= FILE_THUMBNAIL_DIRECTORY_PREFIX ?>/${file.id}.webp`;
                let thumbnailSize = "width: 64px; height: 64px;";
                if (file.mime.startsWith('audio/')) {
                    thumbnailPath = '/static/img/icons/file_audio.png';
                } else if (file.mime.startsWith('text/')) {
                    thumbnailPath = '/static/img/icons/file_text.png';
                } else if (!file.mime.startsWith('image/') && !file.mime.startsWith('video/')) {
                    thumbnailPath = '/static/img/icons/file.png';
                } else {
                    thumbnailSize = 'max-width:100%; max-height: 100%;';
                }
            <?php endif; ?>

            return `
        <div class="box item column gap-4 pad-4">
            <?php if (FILE_THUMBNAILS): ?>
            <div class="column align-center justify-center grow">
                <div class="column justify-center align-center" style="width: 128px; height:128px;">
                    <p><i><img src="${thumbnailPath}" alt="No thumbnail." style="${thumbnailSize}"></i></p>
                </div>
            </div>
            <?php endif; ?>
            <h2>${file.id}.${file.extension}</h2>
            <div>
                <p>${file.mime}</p>
                <p title="${file.size} B">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
            </div>
            <div class="row gap-8">
                <a href="${file_url}">
                    <button>Open</button>
                </a>
                ${file_deletion}
                <button onclick="navigator.clipboard.writeText('${window.location.href}')" title="Copy URL">
                    <img src="/static/img/icons/paste_plain.png" alt="Copy URL">
                </button>
            </div>
        </div>
        `;
        }

        function deleteUploadedFile(url, id) {
            if (!confirm(`Are you sure you want to delete file ID ${id}?`)) {
                return;
            }

            const file = deleteFileLocally(id);

            fetch(url, {
                'headers': {
                    'Accept': 'application/json'
                },
                'method': 'DELETE'
            }).then((r) => r.json())
                .then((json) => {
                    if (json.status_code != 200) {
                        alert(`${json.message} (${json.status_code})`);
                        if (json.status_code != 404) {
                            addFileLocally(file);
                        }
                        loadUploadedFiles();
                        return;
                    }

                    loadUploadedFiles();
                })
                .catch((err) => {
                    alert('Failed to delete the file. Look into the console!');
                    console.error(err);
                });
        }

        function deleteFileLocally(id) {
            let files = getUploadedFiles();
            let file = files.filter((x) => x.id == id);
            files = files.filter((x) => x.id !== id);
            localStorage.setItem('uploaded_files', JSON.stringify(files));
            return file;
        }

        // loading already existing uploaded files
        function loadUploadedFiles() {
            let files = getUploadedFiles();

            let html = '';

            for (const file of files) {
                html += addUploadedFile(file);
            }

            uploadedFiles.parentElement.style.display = html.length > 0 ? 'flex' : 'none';

            uploadedFiles.innerHTML = html;
        }

        loadUploadedFiles();

        function getUploadedFiles() {
            let files = localStorage.getItem("uploaded_files");
            if (!files) {
                files = '[]';
            }
            return JSON.parse(files);
        }

        function addFileLocally(file) {
            let files = getUploadedFiles();
            files.unshift(file);
            localStorage.setItem('uploaded_files', JSON.stringify(files));
        }

        function showUploadType(type) {
            if (formTabs.hasAttribute('disabled')) {
                return;
            }

            document.getElementById('form-upload-wrapper').style.display = type == 'file' ? 'flex' : 'none';
            document.getElementById('form-text-upload').style.display = type == 'text' ? 'flex' : 'none';
            document.getElementById('form-record-upload').style.display = type === 'audio' ? 'flex' : 'none';

            const tabs = document.querySelectorAll('.form-upload-tab');

            for (const tab of tabs) {
                if (tab.getAttribute('id') == `form-tab-${type}`) {
                    tab.classList.remove('disabled');
                } else {
                    tab.classList.add('disabled');
                }
            }
        }

        function setFormDetailsVisiblity(show) {
            formDetails.style.display = show ? 'flex' : 'none';
            formSubmitButton.style.display = show ? 'block' : 'none';
        }
    </script>
    <script src="/static/scripts/form.js"></script>
<?php endif; ?>

</html>