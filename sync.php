<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/thumbnails.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/registry.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['data'], $data['iv'], $data['salt'], $data['keyid'])) {
        send_json_response(null, 'Malformed request.', 400);
    }

    $id = trim($data['keyid']);
    unset($data['keyid']);

    if (strlen($id) !== 64) {
        send_json_response(null, 'Malformed storage ID', 400);
    }

    try {
        $exists = METASTORAGE->has_storage_data($id);

        if (!METASTORAGE->put_storage_data($id, $data)) {
            send_json_response(null, 'failed to save storage data', 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        send_json_response(null, 'Failed to save data', 500);
    }

    send_json_response(['id' => $id, 'rewritten' => $exists], null, 200);
}

if (CLIENT_REQUIRES_JSON && isset($_GET['id'])) {
    $id = trim($_GET['id']);
    try {
        $data = METASTORAGE->get_storage_data($id);
        if ($data === null) {
            send_json_response(null, 'Not found', 404);
        }
        send_json_response($data, null, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        send_json_response(null, 'Failed to get storage data', 500);
    }
}

?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("data synchronization"); ?>
</head>

<body>
    <header>
        <?php html_header(); ?>
        <h2>data synchronization</h2>
        <p>sync your file upload history and favorites across devices</p>
        <p><b>make sure your code is long enough!</b></p>
        <p class="error shake"><b><u>anyone who obtains your code can modify your data!!!</u></b></p>
    </header>
    <main>
        <noscript>JavaScript is required for data synchronization.</noscript>

        <form id="sync-data">
            <div>
                <input type="text" autocapitalize="off" autocorrect="off" autocomplete="off" id="code"
                    placeholder="enter the code...">
                <div class="buttons">
                    <button type="submit">save</button>
                    <button type="reset">load</button>
                </div>
            </div>
            <p id="status"></p>
        </form>
    </main>
    <footer>
        <?php html_footer(); ?>
        <?php html_legal(); ?>
        <?php html_motd(); ?>
    </footer>
</body>

<script src="/static/scripts/sync.js"></script>
<script>
    window.addEventListener("load", () => {
        const form = document.getElementById("sync-data");
        const codeInput = document.getElementById("code");
        const status = document.getElementById("status");
        if (!isSyncAvailable() || !form || !codeInput || !status) return;

        form.style.display = 'flex';

        // -- saving the data
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            status.classList.remove("error");
            status.textContent = 'saving...';
            const code = codeInput.value;
            const id = await hashSyncCode(code);

            const data = {};

            Object.entries(localStorage).map(entry => {
                data[entry[0]] = JSON.parse(entry[1]);
            });

            status.textContent = 'encrypting...';
            const body = await encryptJson(data, code);
            body.keyid = id;

            fetch('/sync', {
                method: 'POST',
                body: JSON.stringify(body),
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            }).then((r) => {
                if (r.status !== 200 && r.headers.get("Content-Type") !== 'application/json') {
                    return Promise.reject(`${r.status} (${r.statusText})`);
                }
                return r.json();
            }).then((j) => {
                if (j.status_code !== 200) {
                    return Promise.reject(`${j.message} (${j.status_code})`);
                }

                status.textContent = 'saved!';
            }).catch((err) => {
                status.classList.add("error");
                status.textContent = err;
            });
        });

        // -- loading the data
        form.addEventListener("reset", async (e) => {
            e.preventDefault();
            const replace = confirm("By confirming, you will overwrite the current data with the incoming data.");
            if (!replace) {
                return;
            }

            status.classList.remove("error");
            status.textContent = 'loading...';
            const code = codeInput.value;
            const id = await hashSyncCode(code);

            fetch(`/sync?id=${id}`, {
                headers: {
                    'Accept': 'application/json'
                }
            }).then((r) => {
                if (r.status !== 200 && r.headers.get("Content-Type") !== 'application/json') {
                    return Promise.reject(`${r.status} (${r.statusText})`);
                }
                return r.json();
            }).then(async (j) => {
                if (j.status_code !== 200) {
                    return Promise.reject(`${j.message} (${j.status_code})`);
                }

                const data = await decryptJson(j.data, code);

                Object.entries(data).map((entry) => {
                    localStorage.setItem(entry[0], JSON.stringify(entry[1]));
                });

                status.textContent = 'loaded!';
            }).catch((err) => {
                status.classList.add("error");
                status.textContent = err;
            });
        });
    });
</script>

</html>