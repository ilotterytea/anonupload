<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";

$theme = $_COOKIE['theme'] ?? CONFIG['instance']['defaultstyle'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = time() + 86400 * 30 * 180;

    $keys = ["theme", "doomscrolling", "noautoplay", "noloop"];

    foreach ($_POST as $k => $v) {
        if (
            !in_array($k, $keys)
            ||
            ($k === 'theme' && $v !== 'default' && !in_array($_POST['theme'], THEME_LIST, true))
        ) {
            continue;
        }

        if ($v === 'default') {
            setcookie($k, '', time() - 1000, '/');
            continue;
        }

        setcookie($k, $v, $t, "/");
    }

    foreach ($keys as $k) {
        if (!array_key_exists($k, $_POST)) {
            setcookie($k, '', time() - 1000, '/');
        }
    }

    generate_alert("/preferences", "Updated!");
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("preferences"); ?>
</head>

<body>
    <header>
        <?php html_header() ?>
        <h2>preferences</h2>
        <?php display_alert() ?>
    </header>
    <main>
        <form autocomplete="off" method="post" class="preferences">
            <fieldset>
                <legend>general</legend>

                <?php if (!empty(THEME_LIST)): ?>
                    <label for="theme">theme:</label>
                    <select name="theme" id="theme">
                        <option value="default" <?= 'default' === $theme ? 'selected' : '' ?>>Default</option>
                        <?php foreach (THEME_LIST as $name): ?>
                            <option value="<?= $name ?>" <?= $name === $theme ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </fieldset>

            <fieldset>
                <legend>files</legend>

                <input type="checkbox" name="noloop" id="noloop" value="1" <?= isset($_COOKIE['noloop']) ? 'checked' : '' ?>>
                <label for="noloop">disable video looping</label>

                <br>

                <input type="checkbox" name="noautoplay" id="noautoplay" value="1" <?= isset($_COOKIE['noautoplay']) ? 'checked' : '' ?>>
                <label for="noautoplay">disable autoplay</label>
            </fieldset>
            <div>
                <button type="reset">reset</button>
                <button type="submit">save</button>
            </div>
        </form>
    </main>
    <footer>
        <?php html_footer() ?>
    </footer>
</body>

<script>
    window.addEventListener("load", () => {
        const form = document.querySelector("form");
        if (!form) return;

        form.addEventListener("reset", (e) => {
            e.preventDefault();
            document.getElementById("noloop").checked = false;
            document.getElementById("noautoplay").checked = false;
            document.getElementById("theme").checked = false;

            const themeSelector = document.getElementById("theme");
            themeSelector.value = "<?= CONFIG['instance']['defaultstyle'] ?>";
            themeSelector.dispatchEvent(new Event("change", { bubbles: true }));
        });
    });
</script>

</html>