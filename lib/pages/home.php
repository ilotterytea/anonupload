<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$recently_uploaded_files = $_SESSION['recently_uploaded_files'] ?? [];
unset($_SESSION['recently_uploaded_files']);
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head(); ?>
</head>

<body>
    <header>
        <?php html_header(); ?>
        <h2>max upload size is <?= get_cfg_var(option: 'upload_max_filesize') ?></h2>
    </header>
    <main>
        <form action="/upload" method="post" enctype="multipart/form-data" autocomplete="off" id="form-upload">
            <button id="huge-upload-button" style="display:none">click, drop, or paste files here</button>
            <input type="file" name="file[]" id="upload-file" required multiple>
            <div class="options">
                <fieldset>
                    <legend>general</legend>

                    <table class="vertical">
                        <tr>
                            <th><label for="file-password">password<sup class="hint iconless"
                                        title="for file deletion">[?]</sup>:</label>
                            </th>
                            <td><input type="text" name="password" id="file-password"></td>
                        </tr>
                    </table>
                </fieldset>
                <fieldset>
                    <legend>miscellaneous</legend>

                    <table class="vertical">
                        <tr>
                            <th><label for="file-originalname">preserve original filename:</label></th>
                            <td><input type="checkbox" name="preserve_original_filename" id="file-originalname"
                                    value="1"></td>
                        </tr>
                        <tr>
                            <th><label for="file-exif">strip EXIF data:</label></th>
                            <td><input type="checkbox" name="strip_exif_data" id="file-exif" value="1"></td>
                        </tr>
                    </table>
                </fieldset>
            </div>
            <button type="submit">upload</button>
            <input type="hidden" name="save_upload_list" value="1">
        </form>
        <p class="hint" id="upload-hint">the page will be refreshed once all uploads are complete</p>
        <section id="file-upload-queue">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recently_uploaded_files as $f): ?>
                        <?php if (is_array($f)): ?>
                            <tr>
                                <td></td>
                                <td>
                                    <p class="file-name"><?= $f['original_name'] ?></p>
                                </td>
                                <td>
                                    <p class="error status"><?= $f['error'] ?></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td>
                                    <?php if (str_starts_with($f->mime, "image/")): ?>
                                        <img src="/static/img/icons/file_image.png" alt="[I]" title="image file">
                                    <?php elseif (str_starts_with($f->mime, "video/")): ?>
                                        <img src="/static/img/icons/file_video.png" alt="[V]" title="video file">
                                    <?php elseif (str_starts_with($f->mime, "audio/")): ?>
                                        <img src="/static/img/icons/file_audio.png" alt="[A]" title="audio file">
                                    <?php elseif (str_starts_with($f->mime, "text/")): ?>
                                        <img src="/static/img/icons/file_text.png" alt="[T]" title="text file">
                                    <?php elseif ($f->mime === "application/x-shockwave-flash"): ?>
                                        <img src="/static/img/icons/file_flash.png" alt="[S]" title="flash file">
                                    <?php else: ?>
                                        <img src="/static/img/icons/file.png" alt="[F]" title="file">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <p class="file-name">
                                        <?= "{$f->id}.{$f->extension}" ?>
                                    </p>
                                </td>
                                <td>
                                    <div class="details">
                                        <a href="<?= $f->url ?>" class="button" target="_blank">open</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
    <footer>
        <?php html_footer(); ?>
    </footer>
</body>

