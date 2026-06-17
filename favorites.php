<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/thumbnails.php';
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("favorites"); ?>
</head>

<body>
    <header>
        <?php html_header(); ?>
        <h2>favorites</h2>
        <p>all the files you liked</p>
    </header>
    <main>
        <noscript>JavaScript is required for favorite files.</noscript>
        <section class="item-list" id="favorite-files"></section>
    </main>
    <footer>
        <?php html_footer(); ?>
        <?php html_legal(); ?>
        <?php html_motd(); ?>
    </footer>
</body>

<script src="/static/scripts/file.js"></script>
<script src="/static/scripts/favorites.js"></script>
<script>
    function checkEmptyList(list, files) {
        if (files.length > 0) return;
        const message = document.createElement("p");
        message.classList.add("message");
        message.innerHTML = "you don't have any favorites yet. press <button class='button'><img src='/static/img/icons/star-gray.png' alt='favorite' title='favorite this file'></button> next time you see a file!";
        list.append(message);
    }

    window.addEventListener("load", () => {
        const favoriteFiles = document.getElementById("favorite-files");
        if (!favoriteFiles) {
            return;
        }

        const files = JSON.parse(localStorage.getItem("favorite-files") ?? '[]');

        for (let file of files) {
            file.is_favorite = true;
            <?php if (THUMBNAILER !== null): ?>
                if (!file.urls) file.urls = {};
                if (!file.urls.thumbnail_url) file.urls.thumbnail_url = `<?= THUMBNAILER->get_thumbnail_root() ?>/${file.id}.<?= THUMBNAILER->get_thumbnail_extension() ?>`;
            <?php endif; ?>
            favoriteFiles.append(createFile(file));
        }

        checkEmptyList(favoriteFiles, files);
    });
</script>

</html>