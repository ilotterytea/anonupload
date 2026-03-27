<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

session_start();

if (CONFIG["supriseme"]["enable"] && isset($_GET['random'])) {
    $file = STORAGE->get_random_file();

    if ($file == null) {
        http_response_code(404);
        die("Random file not found.");
    }

    //array_push($random_viewed_files, $file->id);
    //$_SESSION['random_viewed_files'] = $random_viewed_files;

    http_response_code(303);
    header("Location: /{$file->id}.{$file->extension}");
    exit;
}

$file = null;
$file_id = null;
$file_name = null;
$file_html_description = null;
$url = parse_url($_SERVER['REQUEST_URI']);

if (strlen($url['path']) > 1) {
    $file_id = basename($url['path']);
}

if (CONFIG["files"]["fancyview"] && $file_id) {
    $filename = parse_file_name($file_id);
    if ($filename === null) {
        $error = "404 Not Found";
        include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/pages/error.php";
        exit();
    }
    $file_id = $filename['name'];
    $file_ext = $filename['extension'];

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $file_id) || !preg_match('/^[a-zA-Z0-9]+$/', $file_ext)) {
        include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/pages/404.php";
        exit();
    }

    $file = File::load("$file_id.$file_ext");

    if (!$file || $file->is_banned) {
        $error = "404 Not Found";
        include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/pages/error.php";
        exit();
    }

    // counting views
    $viewed_file_ids = $_SESSION['viewed_file_ids'] ?? [];
    if ($file->views !== null && !in_array($file->id, $viewed_file_ids)) {
        $file->views++;
        array_push($viewed_file_ids, $file->id);
        if (!STORAGE->save($file)) {
            throw new RuntimeException("Failed to save file: {$file->id}");
        }
    }
    $_SESSION['viewed_file_ids'] = $viewed_file_ids;

    if (
        isset($file->expires_at) &&
        (
            ($file->expires_at->getTimestamp() == $file->uploaded_at->getTimestamp() && $file->views > 1) ||
            ($file->expires_at->getTimestamp() != $file->uploaded_at->getTimestamp() && time() > $file->expires_at->getTimestamp())
        )
    ) {
        STORAGE->delete_file_by_id($file_id, $file_ext);
        $error = "404 Not Found";
        include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/pages/error.php";
        exit();
    }

    if (!CONFIG["files"]["showuploadtime"] && (!isset($_SESSION['user']) || $_SESSION['user']->role->as_value() < UserRole::Moderator->as_value())) {
        $file->uploaded_at = null;
    }

    if (!CONFIG["files"]["showviews"] && (!isset($_SESSION['user']) || $_SESSION['user']->role->as_value() < UserRole::Moderator->as_value())) {
        $file->views = null;
    }

    if (IS_JSON_REQUEST) {
        $file->password = null;
        $file = $file->as_array();
        $file['urls'] = [
            'download_url' => CONFIG["instance"]["url"] . "/{$file['id']}.{$file['extension']}"
        ];
        json_response($file, null);
        exit;
    }

    $file_full_url = CONFIG["files"]["url"] . "/{$file->id}.{$file->extension}";

    // formatting the file size
    $size = $file->size;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($size) - 1) / 3);
    $file_size_formatted = sprintf("%.2f", $size / pow(1024, $factor)) . ' ' . $units[$factor];

    $file_name = "{$file->id}.{$file->extension}";
    $file_download_name = $file_name;

    $file_resolution = [];

    if (isset($file->width, $file->height)) {
        array_push($file_resolution, sprintf('%sx%s', $file->width, $file->height));
    }

    if (isset($file->duration)) {
        $dur = format_timestamp((new DateTime())->setTimestamp(time() + $file->duration));
        array_push($file_resolution, empty($file_resolution) ? $dur : "($dur)");
    }

    if (isset($file->line_count)) {
        array_push($file_resolution, sprintf('%d lines', $file->line_count));
    }

    $file_resolution = implode(' ', $file_resolution) ?: null;

    $file_html_description = "{$file->mime} - {$file->extension}";
    if (isset($file->views)) {
        $file_html_description .= " - {$file->views} views";
    }
    if (isset($file->uploaded_at)) {
        $file_html_description .= ' - Uploaded ' . format_timestamp($file->uploaded_at) . ' ago';
    }
    if (isset($file_resolution)) {
        $file_html_description .= " - $file_resolution";
    }
}
?>
<!DOCTYPE html>
<html>

<head><?php html_head($file_name, $file_html_description, $file); ?></head>

