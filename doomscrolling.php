<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/file.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

if (!CONFIG["supriseme"]["enable"]) {
    generate_alert('/', 'This feature is not available on this instance.', 403);
}

if (!isset($_COOKIE['doomscrolling'])) {
    generate_alert('/', 'This feature is not enabled in your preferences.', 403);
}
?>
<!DOCTYPE html>
<html>

<head><?php html_head('Doomscrolling'); ?></head>

<body>
    <?php html_mini_navbar('Doomscrolling Mode'); ?>
    <main>
        <noscript>JavaScript is required for doomscrolling</noscript>
        <div class="row grow justify-center">
            <div class="column align-center gap-8" id="feed">
            </div>
        </div>
        <p id="feed-end-text" style="display:none">You've reached the end of the
            <?= CONFIG['instance']['name'] ?> feed. Congratulations,
            I guess...
        </p>
    </main>
    <?php html_mini_footer(); ?>
</body>

<?php if (!empty(CONFIG["driver"]["ruffle"])): ?>
    <script src="<?= CONFIG["driver"]["ruffle"] ?>"></script>
<?php endif; ?>

<script src="/static/scripts/favorites.js"></script>
<script src="/static/scripts/player.js"></script>

<script>
    const uploadedFiles = JSON.parse(localStorage.getItem('uploaded_files') ?? '[]');
    const loadedFiles = new Set();
    let loading = false;
    let failedAttempts = 0;

    function formatTimestamp(input) {
        const dt = input instanceof Date ? input : new Date(input);
        const now = new Date();

        let diffMs = Math.abs(now - dt);

        const seconds = Math.floor(diffMs / 1000) % 60;
        const minutes = Math.floor(diffMs / (1000 * 60)) % 60;
        const hours = Math.floor(diffMs / (1000 * 60 * 60)) % 24;
        const days = Math.floor(diffMs / (1000 * 60 * 60 * 24)) % 30;
        const months = Math.floor(diffMs / (1000 * 60 * 60 * 24 * 30)) % 12;
        const years = Math.floor(diffMs / (1000 * 60 * 60 * 24 * 365));

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
        } else {
            return `${years} year${years !== 1 ? "s" : ""}`;
        }
    }

    function getRandomFile() {
        return fetch("/?random", {
            "headers": {
                "Accept": "application/json"
            },
            "credentials": "same-origin"
        }).then(r => r.json());
    }

    async function getUniqueRandomFile() {
        let attempts = 0;
        while (true) {
            attempts++;
            const file = await getRandomFile();
            const id = file.data.id;

            if (!loadedFiles.has(id)) {
                loadedFiles.add(id);
                return file.data;
            } else if (attempts > 20) {
                failedAttempts++;
                break;
            }
        }
    }

    async function loadRandomFiles(count = 10) {
        if (loading) return;
        loading = true;

        const container = document.getElementById("feed");
        const promises = [];

        for (let i = 0; i < count; i++) {
            promises.push(getUniqueRandomFile());
        }

        const files = await Promise.all(promises);

        files.forEach(file => {
            const fullUrl = `<?= CONFIG['files']['url'] ?>/${file.id}.${file.extension}`;

            const box = document.createElement("section");
            box.classList.add("box");

            const wrapper = document.createElement('section');

            // wrapper
            {
                wrapper.classList.add('file-preview-wrapper');
                let width = 256;
                if (file.metadata && file.metadata.width && file.metadata.width > 256) {
                    width = file.metadata.width;
                }
                wrapper.style.maxWidth = `${width}px`;
                wrapper.appendChild(box);
                container.appendChild(wrapper);
            }

            // tab
            {
                const el = document.createElement("div");
                el.classList.add("tab", "row", "wrap", "gap-8");
                box.appendChild(el);

                // name
                const name = document.createElement("div");
                name.classList.add("grow");
                name.innerHTML = `<p>File ${file.id}.${file.extension}</p>`;
                el.appendChild(name);

                // buttons
                const buttons = document.createElement("div");
                buttons.classList.add("grow", "row", "gap-8", "justify-end", "align-center", "wrap", "file-tab-buttons");
                el.appendChild(buttons);

                // deletion button
                const f = uploadedFiles.find((x) => x.id === file.id);
                if (f && f.urls && f.urls.deletion_url) {
                    const b = document.createElement("a");
                    b.href = f.urls.deletion_url;
                    b.textContent = 'Delete';
                    b.target = '_blank';
                    b.classList.add('button');
                    buttons.appendChild(b);
                }

                <?php if (CONFIG["report"]["enable"]): ?>
                    // report button
                    {
                        const b = document.createElement("a");
                        b.classList.add("button");
                        b.href = `/files/report.php?id=${file.id}.${file.extension}`;
                        b.target = '_blank';
                        b.textContent = 'Report';
                        buttons.appendChild(b);
                    }
                <?php endif; ?>

                // open in new tab button
                {
                    const b = document.createElement("a");
                    b.classList.add("button");
                    b.href = file.urls.download_url;
                    b.target = '_blank';
                    b.textContent = 'Open in New Tab';
                    buttons.appendChild(b);
                }

                // full size
                {
                    const b = document.createElement("a");
                    b.classList.add("button");
                    b.href = fullUrl;
                    b.target = '_blank';
                    b.textContent = 'Full size';
                    buttons.appendChild(b);
                }

                // download
                {
                    const b = document.createElement("a");
                    b.classList.add("button");
                    b.href = fullUrl;
                    b.download = `${file.id}.${file.extension}`;
                    b.textContent = 'Download';
                    buttons.appendChild(b);
                }
            }

            // content
            {
                const el = document.createElement("div");
                el.classList.add("content", "column", "file-preview");
                box.appendChild(el);

                let f = null;

                if (file.mime.startsWith("image/")) {
                    f = document.createElement("img");
                    f.src = fullUrl;
                    f.alt = 'Image file.';
                } else if (file.mime.startsWith("video/")) {
                    f = document.createElement('video');
                    f.setAttribute("controls", "on");
                    <?php if (!isset($_COOKIE['noloop'])): ?>
                    f.setAttribute("loop", "on");
                    <?php endif; ?>
                    f.classList.add("video-playback");
                    const s = document.createElement('source');
                    s.src = fullUrl;
                    s.type = file.mime;
                    f.appendChild(s);
                }
                else if (file.mime.startsWith("audio/")) {
                    f = document.createElement('audio');
                    f.setAttribute("controls", "on");
                    const s = document.createElement('source');
                    s.src = fullUrl;
                    s.type = file.mime;
                    f.appendChild(s);
                }

                if (f === null) {
                    box.parentElement.remove();
                    return;
                }

                el.appendChild(f);
            }

            // metadata
            {
                const metadata = document.createElement("div");
                metadata.classList.add("font-small", "row", "right", "wrap", "justify-end", "gap-8", "align-bottom");
                wrapper.appendChild(metadata);

                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                const factor = Math.floor((file.size.toString().length - 1) / 3);
                const sizeFormatted = (file.size / Math.pow(1024, factor)).toFixed(2) + " " + units[factor];

                metadata.innerHTML = `<p title="${file.size}B">${sizeFormatted}</p>`;
                metadata.innerHTML += `<p>${file.mime} &#40;${file.extension}&#41;</p>`;

                if (file.metadata) {
                    let md = '';
                    if (file.metadata.width && file.metadata.height) {
                        md += `${file.metadata.width}x${file.metadata.height}`;
                    }
                    if (file.metadata.duration) {
                        const d = formatTimestamp(new Date(Date.now() + file.metadata.duration * 1000));
                        md += md.length > 0 ? ` (${d})` : d;
                    }
                    metadata.innerHTML += `<p>${md}</p>`;
                }

                if (file.uploaded_at !== null && file.uploaded_at.date) {
                    const date = new Date(file.uploaded_at.date.replace(" ", "T"));
                    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                        "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                    const M = monthNames[date.getMonth()];
                    const d = date.getDate();
                    const Y = date.getFullYear();
                    const H = String(date.getHours()).padStart(2, "0");
                    const i = String(date.getMinutes()).padStart(2, "0");
                    const s = String(date.getSeconds()).padStart(2, "0");
                    const title = `${M} ${d}, ${Y} @ ${H}:${i}:${s}`;
                    metadata.innerHTML += `<p title="${title}">Uploaded ${formatTimestamp(date)} ago</p>`;
                }

                if (file.views !== null) {
                    metadata.innerHTML += `<p>${file.views} views</p>`;
                }
            }
        });

        loading = false;
    }

    function nearPageEnd() {
        return window.innerHeight + window.scrollY >= document.body.offsetHeight - 800;
    }

    window.addEventListener("scroll", () => {
        if (nearPageEnd() && failedAttempts < 3) {
            loadRandomFiles(10);
        }

        if (failedAttempts >= 3) {
            document.getElementById("feed-end-text").style.display = 'flex';
        }
    });

    window.addEventListener("load", () => {
        loadRandomFiles(10);
    });
</script>

</html>