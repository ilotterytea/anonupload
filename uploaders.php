<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
?>
<!DOCTYPE html>
<html>

<head><?php html_head("uploaders"); ?></head>

<body>
    <header>
        <?php html_header() ?>
        <h2>file uploaders</h2>
        <p>configure your software to work with
            <?= CONFIG["instance"]["name"] ?>
        </p>
    </header>
    <main>
        <section class="software-uploaders">
            <div class="box">
                <div class="tab">
                    <h2>ShareX</h2>
                    <p class="small-font">(Destinations &rarr; Custom uploader settings &rarr; New)</p>
                </div>
                <div class="content">
                    <table class="vertical">
                        <tr>
                            <th>Name:</th>
                            <td><code><?= CONFIG["instance"]["name"] ?></code></td>
                        </tr>
                        <tr>
                            <th>Request URL:</th>
                            <td><code class="copy"><?= CONFIG["instance"]["url"] ?>/upload</code></td>
                        </tr>
                        <tr>
                            <th>Destination type:</th>
                            <td><code>Image uploader</code></td>
                        </tr>
                        <tr>
                            <th>Method:</th>
                            <td><code>POST</code></td>
                        </tr>
                        <tr>
                            <th>Body:</th>
                            <td><code>Form data (multipart/form-data)</code></td>
                        </tr>
                        <tr>
                            <th>Headers:</th>
                            <td><code>Accept: application/json</code></td>
                        </tr>
                        <tr>
                            <th>File form name:</th>
                            <td><code class="copy">file</code></td>
                        </tr>
                        <tr>
                            <th>URL:</th>
                            <td><code class="copy">{json:data.urls.download_url}</code></td>
                        </tr>
                        <tr>
                            <th>Deletion URL:</th>
                            <td><code class="copy">{json:data.urls.deletion_url}</code></td>
                        </tr>
                    </table>
                    <p>Then, select it via <b>Destinations &rarr; Image uploader &rarr; Custom image
                            uploader</b></p>
                </div>
            </div>

            <div class="box">
                <div class="tab">
                    <h2>Chatterino/DankChat</h2>
                    <p class="small-font"><b>Chatterino</b>: Settings &rarr; External tools &rarr; Image Uploader
                    </p>
                    <p class="small-font"><b>DankChat</b>: &#8942; &rarr; Settings &rarr; Tools &rarr; Configure
                        uploader
                    </p>
                </div>
                <div class="content">
                    <table class="vertical">
                        <tr>
                            <th>URL:</th>
                            <td><code class="copy"><?= CONFIG["instance"]["url"] ?>/upload</code></td>
                        </tr>
                        <tr>
                            <th>Form field:</th>
                            <td><code class="copy">file</code></td>
                        </tr>
                        <tr>
                            <th>Extra headers:</th>
                            <td><code class="copy">Accept: application/json</code></td>
                        </tr>
                        <tr>
                            <th>Image link:</th>
                            <td><code class="copy">{data.urls.download_url}</code></td>
                        </tr>
                        <tr>
                            <th>Deletion link:</th>
                            <td><code class="copy">{data.urls.deletion_url}</code></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="box">
                <div class="tab">
                    <h2>API</h2>
                </div>
                <div class="content">
                    <div class="column">
                        <h3>Endpoint</h3>
                        <hr>
                        <p><code>POST <span class="copy"><?= CONFIG["instance"]["url"] ?>/upload</span></code></p>
                    </div>

                    <div>
                        <h3>Request Format</h3>
                        <hr>
                        <table class="vertical">
                            <tr>
                                <th>Method:</th>
                                <td><code>POST</code></td>
                            </tr>
                            <tr>
                                <th>Content-Type:</th>
                                <td><code>multipart/form-data</code></td>
                            </tr>
                            <tr>
                                <th>Headers:</th>
                                <td><code>Accept: application/json</code></td>
                            </tr>
                            <tr>
                                <th>File field:</th>
                                <td><code>file</code></td>
                            </tr>
                            <tr>
                                <th>Max file size:</th>
                                <td><code><?= get_cfg_var("upload_max_filesize") ?></code></td>
                            </tr>
                        </table>
                    </div>
                </div>
        </section>
    </main>
    <footer>
        <?php html_footer(); ?>
        <?php html_legal(); ?>
        <?php html_motd(); ?>
    </footer>
</body>

<script>
    window.addEventListener("DOMContentLoaded", () => {
        if (navigator.clipboard) {
            const copyButtons = document.querySelectorAll(".copy");
            for (const copyButton of copyButtons) {
                const content = copyButton.innerHTML;
                const button = document.createElement("button");

                button.innerHTML = '<img src="/static/img/icons/copy.png" alt="Copy" />';
                button.addEventListener("click", () => {
                    navigator.clipboard.writeText(content);
                });
                copyButton.parentElement.appendChild(button);
            }
        }
    });
</script>

</html>