<body>
    <?php isset($file) ? html_mini_navbar() : html_big_navbar() ?>
    <main>
        <noscript>
            <p><b>No-JavaScript chad <img src="/static/img/icons/chad.png" alt="" width="16"></b></p>
            <p style="color:gray">no fancy features like local file saving</p>
        </noscript>

        <?php display_alert() ?>

        <?php if ($file): ?>
            <div class="row grow justify-center">
                <section class="file-preview-wrapper" <?= isset($file->width) ? ('style="max-width:' . max($file->width, 256) . 'px;"') : '' ?>>
                    <section class="box">
                        <div class="tab row wrap gap-8">
                            <div class="grow">
                                <p>File <?= $file_name ?></p>
                            </div>
                            <div class="grow row gap-8 justify-end align-center wrap" id="file-tab-buttons">
                                <?php if (isset($_SESSION['user']) && $_SESSION['user']->role->as_value() >= UserRole::Moderator->as_value()): ?>
                                    <a href="/files/delete.php?id=<?= "{$file->id}.{$file->extension}" ?>" class="button"
                                        id="file-deletion-button">
                                        Delete
                                    </a>
                                <?php endif; ?>
                                <?php if (CONFIG["report"]["enable"]): ?>
                                    <a href="/files/report.php?id=<?= "{$file->id}.{$file->extension}" ?>" class="button">
                                        Report
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $file_full_url ?>" class="button">
                                    Full size
                                </a>
                                <a href="<?= $file_full_url ?>" download="<?= $file_download_name ?>" class="button">
                                    Download
                                </a>
                            </div>
                        </div>
                        <div class="content column file-preview">
                            <?php html_file_full($file); ?>
                        </div>

                    </section>

                    <div class="font-small row right wrap justify-end gap-8 align-bottom">
                        <p title="<?= $file->size ?>B"><?= $file_size_formatted ?></p>
                        <p><?= $file->mime ?> &#40;<?= $file->extension ?>&#41;</p>
                        <?php if (isset($file_resolution)): ?>
                            <p><?= $file_resolution ?></p>
                        <?php endif; ?>
                        <?php if (isset($file->uploaded_at)): ?>
                            <p title="<?= $file->uploaded_at->format('M d, Y @ H:i:s') ?>">Uploaded
                                <?= format_timestamp($file->uploaded_at) ?> ago
                            </p>
                        <?php endif; ?>
                        <?php if ((CONFIG["files"]["showviews"] || (isset($_SESSION['user']) && $_SESSION['user']->role->as_value() > UserRole::Moderator->as_value())) && isset($file->views)): ?>
                            <p><?= $file->views ?> views</p>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($file->description) && !empty(trim($file->description))): ?>
                        <details open>
                            <summary>Note</summary>

                            <?= bbcode_parse($file->description) ?>
                        </details>
                    <?php endif; ?>
                </section>
            </div>
        <?php else: ?>
            <section class="box">
                <div class="tab">
                    <p>What is <?= CONFIG["instance"]["name"] ?>?</p>
                </div>
                <div class="content">
                    <p>
                        <?= CONFIG["instance"]["name"] ?> is a simple, free and anonymous file sharing site.
                        We do not store anything other than the files you upload.
                        They are stored <b>publicly</b> until the heat death of the universe occurs or you hit the DELETE
                        button.
                        Users do not need an account to start uploading.
                        <br><br>
                        Click the button below and share the files with your friends today!
                    </p>
                </div>
            </section>

            <section class="box column" id="form-box">
                <div class="tab">
                    <p>Form Upload</p>
                </div>
                <div class="tab-category tabs" id="form-upload-tabs" style="display: none;">
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
                            accept="<?= implode(', ', array_unique(array_values(CONFIG["upload"]["acceptedmimetypes"]))) ?>"
                            multiple id="form-file">

                        <div class="column gap-8" id="form-upload-wrapper">
                            <button type="button" style="display: none;">
                                <h1>Click, drop, or paste files here</h1>
                            </button>
                            <?php if (CONFIG["externalupload"]["enable"]): ?>
                                <div class="row gap-8">
                                    <p>URL:</p>
                                    <div class="column grow">
                                        <input type="url" name="url" id="form-url"
                                            placeholder="Instagram, YouTube and other links">
                                        <ul class="no-style row gap-8 font-small" style="list-style:none">
                                            <li>
                                                <p>Max duration: <b><?= CONFIG["externalupload"]["maxduration"] / 60 ?>
                                                        minutes</b></p>
                                            </li>
                                            <li><a href="https://github.com/yt-dlp/yt-dlp/blob/master/supportedsites.md"
                                                    target="_blank">Supported
                                                    platforms</a></li>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <ul class="no-style row gap-8 font-small" style="list-style:none">
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

                        <div class="column gap-8">
                            <p class="remove-script">Details:</p>
                            <hr class="remove-script">

                            <div id="form-upload-options">
                                <div class="grid grid-3 gap-8">
                                    <fieldset>
                                        <legend>General</legend>

                                        <?php if (CONFIG["upload"]["customid"]): ?>
                                            <label for="id">File ID:</label>
                                            <input type="text" name="id" id="id" placeholder="Leave empty for random ID"
                                                maxlength="<?= CONFIG["upload"]["customidlength"] ?>">
                                        <?php endif; ?>

                                        <label for="password">Password<span class="help"
                                                title="For file deletion">[?]</span>:</label>
                                        <input type="text" name="password" id="password"
                                            placeholder="Leave empty if you want the file to be non-deletable"
                                            value="<?= generate_random_char_sequence(CONFIG["upload"]["idcharacters"], CONFIG["files"]["deletionkeylength"]) ?>">
                                    </fieldset>

                                    <fieldset>
                                        <legend>File accessability</legend>

                                        <label for="visibility">Visibility:</label>
                                        <select name="visibility" id="visibility">
                                            <option value="1" <?= CONFIG['files']['defaultvisibility'] >= 1 ? 'selected' : '' ?>>Public</option>
                                            <option value="0" <?= CONFIG['files']['defaultvisibility'] === 0 ? 'selected' : '' ?>>Unlisted</option>
                                        </select>
                                        <ul class="no-style hint">
                                            <li><b>Public</b> makes the file via the "Suprise Me" feature or file catalog.
                                            </li>
                                            <li><b>Unlisted</b> makes the file accessible only via a link.</li>
                                        </ul>

                                        <?php if (!empty(CONFIG["upload"]["expiration"])): ?>
                                            <label for="expires_in">File expiration:</label>
                                            <select name="expires_in" id="expires_in">
                                                <?php foreach (CONFIG["upload"]["expiration"] as $v => $n): ?>
                                                    <option value="<?= $v ?>">
                                                        <?= $n ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </fieldset>

                                    <fieldset>
                                        <legend>Miscellaneous</legend>

                                        <label for="preserve_original_name">Preserve original filename:</label>
                                        <input type="checkbox" name="preserve_original_name" id="preserve_original_name"
                                            value="1">

                                        <?php if (CONFIG["upload"]["stripexif"]): ?>
                                            <label for="strip_exif_data">Strip EXIF data:</label>
                                            <input type="checkbox" name="strip_exif_data" id="strip_exif_data" value="1"
                                                checked>
                                        <?php endif; ?>

                                        <?php if (CONFIG["upload"]["removeletterboxes"]): ?>
                                            <label for="remove_letterbox">
                                                Remove letterboxing<span class="help"
                                                    title="Removes black bars from the video, may be inaccurate, and only applies to videos">[?]</span>:
                                            </label>
                                            <input type="checkbox" name="remove_letterbox" id="remove_letterbox" value="1">
                                        <?php endif; ?>

                                        <label for="convert_file">Convert the file to a more common format<a class="help"
                                                href="/uploaders.php#file-conversion">[more...]</a>:</label>
                                        <input type="checkbox" name="convert_file" id="convert_file" value="1" checked>
                                    </fieldset>
                                </div>

                                <?php if (CONFIG['files']['description']): ?>
                                    <fieldset class="column">
                                        <legend>Description</legend>
                                        <textarea class="grow" name="description" placeholder="Describe the file..."></textarea>
                                    </fieldset>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="fancy">Upload</button>
                    </form>
                </div>
                </div>
            </section>

            <section class="box column" style="display:none">
                <div class="tab-category tabs" id="file-tabs">
                    <div class="tab" id="uploaded-files-tab">
                        <button class="transparent">
                            <p>Uploaded files<span title="Your file ownership is stored locally."
                                    style="cursor:help">*</span></p>
                        </button>
                    </div>
                    <div class="tab" id="favorite-files-tab">
                        <button class="transparent">
                            <p>Favorites<span title="Favorite files are stored locally." style="cursor:help">*</span>
                            </p>
                        </button>
                    </div>
                </div>
                <div class="content grid grid-3 gap-8" id="uploaded-files">
                </div>
                <div class="content grid grid-3 gap-8" id="favorite-files" style="display: none;">
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php isset($file) ? html_mini_footer() : html_footer(); ?>
</body>

