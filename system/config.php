<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

if (!USER->authorize_with_cookie()) {
    generate_alert('/account/', 'You must be authorized!', 303);
}

if ($_SESSION['user']->role->as_value() < UserRole::Administrator->as_value() && file_exists(CONFIG_FILE_PATH)) {
    generate_alert('/account/', 'You are not allowed to make changes on this page!', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c = CONFIG;

    // setting all checkboxes to false
    foreach ($c as $sk => $sv) {
        foreach ($sv as $k => &$v) {
            if (is_bool($v) && !array_key_exists("{$sk}_$k", $_POST)) {
                $c[$sk][$k] = false;
            }
        }
        unset($v);
    }

    foreach ($_POST as $k => $v) {
        if ($v == '****') {
            continue;
        }

        $parts = explode('_', $k);
        $part_count = count($parts);
        if ($part_count != 2) {
            continue;
        }
        $section = $parts[0];
        $key = $parts[1];

        if (!array_key_exists($section, $c)) {
            $c[$section] = [];
        } else if ($v === 'on') {
            $v = true;
        } else if (is_numeric($v)) {
            $v += 0;
        }

        $c[$section][$key] = $v;
    }

    if (!file_put_contents(CONFIG_FILE_PATH, json_encode($c, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) {
        http_response_code(500);
        exit("Failed to write the configuration file.");
    }

    generate_alert('/system/config.php', 'Saved!', 200);
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("System configuration"); ?>
</head>

<body>
    <?php html_mini_navbar() ?>
    <main>
        <?php display_alert() ?>
        <h1>System configuration</h1>
        <?php if (!file_exists(CONFIG_FILE_PATH)): ?>
            <div class="box alert">
                <p>This message confirms the instance is nearly ready. The configuration below shows the default
                    settings.</p>
            </div>
        <?php endif; ?>
        <hr>
        <form autocomplete="off" method="post" class="column gap-8">
            <div class="wall">
                <fieldset class="block">
                    <legend>Instance</legend>

                    <label for="instance_name">Name:</label>
                    <input type="text" name="instance_name" id="instance_name"
                        value="<?= CONFIG['instance']['name'] ?>">

                    <label for="instance_mirrors">Mirrors:</label>
                    <textarea name="instance_mirrors" id="instance_mirrors"
                        placeholder="One line per mirror. The line should follow this formatting: url=name."><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['instance']['mirrors']), CONFIG['instance']['mirrors'])) ?></textarea>

                    <label for="instance_footerlinks">Footer links:</label>
                    <textarea name="instance_footerlinks" id="instance_footerlinks"
                        placeholder="One line per link. The line should follow this formatting: name=url."><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['instance']['footerlinks']), CONFIG['instance']['footerlinks'])) ?></textarea>

                    <?php if (!empty(THEME_LIST)): ?>
                        <label for="instance_defaultstyle">Default theme:</label>
                        <select name="instance_defaultstyle" id="instance_defaultstyle">
                            <?php foreach (THEME_LIST as $name): ?>
                                <option value="<?= $name ?>">
                                    <?= $name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </fieldset>

                <fieldset class="block">
                    <legend>Storage</legend>

                    <label for="storage_type">Storage type:</label>
                    <select name="storage_type" id="storage_type">
                        <option value="json" <?= CONFIG["storage"]["type"] === "file" ? 'selected' : '' ?>>Files
                            only
                        </option>
                        <option value="json" <?= CONFIG["storage"]["type"] === "json" ? 'selected' : '' ?>>
                            JSON-based
                        </option>
                        <option value="database" <?= CONFIG["storage"]["type"] === "database" ? 'selected' : '' ?>>
                            Database</option>
                    </select>

                    <label for="database_driver">Driver:</label>
                    <input type="text" name="database_driver" id="database_driver"
                        value="<?= CONFIG['database']['driver'] ?>">

                    <label for="database_host">Host:</label>
                    <input type="text" name="database_host" id="database_host"
                        value="<?= CONFIG['database']['host'] ?>">

                    <label for="database_port">Port:</label>
                    <input type="number" name="database_port" id="database_port"
                        value="<?= CONFIG['database']['port'] ?>">

                    <label for="database_name">Name:</label>
                    <input type="text" name="database_name" id="database_name"
                        value="<?= CONFIG['database']['name'] ?>">

                    <label for="database_user">User:</label>
                    <input type="text" name="database_user" id="database_user"
                        value="<?= CONFIG['database']['user'] ?>">

                    <label for="database_pass">Password:</label>
                    <input type="text" name="database_pass" id="database_pass"
                        value="<?= !empty(CONFIG['database']['pass']) ? '****' : '' ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Driver</legend>

                    <label for="driver_ruffle">Ruffle:</label>
                    <input type="text" name="driver_ruffle" id="driver_ruffle"
                        value="<?= CONFIG['driver']['ruffle'] ?>">

                    <label for="driver_chart">Chart.js:</label>
                    <input type="text" name="driver_chart" id="driver_chart" value="<?= CONFIG['driver']['chart'] ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>File catalog</legend>

                    <label for="filecatalog_public">Public access:</label>
                    <input type="checkbox" name="filecatalog_public" id="filecatalog_public" value="on"
                        <?= CONFIG['filecatalog']['public'] ? 'checked' : '' ?>>

                    <label for="filecatalog_limit">Files per page:</label>
                    <input type="number" name="filecatalog_limit" id="filecatalog_limit"
                        value="<?= CONFIG['filecatalog']['limit'] ?>">

                    <label for="filecatalog_includemimetypes">Include only MIME-types:</label>
                    <input type="text" name="filecatalog_includemimetypes" id="filecatalog_includemimetypes"
                        value="<?= implode(' ', CONFIG['filecatalog']['includemimetypes']) ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Random files</legend>

                    <label for="supriseme_enable">Enable:</label>
                    <input type="checkbox" name="supriseme_enable" id="supriseme_enable" value="on"
                        <?= CONFIG['supriseme']['enable'] ? 'checked' : '' ?>>

                    <label for="supriseme_order">File selection order:</label>
                    <input type="text" name="supriseme_order" id="supriseme_order"
                        value="<?= CONFIG['supriseme']['order'] ?>">

                    <label for="supriseme_where">File selection filter:</label>
                    <input type="text" name="supriseme_where" id="supriseme_where"
                        value="<?= CONFIG['supriseme']['where'] ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Files</legend>

                    <label for="files_fancyview">Fancy view:</label>
                    <input type="checkbox" name="files_fancyview" id="files_fancyview" value="on"
                        <?= CONFIG['files']['fancyview'] ? 'checked' : '' ?>>

                    <label for="files_countviews">Count views:</label>
                    <input type="checkbox" name="files_countviews" id="files_countviews" value="on"
                        <?= CONFIG['files']['countviews'] ? 'checked' : '' ?>>

                    <label for="files_showuploadtime">Show upload time:</label>
                    <input type="checkbox" name="files_showuploadtime" id="files_showuploadtime" value="on"
                        <?= CONFIG['files']['showuploadtime'] ? 'checked' : '' ?>>

                    <label for="files_showviews">Show views:</label>
                    <input type="checkbox" name="files_showviews" id="files_showviews" value="on"
                        <?= CONFIG['files']['showviews'] ? 'checked' : '' ?>>

                    <label for="files_displayhtml">Display uploaded HTML-pages:</label>
                    <input type="checkbox" name="files_displayhtml" id="files_displayhtml" value="on"
                        <?= CONFIG['files']['displayhtml'] ? 'checked' : '' ?>>

                    <label for="files_description">Show file descriptions:</label>
                    <input type="checkbox" name="files_description" id="files_description" value="on"
                        <?= CONFIG['files']['description'] ? 'checked' : '' ?>>

                    <label for="files_directory">Save directory:</label>
                    <input type="text" name="files_directory" id="files_directory"
                        value="<?= CONFIG['files']['directory'] ?>">

                    <label for="files_url">File URL prefix:</label>
                    <input type="text" name="files_url" id="files_url" value="<?= CONFIG['files']['url'] ?>">

                    <label for="files_deletion">Allow file deletion:</label>
                    <input type="checkbox" name="files_deletion" id="files_deletion" value="on"
                        <?= CONFIG['files']['deletion'] ? 'checked' : '' ?>>

                    <label for="files_deletionkeylength">Deletion key length:</label>
                    <input type="number" name="files_deletionkeylength" id="files_deletionkeylength"
                        value="<?= CONFIG['files']['deletionkeylength'] ?>">

                    <label for="files_defaultvisibility">Default file visibility:</label>
                    <select name="files_defaultvisibility" id="files_defaultvisibility">
                        <option value="0" <?= CONFIG['files']['defaultvisibility'] === 0 ? 'selected' : '' ?>>Private
                        </option>
                        <option value="1" <?= CONFIG['files']['defaultvisibility'] === 1 ? 'selected' : '' ?>>Public
                        </option>
                        <option value="2" <?= CONFIG['files']['defaultvisibility'] === 2 ? 'selected' : '' ?>>Must pass
                            approval</option>
                    </select>
                </fieldset>

                <fieldset class="block">
                    <legend>File upload</legend>

                    <label for="upload_verifymimetype">Verify MIME-type for files:</label>
                    <input type="checkbox" name="upload_verifymimetype" id="upload_verifymimetype" value="on"
                        <?= CONFIG['upload']['verifymimetype'] ? 'checked' : '' ?>>

                    <label for="upload_stripexif">Strip EXIF information from files:</label>
                    <input type="checkbox" name="upload_stripexif" id="upload_stripexif" value="on"
                        <?= CONFIG['upload']['stripexif'] ? 'checked' : '' ?>>

                    <label for="upload_idcharacters">File ID characters:</label>
                    <input type="text" name="upload_idcharacters" id="upload_idcharacters"
                        value="<?= CONFIG['upload']['idcharacters'] ?>">

                    <label for="upload_idlength">File ID length:</label>
                    <input type="number" name="upload_idlength" id="upload_idlength"
                        value="<?= CONFIG['upload']['idlength'] ?>">

                    <label for="upload_idprefix">File ID prefix:</label>
                    <input type="text" name="upload_idprefix" id="upload_idprefix"
                        value="<?= CONFIG['upload']['idprefix'] ?>">

                    <label for="upload_customid">Allow custom file IDs:</label>
                    <input type="checkbox" name="upload_customid" id="upload_customid" value="on"
                        <?= CONFIG['upload']['customid'] ? 'checked' : '' ?>>

                    <label for="upload_customidregex">Custom file ID regex:</label>
                    <input type="text" name="upload_customidregex" id="upload_customidregex"
                        value="<?= CONFIG['upload']['customidregex'] ?>">

                    <label for="upload_customidlength">Max. custom file ID length:</label>
                    <input type="number" name="upload_customidlength" id="upload_customidlength"
                        value="<?= CONFIG['upload']['customidlength'] ?>">

                    <label for="upload_titlelength">Max. title length:</label>
                    <input type="number" name="upload_titlelength" id="upload_titlelength"
                        value="<?= CONFIG['upload']['titlelength'] ?>">

                    <label for="upload_zipwebapps">Allow web application uploads:</label>
                    <input type="checkbox" name="upload_zipwebapps" id="upload_zipwebapps" value="on"
                        <?= CONFIG['upload']['zipwebapps'] ? 'checked' : '' ?>>

                    <label for="upload_removeletterboxes">Allow letterbox removal:</label>
                    <input type="checkbox" name="upload_removeletterboxes" id="upload_removeletterboxes" value="on"
                        <?= CONFIG['upload']['removeletterboxes'] ? 'checked' : '' ?>>

                    <label for="upload_expiration">File expiration:</label>
                    <textarea name="upload_expiration" id="upload_expiration"
                        placeholder="One expiration per line. Format: timeout=name. For example: ne=never, 14d=2 weeks, 12h=12 hours, 5m=5 minutes"><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['upload']['expiration']), CONFIG['upload']['expiration'])) ?></textarea>

                    <label for="upload_acceptedmimetypes">Accepted file MIME-types:</label>
                    <textarea name="upload_acceptedmimetypes" id="upload_acceptedmimetypes"
                        placeholder="One MIME-type per line. Format: extension=mime. For example: jpg=image/jpeg"><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['upload']['acceptedmimetypes']), CONFIG['upload']['acceptedmimetypes'])) ?></textarea>

                    <label for="upload_convertextensions">Extension conversion:</label>
                    <textarea name="upload_convertextensions" id="upload_convertextensions"
                        placeholder="One MIME-type per line. Format: extension=extension. For example: mkv=mp4"><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['upload']['convertextensions']), CONFIG['upload']['convertextensions'])) ?></textarea>
                </fieldset>

                <fieldset class="block">
                    <legend>External file upload</legend>

                    <label for="externalupload_enable">Enable:</label>
                    <input type="checkbox" name="externalupload_enable" id="externalupload_enable" value="on"
                        <?= CONFIG['externalupload']['enable'] ? 'checked' : '' ?>>

                    <label for="externalupload_maxduration">Max duration:</label>
                    <input type="number" name="externalupload_maxduration" id="externalupload_maxduration"
                        value="<?= CONFIG['externalupload']['maxduration'] ?>">

                    <label for="externalupload_quality">Quality:</label>
                    <input type="text" name="externalupload_quality" id="externalupload_quality"
                        value="<?= CONFIG['externalupload']['quality'] ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Thumbnails</legend>

                    <label for="thumbnails_enable">Enable:</label>
                    <input type="checkbox" name="thumbnails_enable" id="thumbnails_enable" value="on"
                        <?= CONFIG['thumbnails']['enable'] ? 'checked' : '' ?>>

                    <label for="thumbnails_directory">Save directory:</label>
                    <input type="text" name="thumbnails_directory" id="thumbnails_directory"
                        value="<?= CONFIG['thumbnails']['directory'] ?>">

                    <label for="thumbnails_url">Thumbnail URL prefix:</label>
                    <input type="text" name="thumbnails_url" id="thumbnails_url"
                        value="<?= CONFIG['thumbnails']['url'] ?>">

                    <label for="thumbnails_size">Size:</label>

                    <div id="thumbnails_size">
                        <input type="number" name="thumbnails_width" value="<?= CONFIG['thumbnails']['width'] ?>">
                        x
                        <input type="number" name="thumbnails_height" value="<?= CONFIG['thumbnails']['height'] ?>">
                    </div>
                </fieldset>

                <fieldset class="block">
                    <legend>Metadata</legend>

                    <label for="metadata_directory">Save directory:</label>
                    <input type="text" name="metadata_directory" id="metadata_directory"
                        value="<?= CONFIG['metadata']['directory'] ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Reports</legend>

                    <label for="report_enable">Enable:</label>
                    <input type="checkbox" name="report_enable" id="report_enable" value="on"
                        <?= CONFIG['report']['enable'] ? 'checked' : '' ?>>

                    <label for="report_directory">Save directory:</label>
                    <input type="text" name="report_directory" id="report_directory"
                        value="<?= CONFIG['report']['directory'] ?>">

                    <label for="report_reasons">Reasons:</label>
                    <textarea name="report_reasons" id="report_reasons"
                        placeholder="One reason per line"><?= implode("\n", CONFIG['report']['reasons']) ?></textarea>
                </fieldset>

                <fieldset class="block">
                    <legend>Moderation</legend>

                    <label for="moderation_banfiles">File ban permission:</label>
                    <input type="checkbox" name="moderation_banfiles" id="moderation_banfiles" value="on"
                        <?= CONFIG['moderation']['banfiles'] ? 'checked' : '' ?>>

                    <label for="moderation_path">Password file:</label>
                    <input type="text" name="moderation_path" id="moderation_path"
                        value="<?= CONFIG['moderation']['path'] ?>">

                    <label for="moderation_hashpath">Banned hashes file:</label>
                    <input type="text" name="moderation_hashpath" id="moderation_hashpath"
                        value="<?= CONFIG['moderation']['hashpath'] ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Users</legend>
                    <label for="users_path">User filepath:</label>
                    <input type="text" name="users_path" id="users_path" value="<?= CONFIG['users']['path'] ?>">

                    <label for="users_cookietime">Cookie lifetime:</label>
                    <input type="number" name="users_cookietime" id="users_cookietime"
                        value="<?= CONFIG['users']['cookietime'] ?>">

                    <label for="users_allowregistration">Allow registration:</label>
                    <input type="checkbox" name="users_allowregistration" value="on"
                        <?= CONFIG['users']['allowregistration'] ? 'checked' : '' ?>>

                    <label for="users_usernameregex">Username regex:</label>
                    <input type="text" name="users_usernameregex" id="users_usernameregex"
                        value="<?= CONFIG['users']['usernameregex'] ?>">

                    <label for="users_usernameminlength">Min. username length:</label>
                    <input type="number" name="users_usernameminlength" id="users_usernameminlength"
                        value="<?= CONFIG['users']['usernameminlength'] ?>">

                    <label for="users_usernamemaxlength">Max. username length:</label>
                    <input type="number" name="users_usernamemaxlength" id="users_usernamemaxlength"
                        value="<?= CONFIG['users']['usernamemaxlength'] ?>">

                    <label for="users_passwordminlength">Min. password length:</label>
                    <input type="number" name="users_passwordminlength" id="users_passwordminlength"
                        value="<?= CONFIG['users']['passwordminlength'] ?>">
                </fieldset>

                <fieldset class="block">
                    <legend>Statistics</legend>

                    <label for="stats_enable">Enable:</label>
                    <input type="checkbox" name="stats_enable" id="stats_enable" value="on" <?= CONFIG['stats']['enable'] ? 'checked' : '' ?>>

                    <label for="stats_lastfiles">Display last files:</label>
                    <input type="checkbox" name="stats_lastfiles" id="stats_lastfiles" value="on"
                        <?= CONFIG['stats']['lastfiles'] ? 'checked' : '' ?>>

                    <label for="stats_mostviewed">Display the most viewed files:</label>
                    <input type="checkbox" name="stats_mostviewed" id="stats_mostviewed" value="on"
                        <?= CONFIG['stats']['mostviewed'] ? 'checked' : '' ?>>

                    <label for="stats_disksize">Disk size <i>(in bytes)</i>:</label>
                    <input type="text" name="stats_disksize" id="stats_disksize"
                        value="<?= CONFIG['stats']['disksize'] ?>">
                </fieldset>
            </div>

            <button type="submit" class="fancy">Save</button>
        </form>
    </main>
</body>

</html>