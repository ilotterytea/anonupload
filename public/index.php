<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';
?>
<html>

<head>
    <title><?= INSTANCE_NAME ?></title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
</head>

<body>
    <main>
        <noscript>No JavaScript Mode</noscript>
        <?php html_big_navbar() ?>

        <section class="box column">
            <div class="tab">
                <p>File Upload</p>
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
                    </div>

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
    </main>
</body>

<script>
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
        fileURL.addEventListener('change', () => {
            fileUploadWrapper.style.display = fileURL.value.length == 0 ? 'block' : 'none';
            formSubmitButton.style.display = fileURL.value.length == 0 ? 'none' : 'block';
        });
    <?php endif; ?>

    const formSubmitButton = document.querySelector('#form-upload button[type=submit]');

    const formFile = document.getElementById('form-file');
    formFile.style.display = 'none';
    formFile.addEventListener("change", (e) => {
        file = e.target.files[0];
        if (file) {
            fileUploadWrapper.innerHTML = `<h1>File: ${file.name}</h1>`;
            formSubmitButton.style.display = 'block';
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
                    formSubmitButton.style.display = 'block';
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

    formSubmitButton.style.display = 'none';

    function fileUpload(is_url) {
        const form = new FormData(formUpload);
        if (file) {
            form.set('file', file);
        }

        fileUploadWrapper.innerHTML = is_url ? `<h1>Uploading ${fileURL.value}</h1><p>This might take a while...</p>` : `<h1>Uploading ${file.name}...</h1><p>This might take a while...</p>`;
        fileUploadWrapper.style.display = 'block';
        <?php if (FILEEXT_ENABLED): ?>
            fileURLWrapper.style.display = 'none';
            fileURL.value = '';
        <?php endif; ?>
        file = null;
        formSubmitButton.style.display = 'none';

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

                // saving file
                let files = getUploadedFiles();
                files.unshift(json.data);
                localStorage.setItem('uploaded_files', JSON.stringify(files));
            });
    }

    function addUploadedFile(file) {
        return `
        <div class="box item column gap-4 pad-4">
            <div class="column align-center justify-center grow">
                <div style="max-width: 128px; max-height:128px;">
                    <img src="/userdata/${file.id}.${file.extension}" alt="${file.id}.${file.extension}" style="max-width:100%; max-height: 100%;">
                </div>
            </div>
            <h2>${file.id}.${file.extension}</h2>
            <div>
                <p>${file.mime}</p>
                <p title="${file.size} B">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
            </div>
            <div class="row gap-8">
                <a href="/${file.id}.${file.extension}">
                    <button>Open</button>
                </a>
            </div>
        </div>
        `;
    }

    // loading already existing uploaded files
    function loadUploadedFiles() {
        let files = getUploadedFiles();

        let html = '';

        for (const file of files) {
            html += addUploadedFile(file);
        }

        if (html.length > 0) {
            uploadedFiles.parentElement.style.display = 'flex';
        }

        uploadedFiles.innerHTML = html;

        localStorage.setItem('uploaded_files', JSON.stringify(files));
    }

    loadUploadedFiles();

    function getUploadedFiles() {
        let files = localStorage.getItem("uploaded_files");
        if (!files) {
            files = '[]';
        }
        return JSON.parse(files);
    }
</script>

</html>