<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head(); ?>
</head>

<body>
    <header>
        <?php html_big_navbar(); ?>
        <h2>max upload size is <?= get_cfg_var(option: 'upload_max_filesize') ?></h2>
    </header>
    <main>
        <form action="/upload.php" method="post" enctype="multipart/form-data" autocomplete="off" id="form-upload">
            <input type="file" name="file" id="upload-file" required multiple>
            <button type="submit">upload</button>
            <button id="huge-upload-button" style="display:none">click, drop, or paste files here</button>
        </form>
    </main>
    <footer>
        <?php html_footer(); ?>
    </footer>
</body>

<script>
    let cachedFiles = [];

    window.addEventListener("load", () => {
        const form = document.getElementById("form-upload");
        const fileUploadElement = document.getElementById("upload-file");
        const submitButton = document.querySelector("#form-upload>button[type=\"submit\"]");
        const fakeUploadButton = document.getElementById("huge-upload-button");
        if (!form || !fileUploadElement || !submitButton || !fakeUploadButton) return;

        // -- adjusting the upload form
        fileUploadElement.style.display = 'none';
        submitButton.style.display = 'none';
        fakeUploadButton.style.display = 'flex';

        fileUploadElement.removeAttribute("required");

        {
            const p = document.createElement("p");
            p.classList.add("hint");
            p.textContent = "the upload will start immediately after selecting the file";
            form.append(p);
        }

        // -- fake upload button functionality
        fakeUploadButton.addEventListener("click", () => fileUploadElement.click());
        fakeUploadButton.addEventListener("dragleave", (ev) => {
            ev.preventDefault();
            fakeUploadButton.textContent = 'click, drop, or paste files here';
        });

        fakeUploadButton.addEventListener("dragover", (ev) => {
            ev.preventDefault();
            fakeUploadButton.textContent = 'drop files here';
        });

        fakeUploadButton.addEventListener("drop", (ev) => {
            ev.preventDefault();
            cachedFiles = [];
            fakeUploadButton.textContent = 'click, drop, or paste files here';
            if (ev.dataTransfer.items) {
                for (const item of ev.dataTransfer.items) {
                    if (item.kind === "file") {
                        cachedFiles.push(item.getAsFile());
                    }
                }
            }
            submitButton.click();
        });

        fileUploadElement.addEventListener("change", (ev) => {
            cachedFiles = [];
            cachedFiles.push(...fileUploadElement.files);
            submitButton.click();
        });

        // -- file upload via fetch
        form.addEventListener("submit", (ev) => {
            ev.preventDefault();
            console.log("uploading...");
            console.log(cachedFiles);

            for (const file of cachedFiles) {
                const formData = new FormData();

                formData.set("file", file);

                fetch(form.getAttribute("action"), {
                    method: "POST",
                    headers: {
                        "Accept": "application/json"
                    },
                    body: formData
                }).then((r) => {
                    if (r.status !== 201) {
                        return Promise.reject(`${r.status} ${r.statusText}`);
                    }
                    return r.json();
                }).then((j) => console.log(r))
                    .catch((e) => console.error(e));
            }

            cachedFiles = [];
        });
    });
</script>

</html>