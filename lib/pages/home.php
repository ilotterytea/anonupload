<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils.php';

if (IS_JSON_REQUEST) {
    $x = get_commit();
    $x['app_name'] = 'anonupload';
    send_json_response($x);
}

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
        <h2>
            max upload size is <?= get_cfg_var(option: 'upload_max_filesize') ?>
            <?php if (CONFIG['upload']['force_default_expiration'] && CONFIG['upload']['default_expiration'] !== 'ne'): ?>
                & files expire after <?= CONFIG['upload']['expiration'][CONFIG['upload']['default_expiration']] ?>
            <?php endif; ?>
        </h2>
    </header>
    <main>
        <form action="/upload" method="post" enctype="multipart/form-data" autocomplete="off" id="form-upload">
            <button id="huge-upload-button" style="display:none">click, drop, or paste files here</button>
            <input type="file" name="file[]" id="upload-file" required multiple>
            <div class="options">
                <div class="controls">
                    <fieldset>
                        <legend>general</legend>

                        <table class="vertical">
                            <tr>
                                <th><label for="file-password">password<sup class="hint iconless"
                                            title="for file deletion">[?]</sup>:</label>
                                </th>
                                <td><input type="text" name="password" id="file-password"
                                        placeholder="leave empty for permanent file"></td>
                            </tr>
                            <tr>
                                <th><label for="file-singleurl">single URL<sup class="hint iconless"
                                            title="attach all files to a single link">[?]</sup>:</label></th>
                                <td><input type="checkbox" name="single_url" id="file-singleurl" value="1"></td>
                            </tr>
                            <?php if (!empty(CONFIG['upload']['expiration']) && !CONFIG['upload']['force_default_expiration']): ?>
                                <tr>
                                    <th><label for="file-expiration">expires in:</label></th>
                                    <td>
                                        <select name="expires_in" id="file-expiration">
                                            <?php foreach (CONFIG['upload']['expiration'] as $v => $t): ?>
                                                <option value="<?= $v ?>" <?= $v === CONFIG['upload']['default_expiration'] ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endif; ?>
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
                <fieldset class="description">
                    <legend>description</legend>
                    <textarea name="description" placeholder="write your SNCA here... (Markdown supported)"></textarea>
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
        <?php html_legal(); ?>
    </footer>
</body>

<script src="/static/scripts/options.js"></script>
<script src="/static/scripts/file.js"></script>
<script>
    let cachedFiles = [];

    window.addEventListener("load", () => {
        initOptions("form-upload");

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
            hint.remove();
            fakeUploadButton.appendChild(hint);
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
            const cf = cachedFiles;

            if (cf.length === 0) {
                return;
            }

            const f = new FormData(form);
            f.delete("file[]");
            for (const file of cachedFiles) {
                f.append("file[]", file);
            }

            fetch('/track?create', {
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then((r) => {
                    if (r.status !== 201) {
                        return Promise.reject(`${r.status} ${r.statusText}`);
                    }
                    return r.json();
                })
                .then((j) => {
                    f.set("track_id", j.data.id);
                    const element = uploadData(f);
                    fileUploadQueue.prepend(element.root);
                })
                .catch((err) => {
                    const element = uploadData(f);
                    fileUploadQueue.prepend(element.root);
                });

            cachedFiles = [];
        });

        document.addEventListener("paste", (e) => {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            let files = [];

            for (const item of items) {
                if (item.kind === 'file') files.push(item.getAsFile());
            }

            if (files.length !== 0) {
                cachedFiles = files;
                submitButton.click();
            }
        });
    });
</script>

</html>