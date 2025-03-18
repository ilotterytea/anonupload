<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/../lib/partials.php';

$accepted_mime_types = [];

foreach (FILE_ACCEPTED_MIME_TYPES as $k => $v) {
    $m = [];

    foreach ($v as $z) {
        array_push($m, "$k/$z");
    }

    array_push($accepted_mime_types, implode(', ', $m));
}

$accepted_mime_types = implode(', ', $accepted_mime_types);
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
                    <input type="file" name="file" accept="<?= $accepted_mime_types ?>" id="form-file">

                    <button type="button" id="form-upload-wrapper" style="display: none">
                        <h1>Click here to start upload</h1>
                    </button>

                    <button type="submit">Upload</button>
                </form>
            </div>
        </section>
    </main>
</body>

<script>
    const formUpload = document.getElementById('form-upload');
    formUpload.addEventListener('submit', (event) => {
        event.preventDefault();
        fileUpload();
    });

    const formUploadWrapper = document.getElementById('form-upload-wrapper');
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

                alert(`File ID: ${json.data.id}`);
            });
    }
</script>

</html>