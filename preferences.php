<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

USER->authorize_with_cookie();

$theme = $_COOKIE['theme'] ?? CONFIG['instance']['defaultstyle'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = time() + 86400 * 30 * 180;

    $keys = ["theme", "doomscrolling", "noautoplay", "noloop"];

    foreach ($_POST as $k => $v) {
        if (
            !in_array($k, $keys)
            ||
            ($k === 'theme' && !in_array($_POST['theme'], THEME_LIST, true))
        ) {
            continue;
        }

        setcookie($k, $v, $t, "/");
    }

    foreach ($keys as $k) {
        if (!array_key_exists($k, $_POST)) {
            setcookie($k, '', time() - 1000, '/');
        }
    }

    generate_alert("/preferences.php", "Updated!");
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("Preferences"); ?>
</head>

<body>
    <?php html_mini_navbar() ?>
    <main>
        <?php display_alert() ?>
        <h1>Preferences</h1>
        <hr>
        <form autocomplete="off" method="post" class="column gap-8">
            <fieldset>
                <legend>General</legend>
                <?php if (!empty(THEME_LIST)): ?>
                    <label for="theme">Theme:</label>
                    <select name="theme" id="theme">
                        <?php foreach (THEME_LIST as $name): ?>
                            <option value="<?= $name ?>" <?= $name === $theme ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </fieldset>
            <fieldset>
                <legend>Files</legend>
                <input type="checkbox" name="doomscrolling" id="doomscrolling" value="1"
                    <?= isset($_COOKIE['doomscrolling']) ? 'checked' : '' ?>>
                <label for="doomscrolling">Doomscrolling</label>
                <p class="hint">Replaces the "Surprise Me" button with "Burn My Receptors" which activates<br>
                    an advanced random file page where endless scrolling is available.</p>

                <input type="checkbox" name="noloop" id="noloop" value="1" <?= isset($_COOKIE['noloop']) ? 'checked' : '' ?>>
                <label for="noloop">Disable video looping</label>

                <br>

                <input type="checkbox" name="noautoplay" id="noautoplay" value="1" <?= isset($_COOKIE['noautoplay']) ? 'checked' : '' ?>>
                <label for="noautoplay">Disable autoplay</label>
            </fieldset>
            <div class="row">
                <div class="grow">
                    <a href="/">&larr; Go back</a>
                </div>
                <div class="row grow gap-8 justify-end">
                    <button type="reset">Reset</button>
                    <button type="submit">Save</button>
                </div>
            </div>
        </form>
    </main>
</body>

</html>