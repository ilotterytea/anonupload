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

                    <button type="button" id="form-upload-wrapper" style="display: none">
                        <h1>Click here to start upload</h1>
                    </button>

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
    const uploadedFiles = document.getElementById('uploaded-files');

    const formUpload = document.getElementById('form-upload');
    formUpload.addEventListener('submit', (event) => {
        event.preventDefault();
        fileUpload();
    });

    const formUploadWrapper = document.getElementById('form-upload-wrapper');
    formUploadWrapper.style.display = 'block';

    const formSubmitButton = document.querySelector('#form-upload button[type=submit]');

    const formFile = document.getElementById('form-file');
    formFile.style.display = 'none';
    formFile.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (file) {
            formUploadWrapper.innerHTML = `<h1>File: ${file.name}</h1>`;
            formSubmitButton.style.display = 'block';
        }
    });

    formUploadWrapper.addEventListener("click", () => formFile.click());

    formSubmitButton.style.display = 'none';

    function fileUpload() {
        formUploadWrapper.innerHTML = `<h1>Uploading ${formFile.files[0].name}...</h1><p>This might take a while...</p>`;
        formSubmitButton.style.display = 'none';

        fetch(formUpload.getAttribute('action'), {
            'body': new FormData(formUpload),
            'method': 'POST',
            'headers': {
                'Accept': 'application/json'
            }
        })
            .catch((err) => {
                console.error(err);
                alert('Failed to send a file. More info in the console...');
            })
            .then((r) => r.json())
            .then((json) => {
                formUploadWrapper.innerHTML = '<h1>Click here to start upload</h1>';

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