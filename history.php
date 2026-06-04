<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/thumbnails.php';
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("file upload history"); ?>
</head>

<body>
    <header>
        <?php html_header(); ?>
        <h2>file upload history</h2>
        <p>every file you uploaded from this device is stored in local storage</p>
    </header>
    <main>
        <noscript>JavaScript is required for file upload history.</noscript>
        <section class="item-list" id="file-history"></section>
    </main>
    <footer>
        <?php html_footer(); ?>
    </footer>
</body>

<script src="/static/scripts/file.js"></script>
<script>
    window.addEventListener("load", () => {
        const fileHistory = document.getElementById("file-history");
        if (!fileHistory) {
            return;
        }

        const files = getUploadedFiles();

        for (const file of files) {
            <?php if (THUMBNAILER !== null): ?>
                if (!file.urls) file.urls = {};
                if (!file.urls.thumbnail_url) file.urls.thumbnail_url = `<?= THUMBNAILER->get_thumbnail_root() ?>/${file.id}.<?= THUMBNAILER->get_thumbnail_extension() ?>`;
            <?php endif; ?>
            fileHistory.append(createFile(file));
        }

        if (files.length == 0) {
            const message = document.createElement("p");
            message.classList.add("message");
            message.innerHTML = "you haven't uploaded any files yet. <a href='/'>maybe it's time to fix that? :)</a>";
            fileHistory.append(message);
        }
    });
</script>

</html>