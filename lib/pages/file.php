<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils.php';

if (IS_JSON_REQUEST) {
    send_json_response($post);
}

$single_attachment = $post->single_attachment();
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head($post->id); ?>
</head>

<body>
    <section class="post-preview">
        <section class="disclaimer">
            <?php html_legal(); ?>
            <p>
                All trademarks and copyrights belong to their respective owners.
                The uploader is responsible for any content shared here.
            </p>
        </section>

        <section class="feed" feed-id="<?= $post->id ?>" feed-url="<?= $post->url() ?>">
            <?php foreach ($post->attachments as $file): ?>
                <div class="preview">
                    <?php html_file_full($file); ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="feed-metadata">
            <section class="file-metadata">
                <div class="left">
                    <div class="icon">
                        <?= html_mini_icon(); ?>
                    </div>
                    <div class="metadata">
                        <?php if ($single_attachment): ?>
                            <p>
                                <span id="post-id"><?= $post->id ?></span>.<span
                                    id="file-extension"><?= $single_attachment->extension ?></span>
                                (<span id="file-mime"><?= $single_attachment->mime ?></span>)
                            </p>
                        <?php else: ?>
                            <p>
                                <span id="post-id"><?= $post->id ?></span>
                                (<?= count($post->attachments) ?> attachments)
                            </p>
                        <?php endif; ?>
                        <p id="post-timestamp" timestamp="<?= $post->uploaded_at->getTimestamp() ?>">uploaded
                            <?= format_timestamp($post->uploaded_at) ?> ago
                        </p>
                        <p style="display:none" id="post-url"><?= $post->url() ?></p>
                    </div>
                </div>
                <div>
                    <button class="hidden feed-description-opener"> description omitted. click to expand.</button>
                </div>
                <div class="control-buttons" id="control-buttons">
                    <?php if (isset($_GET['random'])): ?>
                        <a href="/?random" class="button">
                            <img src="/static/img/icons/reroll.png" alt="re-roll" title="re-roll" />
                        </a>
                    <?php endif; ?>

                    <a href="<?= $single_attachment?->raw_url() ?>" download="<?= $file_name ?>"
                        class="download-button button">
                        <img src="/static/img/icons/download.png" alt="download" title="download file" />
                    </a>
                    <a href="<?= $single_attachment?->raw_url() ?>" class="full-size-button button" target="_blank">
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

            <?php if (isset($post->description)): ?>
                <section class="feed-description">
                    <?= $post->description ?>
                </section>
            <?php endif; ?>
        </section>
    </section>
</body>

<?php if (isset($post->description)): ?>
    <script>
        window.addEventListener("load", () => {
            const button = document.querySelector(".feed-description-opener");
            const description = document.querySelector(".feed-description");
            if (!button || !description) return;

            description.classList.add("hidden");
            button.classList.remove("hidden");

            button.addEventListener("click", () => {
                description.classList.remove("hidden");
                button.remove();
            });

            // counting description lines
            const lines = description.textContent.trim().split("\n");
            button.textContent = `${lines.length} line(s) omitted. click to expand.`;
        });
    </script>
<?php endif; ?>

<script src="/static/scripts/player.js"></script>
<script src="/static/scripts/favorites.js"></script>
<script src="/static/scripts/file.js"></script>
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

    function updateControlButtons(file) {
        const downloadButton = document.querySelector(".download-button");
        const fullsizeButton = document.querySelector(".full-size-button");
        if (!downloadButton || !fullsizeButton) return;

        const url = file.getAttribute("file-raw-url");
        downloadButton.href = url;
        fullsizeButton.href = url;
    }

    window.addEventListener("load", () => {
        const buttons = document.getElementById("control-buttons");
        const feed = document.querySelector(".feed");
        const files = document.querySelectorAll(".feed .file-preview");
        if (!buttons || !feed || !files) return;

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const isIntersecting = entry.isIntersecting > 0.9;
                if (isIntersecting) updateControlButtons(entry.target);

                const media = entry.target.querySelector("video, audio");

                if (!media) return;

                const canPause =
                    typeof media.pause === "function" &&
                    typeof media.play === "function";

                if (!canPause) return;

                if (isIntersecting) {
                    if (media.dataset.autoPaused === "true") {
                        media.play().catch(() => { });
                        delete media.dataset.autoPaused;
                    }
                } else {
                    if (!media.paused) {
                        media.dataset.autoPaused = "true";
                        media.pause();
                    }
                }
            });
        },
            {
                threshold: [0, 0.9]
            }
        );

        files.forEach(element => {
            observer.observe(element);
        });
    });

    window.addEventListener("load", () => {
        const feed = document.querySelector(".feed");
        const files = document.querySelectorAll(".feed>.preview");
        const buttons = document.getElementById("control-buttons");
        if (!buttons) return;

        const file = {
            id: feed.getAttribute("feed-id"),
            urls: {
                download_url: feed.getAttribute("feed-url")
            }
        };

        if (files.length === 1) {
            const firstFile = files[0].querySelector(".file-preview");
            file.mime = firstFile.getAttribute("file-mime");
            file.size = firstFile.getAttribute("file-size");
            file.extension = firstFile.getAttribute("file-ext");
            file.urls.thumbnail_url = firstFile.getAttribute("file-thumb-url");
        } else {
            file.count = files.length;
        }

        // -- copy button
        if (navigator.clipboard) {
            const icon = document.createElement("img");
            icon.src = '/static/img/icons/copy.png';
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
        const timestampElement = document.getElementById("post-timestamp");
        if (!timestampElement) return;

        const timestamp = timestampElement.getAttribute("timestamp");

        setInterval(() => {
            timestampElement.textContent = `uploaded ${formatTimestamp(timestamp)} ago`;
        }, 1000);
    });
</script>

<?php if ($post->is_flash()): ?>
    <script src="<?= CONFIG['driver']['ruffle'] ?>"></script>
<?php endif; ?>

</html>