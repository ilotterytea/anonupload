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
                <p id="file-timestamp" timestamp="<?= $file->uploaded_at->getTimestamp() ?>">uploaded
                    <?= format_timestamp($file->uploaded_at) ?> ago
                </p>
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
                <?php if (CONFIG['report']['mail']): ?>
                    <a href="<?= sprintf('mailto:%s?subject=%s', CONFIG['report']['mail'], rawurlencode("File Report - $file_name")) ?>"
                        class="button">
                        <img src="/static/img/icons/flag.png" alt="report" title="report this file">
                    </a>
                <?php endif; ?>
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

        // -- copy button
        if (navigator.clipboard) {
            const icon = document.createElement("img");
            icon.src = '/static/img/icons/link.png';
            icon.alt = 'copy link';
            icon.title = 'copy link';

            const button = document.createElement("button");
            button.classList.add("button");
            button.append(icon);
            button.addEventListener("click", () => navigator.clipboard.writeText(file.urls.download_url));
            buttons.prepend(button);
        }

        // -- favorite button
        {
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
        }
    });

    // live timestamp counting
    function formatTimestamp(ts) {
        const diff = Math.floor(Date.now() - ts * 1000);

        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        const months = Math.floor(days / 30);
        const years = Math.floor(days / 365);

        if (years === 0 && months === 0 && days === 0 && hours === 0 && minutes === 0) {
            return `${seconds} second${seconds !== 1 ? "s" : ""}`;
        } else if (years === 0 && months === 0 && days === 0 && hours === 0) {
            return `${minutes} minute${minutes !== 1 ? "s" : ""}`;
        } else if (years === 0 && months === 0 && days === 0) {
            return `${hours} hour${hours !== 1 ? "s" : ""}`;
        } else if (years === 0 && months === 0) {
            return `${days} day${days !== 1 ? "s" : ""}`;
        } else if (years === 0) {
            return `${months} month${months !== 1 ? "s" : ""}`;
        }

        return `${years} year${years !== 1 ? "s" : ""}`;
    }

    window.addEventListener("load", () => {
        const timestampElement = document.getElementById("file-timestamp");
        if (!timestampElement) return;

        const timestamp = timestampElement.getAttribute("timestamp");

        setInterval(() => {
            timestampElement.textContent = `uploaded ${formatTimestamp(timestamp)} ago`;
        }, 1000);
    });
</script>

</html>