<script src="/static/scripts/options.js"></script>
<script src="/static/scripts/file.js"></script>
<script>
    let cachedFiles = [];

    function setStatus(element, message) {
        element.style.display = message.length > 0 ? 'flex' : 'none';
        element.textContent = message;
    }

    window.addEventListener("load", () => initOptions("form-upload"));

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
        document.querySelector("input[name=\"save_upload_list\"]").remove();

        fakeUploadButton.setAttribute("active", "true");

        fileUploadElement.removeAttribute("required");

        const hint = document.getElementById("upload-hint");
        if (hint) {
            hint.textContent = "the upload will start immediately after selecting the file";
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
                fileElement.setAttribute("status", "progress");

                const fileIconElement = document.createElement("img");

                // setting icon according to file type (not thumbnail)
                if (file.type.startsWith("image/")) {
                    fileIconElement.src = '/static/img/icons/file_image.png';
                    fileIconElement.alt = '[I]';
                    fileIconElement.title = 'image file';
                } else if (file.type.startsWith("video/")) {
                    fileIconElement.src = '/static/img/icons/file_video.png';
                    fileIconElement.alt = '[V]';
                    fileIconElement.title = 'video file';
                } else if (file.type.startsWith("audio/")) {
                    fileIconElement.src = '/static/img/icons/file_audio.png';
                    fileIconElement.alt = '[A]';
                    fileIconElement.title = 'audio file';
                } else if (file.type.startsWith("text/")) {
                    fileIconElement.src = '/static/img/icons/file_text.png';
                    fileIconElement.alt = '[T]';
                    fileIconElement.title = 'text file';
                } else if (file.type === "application/x-shockwave-flash") {
                    fileIconElement.src = '/static/img/icons/file_flash.png';
                    fileIconElement.alt = '[S]';
                    fileIconElement.title = 'flash file';
                } else {
                    fileIconElement.src = '/static/img/icons/file.png';
                    fileIconElement.alt = '[F]';
                    fileIconElement.title = 'file';
                }

                const fileNameElement = document.createElement("p");
                fileNameElement.classList.add("file-name");
                fileNameElement.textContent = file.name;

                const fileStatusElement = document.createElement("p");
                setStatus(fileStatusElement, "uploading...");

                const fileOpenButton = document.createElement("button");
                fileOpenButton.textContent = 'open';
                fileOpenButton.style.display = 'none';

                const fileCopyButton = document.createElement("button");
                fileCopyButton.innerHTML = '<img src="/static/img/icons/paste_plain.png" alt="[C]" title="copy URL" />';
                fileCopyButton.style.display = 'none';

                const fileDeleteButton = document.createElement("button");
                fileDeleteButton.innerHTML = '<img src="/static/img/icons/cross.png" alt="[X]" title="delete this file" />';
                fileDeleteButton.style.display = 'none';

                for (const e of [fileIconElement, fileNameElement, [fileStatusElement, fileOpenButton, fileCopyButton, fileDeleteButton]]) {
                    const td = document.createElement("td");

                    if (Array.isArray(e)) {
                        const div = document.createElement("div");
                        div.classList.add("details");
                        for (const d of e) div.appendChild(d);
                        td.appendChild(div);
                    } else {
                        td.appendChild(e);
                    }

                    fileElement.append(td);
                }

                fileUploadQueue.append(fileElement);

                // -- uploading file
                const formData = new FormData(form);

                formData.set("file", file);

                fetch(form.getAttribute("action"), {
                    method: "POST",
                    headers: {
                        "Accept": "application/json"
                    },
                    body: formData
                }).then((r) => {
                    if (r.status !== 201 && r.headers.get('Content-Type') !== 'application/json') {
                        return Promise.reject(`${r.status} ${r.statusText}`);
                    }
                    return r.json();
                }).then((j) => {
                    const d = j.data;
                    if (j.status_code !== 201) {
                        return Promise.reject(j.message ?? d.error ?? `Received ${j.status_code} code`);
                    }
                    fileNameElement.textContent = `${d.id}.${d.extension}`;
                    setStatus(fileStatusElement, '');
                    fileElement.setAttribute("status", "success");

                    if (d.urls) {
                        if (d.urls.download_url) {
                            fileOpenButton.style.display = 'inline';
                            fileOpenButton.addEventListener("click", () => window.open(d.urls.download_url, '_blank'));

                            if (navigator.clipboard) {
                                fileCopyButton.style.display = 'inline';
                                fileCopyButton.addEventListener("click", () => {
                                    navigator.clipboard.writeText(d.urls.download_url);
                                });
                            }
                        }

                        if (d.urls.deletion_url) {
                            fileDeleteButton.style.display = 'inline';
                            fileDeleteButton.addEventListener("click", () => {
                                fileDeleteButton.style.display = 'none';
                                setStatus(fileStatusElement, 'deleting...');

                                fetch(d.urls.deletion_url, {
                                    method: "DELETE",
                                    headers: {
                                        "Accept": "application/json"
                                    }
                                })
                                    .then((r) => r.json())
                                    .then((j) => {
                                        if (j.status_code === 200 || j.status_code == 404) {
                                            setStatus(fileStatusElement, "deleted");
                                            fileOpenButton.style.display = 'none';
                                            fileCopyButton.style.display = 'none';
                                            fileDeleteButton.style.display = 'none';
                                            fileElement.setAttribute("status", "deleted");
                                            deleteUploadedFile(d.id);
                                        } else {
                                            fileElement.setAttribute("status", "error");
                                            setStatus(fileStatusElement, j.message);
                                        }
                                    })
                                    .catch((err) => {
                                        alert("Failed to delete this file. Check the console.");
                                        console.error(err);
                                    });
                            });
                        }
                    }

                    saveUploadedFile(d);
                })
                    .catch((e) => {
                        setStatus(fileStatusElement, e);
                        fileElement.setAttribute("status", "error");
                    });
            }

            cachedFiles = [];
        });
    });
</script>

</html>