<?php if ($file && $file->mime == 'application/x-shockwave-flash' && !empty(CONFIG["driver"]["ruffle"])): ?>
    <script src="<?= CONFIG["driver"]["ruffle"] ?>"></script>
<?php endif; ?>

<?php if ($file): ?>
    <script>
        const fileTabButtons = document.getElementById('file-tab-buttons');
        fileTabButtons.innerHTML += `<button onclick="navigator.clipboard.writeText('${window.location.href}')">Copy URL</button>`;
    </script>
    <script src="/static/scripts/favorites.js"></script>
    <script src="/static/scripts/player.js"></script>
    <script>
        // adding deletion button
        if (document.getElementById("file-deletion-button") === null) {
            const files = JSON.parse(localStorage.getItem('uploaded_files') ?? '[]');
            const file = files.find((x) => x.id === '<?= $file->id ?>');
            if (file && file.urls && file.urls.deletion_url) {
                fileTabButtons.innerHTML = `<a href='${file.urls.deletion_url}' class="button">Delete</a>` + fileTabButtons.innerHTML;
            }
        }
    </script>
<?php elseif (!$file): ?>
    <script>
        const formTabs = document.getElementById('form-upload-tabs');
        const thumbnailPathPrefix = "<?= CONFIG["thumbnails"]["url"] ?>";
    </script>
    <script src="/static/scripts/audiorecorder.js"></script>
    <script src="/static/scripts/options.js"></script>
    <script src="/static/scripts/tabs.js"></script>
    <script src="/static/scripts/upload.js"></script>
    <script src="/static/scripts/favorites.js"></script>
    <script src="/static/scripts/form.js"></script>
    <script>
        window.onload = () => {
            initOptions();
        };

        document.querySelectorAll(".remove-script").forEach((x) => {
            x.remove();
        });

        formTabs.style.display = 'flex';

        document.querySelector('#form-box>.tab').remove();

        const formDetails = document.getElementById('form-upload-options');

        document.getElementById('form-text-upload').style.display = 'none';

        const uploadedFiles = document.getElementById('uploaded-files');
        const fileUploadWrapper = document.querySelector('#form-upload-wrapper>button');
        fileUploadWrapper.style.display = 'block';

        let files = [];

        const formUpload = document.getElementById('form-upload');
        formUpload.addEventListener('submit', (event) => {
            event.preventDefault();
            displayTab('file-tabs', 'uploaded-files');
            if (files.length > 0) {
                for (const file of files) {
                    const form = new FormData(formUpload);
                    form.set("file", file);
                    form.delete("paste");
                    form.delete("url");
                    uploadData(form);
                }
                files = [];
            } else {
                const form = new FormData(formUpload);
                form.delete("file");
                uploadData(form);
            }
            files = [];

            fileUploadWrapper.innerHTML = '<h1>Click, drop, or paste files here</h1>';
            setFormDetailsVisiblity(false);
            showFile(null);
            fileUploadWrapper.style.display = 'block';
            fileURL.value = '';

            document.querySelector('#uploaded-files').parentElement.style.display = 'grid';
        });

        const fileURLWrapper = document.querySelector('#form-upload-wrapper>div');
        const fileURL = document.getElementById('form-url');
        fileURL.addEventListener('keyup', () => {
            fileUploadWrapper.style.display = fileURL.value.length == 0 ? 'block' : 'none';
            setFormDetailsVisiblity(fileURL.value.length > 0);
        });

        const textArea = document.querySelector('#form-text-upload>textarea');
        textArea.addEventListener('keyup', () => {
            setFormDetailsVisiblity(textArea.value.length > 0);
        });

        const formSubmitButton = document.querySelector('#form-upload button[type=submit]');

        const formFile = document.getElementById('form-file');
        formFile.style.display = 'none';
        formFile.addEventListener("change", (e) => {
            files = [];
            for (const file of e.target.files) {
                files.push(file);
            }
            showFile(files);
        });

        fileUploadWrapper.addEventListener("click", () => formFile.click());

        // ---------------------
        // DRAG AND DROP FEATURE
        // ---------------------
        fileUploadWrapper.addEventListener("drop", (e) => {
            e.preventDefault();
            files = [];
            if (e.dataTransfer.items) {
                for (const item of e.dataTransfer.items) {
                    if (item.kind === "file") {
                        file = item.getAsFile();
                        files.push(file);
                    }
                }
                showFile(files);
            }
        });
        fileUploadWrapper.addEventListener("dragover", (e) => {
            e.preventDefault();
            fileUploadWrapper.innerHTML = '<h1>Drop files here</h1>';
            fileURLWrapper.style.display = 'none';
        });
        fileUploadWrapper.addEventListener("dragleave", (e) => {
            showFile(files);
        });

        setFormDetailsVisiblity(false);
        const uploadedFileCount = getUploadedFiles().length;
        const favoriteFileCount = getFavoriteFiles().length;
        if (uploadedFileCount > 0) {
            document.querySelector('#uploaded-files').parentElement.style.display = 'grid';
            displayTab('file-tabs', 'uploaded-files');
        }

        if (favoriteFileCount > 0) {
            document.querySelector('#favorite-files').parentElement.style.display = 'grid';
            if (uploadedFileCount == 0) {
                displayTab('file-tabs', 'favorite-files');
            }
        }
    </script>
<?php endif; ?>

</html>