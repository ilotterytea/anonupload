<?php
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/alert.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/partials.php";
include_once "{$_SERVER['DOCUMENT_ROOT']}/lib/config.php";

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
        }

        else if ($v === 'on') {
            $v = true;
        }

        else if (is_numeric($v)) {
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
    <title>System configuration - <?= CONFIG['instance']['name'] ?></title>
    <meta name="description" content="The instance configuration of <?= CONFIG['instance']['name'] ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/static/style.css">
    <link rel="shortcut icon" href="/static/favicon.ico" type="image/x-icon">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="theme-color" content="#ffe1d4">
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <main>
        <?php html_mini_navbar() ?>
        <?php display_alert() ?>
        <h1>System configuration</h1>
        <?php if (!file_exists(CONFIG_FILE_PATH)): ?>
            <div class="box alert">
                <p>This message confirms the instance is nearly ready. The configuration below shows the default
                            settings.</p>
            </div>
            <?php endif; ?>
        <hr>
        <form action="/system/config.php" method="post">
            <h2>Instance</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Name</th>
                    <td><input type="text" name="instance_name" value="<?= CONFIG['instance']['name'] ?>"></td>
                </tr>
                <tr>
                    <th>Mirrors</th>
                    <td>
                        <textarea name="instance_mirrors"
                            placeholder="One line per mirror. The line should follow this formatting: url=name."
                            ><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['instance']['mirrors']), CONFIG['instance']['mirrors'])) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>Footer links</th>
                    <td>
                        <textarea name="instance_footerlinks"
                            placeholder="One line per link. The line should follow this formatting: name=url."
                            ><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['instance']['footerlinks']), CONFIG['instance']['footerlinks'])) ?></textarea>
                    </td>
                </tr>
            </table>

            <h2>Storage</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Type</th>
                    <td>
                        <select name="storage_type">
                            <option value="json" <?= CONFIG["storage"]["type"] === "file" ? 'selected' : '' ?>>Files only</option>
                            <option value="json" <?= CONFIG["storage"]["type"] === "json" ? 'selected' : '' ?>>JSON-based</option>
                            <option value="database" <?= CONFIG["storage"]["type"] === "database" ? 'selected' : '' ?>>Database</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2>Database</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Driver</th>
                    <td><input type="text" name="database_driver" value="<?= CONFIG['database']['driver'] ?>"></td>
                </tr>
                <tr>
                    <th>Hostname</th>
                    <td><input type="text" name="database_host" value="<?= CONFIG['database']['host'] ?>"></td>
                </tr>
                <tr>
                    <th>Port</th>
                    <td><input type="number" name="database_port" value="<?= CONFIG['database']['port'] ?>"></td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td><input type="text" name="database_name" value="<?= CONFIG['database']['name'] ?>"></td>
                </tr>
                <tr>
                    <th>User</th>
                    <td><input type="text" name="database_user" value="<?= CONFIG['database']['user'] ?>"></td>
                </tr>
                <tr>
                    <th>Password</th>
                    <td><input type="text" name="database_pass" value="<?= !empty(CONFIG['database']['pass']) ? '****' : '' ?>"></td>
                </tr>
            </table>

            <h2>Driver</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Ruffle</th>
                    <td>
                        <input type="text" name="driver_ruffle" value="<?= CONFIG['driver']['ruffle'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>Chart.js</th>
                    <td>
                        <input type="text" name="driver_chart" value="<?= CONFIG['driver']['chart'] ?>">
                    </td>
                </tr>
            </table>

            <h2>File catalog</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Public access:</th>
                    <td>
                        <input type="checkbox" name="filecatalog_public" value="on"
                            <?= CONFIG['filecatalog']['public'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Files per page:</th>
                    <td>
                        <input type="number" name="filecatalog_limit" value="<?= CONFIG['filecatalog']['limit'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>Include only MIME-types:</th>
                    <td>
                        <input type="text" name="filecatalog_includemimetypes" value="<?= implode(' ', CONFIG['filecatalog']['includemimetypes']) ?>">
                    </td>
                </tr>
            </table>

            <h2>Random files <i>(Suprise Me!)</i></h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Enable:</th>
                    <td>
                        <input type="checkbox" name="supriseme_enable" value="on"
                            <?= CONFIG['supriseme']['enable'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Order condition:</th>
                    <td>
                        <input type="text" name="filecatalog_order" value="<?= CONFIG['supriseme']['order'] ?>">
                    </td>
                </tr>
            </table>

            <h2>Files</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Fancy view:</th>
                    <td>
                        <input type="checkbox" name="files_fancyview" value="on"
                            <?= CONFIG['files']['fancyview'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Count views:</th>
                    <td>
                        <input type="checkbox" name="files_countviews" value="on"
                            <?= CONFIG['files']['countviews'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Show upload time:</th>
                    <td>
                        <input type="checkbox" name="files_showuploadtime" value="on"
                            <?= CONFIG['files']['showuploadtime'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Show views:</th>
                    <td>
                        <input type="checkbox" name="files_showviews" value="on"
                            <?= CONFIG['files']['showviews'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Display uploaded HTML-pages:</th>
                    <td>
                        <input type="checkbox" name="files_displayhtml" value="on"
                            <?= CONFIG['files']['displayhtml'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Directory:</th>
                    <td>
                        <input type="text" name="files_directory" value="<?= CONFIG['files']['directory'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>URL:</th>
                    <td>
                        <input type="text" name="files_url" value="<?= CONFIG['files']['url'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>Deletion:</th>
                    <td>
                        <input type="checkbox" name="files_deletion" value="on"
                            <?= CONFIG['files']['deletion'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Deletion key length:</th>
                    <td>
                        <input type="number" name="files_deletionkeylength" value="<?= CONFIG['files']['deletionkeylength'] ?>">
                        characters
                    </td>
                </tr>
            </table>

            <h2>File upload</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Verify MIME-type:</th>
                    <td>
                        <input type="checkbox" name="upload_verifymimetype" value="on"
                            <?= CONFIG['upload']['verifymimetype'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Strip EXIF information:</th>
                    <td>
                        <input type="checkbox" name="upload_stripexif" value="on"
                            <?= CONFIG['upload']['stripexif'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>ID characters:</th>
                    <td>
                        <input type="text" name="upload_idcharacters" value="<?= CONFIG['upload']['idcharacters'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>ID length:</th>
                    <td>
                        <input type="number" name="upload_idlength" value="<?= CONFIG['upload']['idlength'] ?>">
                        characters
                    </td>
                </tr>
                <tr>
                    <th>ID prefix:</th>
                    <td>
                        <input type="text" name="upload_idprefix" value="<?= CONFIG['upload']['idprefix'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>Custom ID:</th>
                    <td>
                        <input type="checkbox" name="upload_customid" value="on"
                            <?= CONFIG['upload']['customid'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Custom ID regex:</th>
                    <td>
                        <input type="text" name="upload_customidregex" value="<?= CONFIG['upload']['customidregex'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>Max custom ID length:</th>
                    <td>
                        <input type="number" name="upload_customidlength" value="<?= CONFIG['upload']['customidlength'] ?>">
                        characters
                    </td>
                </tr>
                <tr>
                    <th>Max title length:</th>
                    <td>
                        <input type="number" name="upload_titlelength" value="<?= CONFIG['upload']['titlelength'] ?>">
                        characters
                    </td>
                </tr>
                <tr>
                    <th>Unpack ZIP web applications:</th>
                    <td>
                        <input type="checkbox" name="upload_zipwebapps" value="on"
                            <?= CONFIG['upload']['zipwebapps'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Remove letterboxes:</th>
                    <td>
                        <input type="checkbox" name="upload_removeletterboxes" value="on"
                            <?= CONFIG['upload']['removeletterboxes'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>File expiration:</th>
                    <td>
                        <textarea name="upload_expiration"
                            placeholder="One expiration per line. Format: timeout=name. For example: ne=never, 14d=2 weeks, 12h=12 hours, 5m=5 minutes"
                            ><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['upload']['expiration']), CONFIG['upload']['expiration'])) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>Accepted MIME-types:</th>
                    <td>
                        <textarea name="upload_acceptedmimetypes"
                            placeholder="One MIME-type per line. Format: extension=mime. For example: jpg=image/jpeg"
                            ><?= implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys(CONFIG['upload']['acceptedmimetypes']), CONFIG['upload']['acceptedmimetypes'])) ?></textarea>
                    </td>
                </tr>
            </table>

            <h2>External File Upload</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Enable:</th>
                    <td>
                        <input type="checkbox" name="externalupload_enable" value="on"
                            <?= CONFIG['externalupload']['enable'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Max duration:</th>
                    <td>
                        <input type="number" name="externalupload_maxduration" value="<?= CONFIG['externalupload']['maxduration'] ?>">
                        seconds
                    </td>
                </tr>
                <tr>
                    <th>Quality:</th>
                    <td>
                        <input type="text" name="externalupload_quality" value="<?= CONFIG['externalupload']['quality'] ?>">
                    </td>
                </tr>
            </table>

            <h2>Thumbnails</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Enable:</th>
                    <td>
                        <input type="checkbox" name="thumbnails_enable" value="on"
                            <?= CONFIG['thumbnails']['enable'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Directory:</th>
                    <td>
                        <input type="text" name="thumbnails_directory" value="<?= CONFIG['thumbnails']['directory'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>URL:</th>
                    <td>
                        <input type="text" name="thumbnails_url" value="<?= CONFIG['thumbnails']['url'] ?>">
                    </td>
                </tr>
                <tr>
                    <th>Size:</th>
                    <td>
                        <input type="number" name="thumbnails_width" value="<?= CONFIG['thumbnails']['width'] ?>">
                        x
                        <input type="number" name="thumbnails_height" value="<?= CONFIG['thumbnails']['height'] ?>">
                    </td>
                </tr>
            </table>

            <h2>Metadata</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Directory:</th>
                    <td>
                        <input type="text" name="metadata_directory" value="<?= CONFIG['metadata']['directory'] ?>">
                    </td>
                </tr>
            </table>

            <h2>Reports</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Enable:</th>
                    <td>
                        <input type="checkbox" name="report_enable" value="on"
                            <?= CONFIG['report']['enable'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Directory:</th>
                    <td>
                        <input type="text" name="report_directory" value="<?= CONFIG['report']['directory'] ?>">
                    </td>
                </tr>
            </table>

            <h2>Moderation</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>File ban permission:</th>
                    <td>
                        <input type="checkbox" name="moderation_banfiles" value="on"
                            <?= CONFIG['moderation']['banfiles'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Password file:</th>
                    <td>
                        <input type="text" name="moderation_path" value="<?= CONFIG['moderation']['path'] ?>">
                    </td>
                </tr>
            </table>

            <h2>Statistics</h2>
            <hr>
            <table class="vertical">
                <tr>
                    <th>Enable:</th>
                    <td>
                        <input type="checkbox" name="stats_enable" value="on"
                            <?= CONFIG['stats']['enable'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Display last files:</th>
                    <td>
                        <input type="checkbox" name="stats_lastfiles" value="on"
                            <?= CONFIG['stats']['lastfiles'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Display the most viewed files:</th>
                    <td>
                        <input type="checkbox" name="stats_mostviewed" value="on"
                            <?= CONFIG['stats']['mostviewed'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <tr>
                    <th>Disk size:</th>
                    <td>
                        <input type="text" name="stats_disksize" value="<?= CONFIG['stats']['disksize'] ?>">
                        bytes
                    </td>
                </tr>
            </table>

            <button type="submit" class="fancy">Save</button>
        </form>
    </main>
</body>

</html>