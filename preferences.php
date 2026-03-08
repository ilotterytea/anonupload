<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

USER->authorize_with_cookie();

$theme = $_COOKIE['theme'] ?? CONFIG['instance']['defaultstyle'];
$doomscrolling = boolval($_COOKIE['doomscrolling'] ?? '0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = time() + 86400 * 30 * 180;
    if (isset($_POST['theme']) && in_array($_POST['theme'], THEME_LIST, true)) {
        $theme = $_POST['theme'];
    }
    setcookie("theme", $theme, $t, "/");

    if (isset($_POST['doomscrolling'])) {
        $doomscrolling = boolval($_POST['doomscrolling']);
        setcookie("doomscrolling", $doomscrolling, $t, "/");
    } else if ($doomscrolling) {
        $doomscrolling = false;
        setcookie("doomscrolling", "", time() - 1000, "/");
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
                <input type="checkbox" name="doomscrolling" id="doomscrolling" value="1" <?= $doomscrolling ? 'checked' : '' ?>>
                <label for="doomscrolling">Doomscrolling</label>
                <p class="hint">Replaces the "Surprise Me" button with "Burn My Receptors" which activates<br>
                    an advanced random file page where endless scrolling is available.</p>
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