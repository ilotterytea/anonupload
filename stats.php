<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/partials.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/storage.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils.php';

$stats = FILESTORAGE->get_stats();
if (IS_JSON_REQUEST) {
    send_json_response($stats);
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php html_head("statistics"); ?>
</head>

<body>
    <header>
        <?php html_header(); ?>
        <h2>statistics</h2>
    </header>
    <main class="statistics">
        <section class="box">
            <div class="tab">
                <h3>about files</h3>
            </div>
            <div class="content">
                <table class="vertical left">
                    <?php if (isset($stats['serving_files'])): ?>
                        <tr>
                            <th>serving files</th>
                            <td><?= $stats['serving_files'] ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($stats['active_content'])): ?>
                        <tr>
                            <th>active content</th>
                            <td><?= format_filesize($stats['active_content']) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($stats['average_file_size'])): ?>
                        <tr>
                            <th>average file size</th>
                            <td><?= format_filesize($stats['average_file_size']) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($stats['average_upload_rate'])): ?>
                        <tr>
                            <th>average upload rate &lpar;per minute&rpar;</th>
                            <td><?= sprintf("%.4f", $stats['average_upload_rate']) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($stats['serving_future_files'])): ?>
                        <tr>
                            <th>how many files can be uploaded</th>
                            <td>~<?= $stats['serving_future_files'] ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </section>

        <?php if (isset($stats['timeline'], CONFIG['driver']['chart'])): ?>
            <section class="box">
                <div class="tab">
                    <h3>files uploaded &lpar;per quarter&rpar;</h3>
                </div>
                <div class="content">
                    <noscript>JavaScript is required for bar chart</noscript>
                    <canvas id="uploaded-files-chart"></canvas>
                </div>
            </section>
        <?php endif; ?>

        <?php if (isset($stats['most_viewed'])): ?>
            <section class="box">
                <div class="tab">
                    <h3>the most viewed files</h3>
                </div>
                <div class="content wall">
                    <?php foreach ($stats['most_viewed'] as $file): ?>
                        <?php html_file_brick($file) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <footer>
        <?php html_footer(); ?>
    </footer>
</body>

<?php if (isset($stats['timeline'], CONFIG['driver']['chart'])): ?>
    <script src="<?= CONFIG['driver']['chart'] ?>"></script>
    <script>
        new Chart("uploaded-files-chart", {
            type: "bar",
            data: {
                labels: <?= sprintf('[%s]', implode(', ', array_map(
                    fn($x) =>
                    sprintf("'%s-%s'", $x['year'], $x['quarter']),
                    $stats['timeline']
                ))) ?>,
                datasets: [{
                    data: <?= sprintf('[%s]', implode(', ', array_map(
                        fn($x) => $x['file_count'],
                        $stats['timeline']
                    ))) ?> }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    },
                }
            }
        });
    </script>
<?php endif; ?>

</html>