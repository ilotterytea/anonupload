<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("file upload history"); ?>
</head>

<body>
    <header>
        <?php html_big_navbar(); ?>
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

        const files = JSON.parse(localStorage.getItem("uploaded_files") ?? '[]');

        for (const file of files) {
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