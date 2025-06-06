<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/utils.php';

if (FILE_CATALOG_RANDOM && isset($_GET['random'])) {
    $files = glob(FILE_UPLOAD_DIRECTORY . "/*.*");
    $file = $files[random_int(0, count($files) - 1)];
    $filename = basename($file);
    header("Location: /{$filename}");
    exit();
}

$file = null;
$file_id = null;

if (strlen(substr($_SERVER['PHP_SELF'], strlen('/index.php'))) > 0) {
    $file_id = basename($_SERVER['PHP_SELF']);
} else if (isset($_SERVER['QUERY_STRING']) && !empty(trim($_SERVER['QUERY_STRING']))) {
    $file_id = basename($_SERVER['QUERY_STRING']);
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
    $meta_path = FILE_METADATA_DIRECTORY . "/{$file_id}.metadata.json";

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit();
    }

    if (file_exists($meta_path)) {
        $file = json_decode(file_get_contents($meta_path), true);

        if (isset($file['views'])) {
            session_start();

            $viewed_file_ids = $_SESSION['viewed_file_ids'] ?? [];

            if (!in_array($file['id'], $viewed_file_ids)) {
                $file['views']++;
                array_push($viewed_file_ids, $file['id']);
                file_put_contents($meta_path, json_encode($file, JSON_UNESCAPED_SLASHES));
            }

            $_SESSION['viewed_file_ids'] = $viewed_file_ids;

            session_commit();
        }
    } else {
        $file = [
            'id' => $file_id,
            'extension' => $file_ext,
            'mime' => FILE_ACCEPTED_MIME_TYPES[$file_ext],
            'size' => filesize($file_path)
        ];
    }

    $file['full_url'] = FILE_UPLOAD_DIRECTORY_PREFIX . "/{$file['id']}.{$file['extension']}";

    $size = $file['size'];
    $size_suffix = 'B';
    $size_i = 0;
    do {
        $size /= 1024;
        $size_suffix = match ($size_i) {
            0 => 'B',
            1 => 'KB',
            2 => 'MB',
            3 => 'GB',
            default => 'TB'
        };
        $size_i++;
    } while ($size > 1024);

    $file['size_formatted'] = sprintf('%.2f%s', $size, $size_suffix);
    $file['name'] = $file['original_name'] ?? sprintf('%s.%s', $file['id'], $file['extension']);

    if (!isset($file['uploaded_at'])) {
        $file['uploaded_at'] = filemtime($file_path);
    }

    if (str_starts_with($file['mime'], 'image/')) {
        $file['resolution'] = trim(shell_exec('identify -format "%wx%h" ' . escapeshellarg($file_path) . '[0]'));
    } else if (str_starts_with($file['mime'], 'video/')) {
        $info = shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of csv=p=0 ' . escapeshellarg($file_path));
        [$width, $height, $duration] = explode(',', trim($info));
        $file['resolution'] = sprintf('%sx%s (%s seconds)', $width, $height, round($duration, 2));
    } else if (str_starts_with($file['mime'], 'audio/')) {
        $file['resolution'] = round(trim(shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file_path))), 2) . ' seconds';
    } else if (str_starts_with($file['mime'], 'text/')) {
        $file['resolution'] = trim(shell_exec('wc -l < ' . escapeshellarg($file_path))) . ' lines';
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
                        <p>Uploaded <?= format_timestamp(time() - $file['uploaded_at']) ?> ago</p>
                    </div>
                    <div class="row gap-8 grow align-bottom">
                        <?php if (FILE_COUNT_VIEWS && isset($file['views'])): ?>
                            <p><?= $file['views'] ?> views</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <section class="file-preview-wrapper">
                <section class="box">
                    <div class="tab row">
                        <div class="grow">
                            <?php if (isset($file['original_name'])): ?>
                                <p><i><?= $file['original_name'] ?></i></p>
                            <?php else: ?>
                                <p>File <?= sprintf('%s.%s', $file['id'], $file['extension']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="grow row gap-8 justify-end align-center" id="file-tab-buttons">
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
            <noscript>No JavaScript Mode</noscript>
            <?php html_big_navbar() ?>

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

            <section class="box column">
                <div class="tabs">
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
                        <input type="file" name="file"
                            accept="<?= implode(', ', array_unique(array_values(FILE_ACCEPTED_MIME_TYPES))) ?>"
                            id="form-file">

                        <div class="column gap-8" id="form-upload-wrapper">
                            <button type="button" style="display: none;">
                                <h1>Click, or drop files here</h1>
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
                            <textarea name="paste" placeholder="Enter your text here..."></textarea>
                        </div>

                        <table class="vertical" id="form-upload-options">
                            <tr>
                                <th>Preserve original filename:</th>
                                <td><input type="checkbox" name="preserve_original_name" value="1"></td>
                            </tr>
                        </table>
                        <button type="submit">Upload</button>
                    </form>
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
        // adding deletion button
        const files = JSON.parse(localStorage.getItem('uploaded_files') ?? '[]');
        const file = files.find((x) => x.id === '<?= $file['id'] ?>');
        if (file && file.urls && file.urls.deletion_url) {
            const buttons = document.getElementById('file-tab-buttons');
            buttons.innerHTML = `<a href='${file.urls.deletion_url}'><button>Delete</button></a>` + buttons.innerHTML;
        }
    </script>
<?php else: ?>
    <script>
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
            setFormDetailsVisiblity(fileURL.value.length > 0);
        });

        const formSubmitButton = document.querySelector('#form-upload button[type=submit]');

        const formFile = document.getElementById('form-file');
        formFile.style.display = 'none';
        formFile.addEventListener("change", (e) => {
            file = e.target.files[0];
            if (file) {
                fileUploadWrapper.innerHTML = `<h1>File: ${file.name}</h1>`;
                setFormDetailsVisiblity(true);
                <?php if (FILEEXT_ENABLED): ?>
                    fileURLWrapper.style.display = 'none';
                <?php endif; ?>
            }
        });

        fileUploadWrapper.addEventListener("click", () => formFile.click());
        fileUploadWrapper.addEventListener("drop", (e) => {
            e.preventDefault();
            if (e.dataTransfer.items) {
                for (const item of e.dataTransfer.items) {
                    if (item.kind === "file") {
                        file = item.getAsFile();
                        fileUploadWrapper.innerHTML = `<h1>File: ${file.name}</h1>`;
                        setFormDetailsVisiblity(true);
                        <?php if (FILEEXT_ENABLED): ?>
                            fileURLWrapper.style.display = 'none';
                        <?php endif; ?>
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
            if (file) {
                fileUploadWrapper.innerHTML = `<h1>File: ${file.name}</h1>`;
                return;
            }
            fileUploadWrapper.innerHTML = '<h1>Click, or drop files here</h1>';
            <?php if (FILEEXT_ENABLED): ?>
                fileURLWrapper.style.display = 'flex';
            <?php endif; ?>

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
                    fileUploadWrapper.innerHTML = '<h1>Click, or drop files here</h1>';
                })
                .then((r) => r.json())
                .then((json) => {
                    fileUploadWrapper.innerHTML = '<h1>Click, or drop files here</h1>';
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

                    formUpload.clear();
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

            return `
        <div class="box item column gap-4 pad-4">
            <?php if (FILE_THUMBNAILS): ?>
            <div class="column align-center justify-center grow">
                <div style="max-width: 128px; max-height:128px;">
                    <p><i><img src="<?= FILE_THUMBNAIL_DIRECTORY_PREFIX ?>/${file.id}.webp" alt="No thumbnail." style="max-width:100%; max-height: 100%;"></i></p>
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

                    let files = getUploadedFiles();
                    files = files.filter((x) => x.id !== id);
                    localStorage.setItem('uploaded_files', JSON.stringify(files));
                    loadUploadedFiles();
                })
                .catch((err) => {
                    alert('Failed to delete the file. Look into the console!');
                    console.error(err);
                });
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
            document.getElementById('form-upload-wrapper').style.display = type == 'text' ? 'none' : 'flex';
            document.getElementById('form-text-upload').style.display = type == 'text' ? 'flex' : 'none';

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
<?php endif; ?>

</html>