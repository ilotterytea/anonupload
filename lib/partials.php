<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

function html_big_navbar()
{
    echo '' ?>
    <section class="column justify-center align-center navbar">
        <div class="column justify-center grow">
            <h1><img src="/static/img/brand/big.webp" alt="<?= INSTANCE_NAME ?>"></h1>
        </div>

        <div class="row justify-center">

        </div>
    </section>
    <?php ;
}