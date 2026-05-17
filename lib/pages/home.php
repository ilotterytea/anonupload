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
            <input type="file" name="file[]" id="upload-file" required multiple>
            <button type="submit">upload</button>
            <button id="huge-upload-button" style="display:none">click, drop, or paste files here</button>
        </form>
        <section id="file-upload-queue">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </section>
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
        const fileUploadQueue = document.querySelector("#file-upload-queue tbody");
        if (!form || !fileUploadElement || !submitButton || !fakeUploadButton || !fileUploadQueue) return;

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

            for (const file of cachedFiles) {
                // -- creating file in queue
                const fileElement = document.createElement("tr");
                const fileIconElement = document.createElement("img");

                const fileNameElement = document.createElement("p");
                fileNameElement.textContent = file.name;

                const fileStatusElement = document.createElement("p");
                fileStatusElement.textContent = 'uploading...';

                const fileOpenButton = document.createElement("button");
                fileOpenButton.textContent = 'open';
                fileOpenButton.style.display = 'none';

                const fileCopyButton = document.createElement("button");
                fileCopyButton.textContent = 'copy';
                fileCopyButton.style.display = 'none';

                for (const e of [fileIconElement, fileNameElement, [fileStatusElement, fileOpenButton, fileCopyButton]]) {
                    const td = document.createElement("td");

                    if (Array.isArray(e)) {
                        const div = document.createElement("div");
                        for (const d of e) div.appendChild(d);
                        td.appendChild(div);
                    } else {
                        td.appendChild(e);
                    }

                    fileElement.append(td);
                }

                fileUploadQueue.append(fileElement);

                // -- uploading file
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
                }).then((j) => {
                    const d = j.data;
                    fileNameElement.textContent = `${d.id}.${d.extension}`;
                    fileStatusElement.style.display = 'none';

                    fileOpenButton.style.display = 'inline';
                    fileOpenButton.addEventListener("click", () => window.location.href = d.urls.download_url);

                    if (navigator.clipboard) {
                        fileCopyButton.style.display = 'inline';
                        fileCopyButton.addEventListener("click", () => {
                            navigator.clipboard.writeText(d.urls.download_url);
                        });
                    }

                    // -- saving file in history
                    let fileHistory = JSON.parse(localStorage.getItem("uploaded_files") ?? "[]");
                    fileHistory.unshift(d);
                    localStorage.setItem("uploaded_files", JSON.stringify(fileHistory));
                })
                    .catch((e) => {
                        fileStatusElement.classList.add("error");
                        fileStatusElement.textContent = e;
                    });
            }

            cachedFiles = [];
        });
    });
</script>

</html>