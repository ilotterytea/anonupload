<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/alert.php';

session_start();

$db = new PDO(DB_URL, DB_USER, DB_PASS);

if (FILE_CATALOG_RANDOM && isset($_GET['random'])) {
    $stmt = $db->query('SELECT id, extension FROM files ORDER BY rand() LIMIT 1');
    $stmt->execute();

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        header("Location: /{$row['id']}.{$row['extension']}");
        exit;
    }
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

    $file_path = FILE_UPLOAD_DIRECTORY . "/{$file_id}.{$file_ext}";

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit();
    }

    $stmt = $db->prepare('SELECT fm.*, f.*
        FROM files f
        LEFT JOIN file_metadata fm ON fm.id = f.id
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
    session_commit();

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
        array_push($file['resolution'], format_timestamp($file['duration']));
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
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <main>
        <noscript>
            <p><b>No-JavaScript chad <img src="/static/img/icons/chad.png" alt="" width="16"></b></p>
            <p style="color:gray">no fancy features like local file saving</p>
        </noscript>

        <?php if ($file): ?>
            <div class="row">
                <?php html_mini_navbar() ?>
                <div class="font-small column grow justify-end align-bottom">
                    <div class="row gap-8 grow align-bottom">
                        <p title="<?= $file['size'] ?>B"><?= $file['size_formatted'] ?></p>
                        <p><?= $file['mime'] ?> &#40;<?= $file['extension'] ?>&#41;</p>
                        <?php if (isset($file['resolution'])): ?>
                            <p><?= $file['resolution'] ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="row gap-8 grow align-bottom">
                        <p>Uploaded <?= format_timestamp(time() - strtotime($file['uploaded_at'])) ?> ago</p>
                    </div>
                    <div class="row gap-8 grow align-bottom">
                        <?php if (FILE_COUNT_VIEWS && isset($file['views'])): ?>
                            <p><?= $file['views'] ?> views</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php display_alert() ?>

            <section class="file-preview-wrapper">
                <section class="box">
                    <div class="tab row gap-8">
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
            </section>
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
                                <tr>
                                    <th>Title:</th>
                                    <td>
                                        <input type="text" name="title" placeholder="Leave empty if you want a random title"
                                            maxlength="<?= FILE_TITLE_MAX_LENGTH ?>">
                                    </td>
                                </tr>
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
                            <button type="submit">Upload</button>
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

<?php if ($file && !isset($_SESSION['is_moderator'])): ?>
    <script>
        // adding deletion button
        const files = JSON.parse(localStorage.getItem('uploaded_files') ?? '[]');
        const file = files.find((x) => x.id === '<?= $file['id'] ?>');
        if (file && file.urls && file.urls.deletion_url) {
            const buttons = document.getElementById('file-tab-buttons');
            buttons.innerHTML = `<a href='${file.urls.deletion_url}'><button>Delete</button></a>` + buttons.innerHTML;
        }
    </script>
<?php elseif (!$file): ?>
    <script>
        const formTabs = document.getElementById('form-upload-tabs');
    </script>
    <script src="/static/scripts/audiorecorder.js"></script>
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

                    // saving file
                    let files = getUploadedFiles();
                    files.unshift(json.data);
                    localStorage.setItem('uploaded_files', JSON.stringify(files));

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
                file_deletion = `<button onclick="deleteUploadedFile('${file.urls.deletion_url}', '${file.id}')">Delete</button>`;
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
            <button class="delete-btn" onclick="deleteFileLocally('${file.id}');loadUploadedFiles();" title="Delete locally">
                <img src="/static/img/icons/cross.png" alt="X">
            </button>
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
            </div>
        </div>
        `;
        }

        function deleteUploadedFile(url, id) {
            fetch(url, {
                'headers': {
                    'Accept': 'application/json'
                },
                'method': 'DELETE'
            }).then((r) => r.json())
                .then((json) => {
                    if (json.status_code != 200) {
                        alert(`${json.message} (${json.status_code})`);
                        return;
                    }

                    deleteFileLocally(id);
                    loadUploadedFiles();
                })
                .catch((err) => {
                    alert('Failed to delete the file. Look into the console!');
                    console.error(err);
                });
        }

        function deleteFileLocally(id) {
            let files = getUploadedFiles();
            files = files.filter((x) => x.id !== id);
            localStorage.setItem('uploaded_files', JSON.stringify(files));
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