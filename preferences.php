<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/utils.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/user.php";

USER->authorize_with_cookie();

$themes = array_map(fn($x) => basename($x), glob("{$_SERVER['DOCUMENT_ROOT']}/static/themes/*", GLOB_ONLYDIR));
$theme = $_COOKIE['theme'] ?? CONFIG['instance']['defaultstyle'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['theme']) && in_array($_POST['theme'], $themes, true)) {
        $theme = $_POST['theme'];
    }
    setcookie("theme", $theme, time() + 86400 * 30 * 180, "/");

    generate_alert("/preferences.php", "Updated!");
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("Preferences"); ?>
</head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>Preferences</h1>
        <hr>
        <form autocomplete="off" method="post" class="column gap-8">
            <fieldset>
                <legend>General</legend>
                <?php if (!empty($themes)): ?>
                    <label for="theme">Theme:</label>
                    <select name="theme" id="theme">
                        <?php foreach ($themes as $name): ?>
                            <option value="<?= $name ?>" <?= $name === $theme ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
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