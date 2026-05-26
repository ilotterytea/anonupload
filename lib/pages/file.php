<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils.php';

$file_name = "{$file->id}.{$file->extension}";
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head($file_name); ?>
</head>

<body>
    <section class="file-preview">
        <section class="preview">
            <?php html_file_full($file); ?>
        </section>

        <section class="control-panel">
            <div class="icon">
                <?= html_mini_icon(); ?>
            </div>
            <div class="metadata">
                <p>
                    <span id="file-id"><?= $file->id ?></span>.<span id="file-extension"><?= $file->extension ?></span>
                    (<span id="file-mime"><?= $file->mime ?></span>)
                </p>
                <p>uploaded <?= format_timestamp($file->uploaded_at) ?> ago</p>
                <p style="display:none" id="file-size"><?= $file->size ?></p>
                <p style="display:none" id="file-raw-url"><?= $file->url ?></p>
                <p style="display:none" id="file-url"><?= "/{$file->id}.{$file->extension}" ?></p>
            </div>
            <div class="control-buttons" id="control-buttons">
                <a href="<?= $file->url ?>" download="<?= $file_name ?>" class="button">
                    <img src="/static/img/icons/download.png" alt="download" title="download file" />
                </a>
                <a href="<?= $file->url ?>" class="button" target="_blank">
                    <img src="/static/img/icons/fullsize.png" alt="full size" title="open in full size" />
                </a>
            </div>
        </section>
    </section>
</body>

<script src="/static/scripts/favorites.js"></script>
<script>
    function setFavoriteIcon(file, btn, goodIcon, badIcon) {
        while (btn.firstChild) btn.firstChild.remove();
        const good = isFavoriteFile(file);
        if (good) {
            btn.append(goodIcon);
        } else {
            btn.append(badIcon);
        }
        return good;
    }

    window.addEventListener("load", () => {
        const buttons = document.getElementById("control-buttons");
        if (!buttons) return;

        const file = {
            id: document.getElementById("file-id").textContent,
            mime: document.getElementById("file-mime").textContent,
            extension: document.getElementById("file-extension").textContent,
            size: document.getElementById("file-size").textContent,
            urls: {
                download_url: document.getElementById("file-url").textContent
            }
        };

        const goodIcon = document.createElement("img");
        goodIcon.src = '/static/img/icons/star.png';
        goodIcon.alt = 'unfavorite';
        goodIcon.title = 'unfavorite this file';

        const badIcon = document.createElement("img");
        badIcon.src = '/static/img/icons/star-gray.png';
        badIcon.alt = 'favorite';
        badIcon.title = 'favorite this file';

        const favoriteButton = document.createElement("button");
        favoriteButton.classList.add("button");
        setFavoriteIcon(file, favoriteButton, goodIcon, badIcon);
        favoriteButton.addEventListener("click", () => {
            if (isFavoriteFile(file)) {
                removeFavoriteFile(file);
            } else {
                addFavoriteFile(file);
            }

            setFavoriteIcon(file, favoriteButton, goodIcon, badIcon);
        });
        buttons.append(favoriteButton);
    });
</script>

</html>