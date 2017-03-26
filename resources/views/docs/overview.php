<div>
    <?php echo trans('api.overview.description') ?>
</div>

<?php
    $client_url = env('CLIENT_URL', NULL);
    if($client_url): ?>

    <div>
        <?php echo trans('api.overview.see_in_action') ?>: <a href='<?php echo $client_url ?>' target='_NEW'><?php echo $client_url ?></a>
    </div>
<?php endif; ?>
<br />

<h3><?php echo trans('api.overview.all_actions') ?>:</h3>
<ul>
    <li><?php echo trans('api.overview.bullets.format') ?></li>
    <li><?php echo trans('api.overview.bullets.method') ?></li>
    <li><?php echo trans('api.overview.bullets.cors') ?></li>
    <li><?php echo trans('api.overview.bullets.jsonp') ?></li>
    <li><?php echo trans('api.overview.bullets.structure') ?>:</li>
</ul>

<pre><code>{
    "errors": [],     // <?php echo trans('api.overview.structure.errors') . PHP_EOL ?>
    "error_level": 0, // <?php echo trans('api.overview.structure.error_level') . PHP_EOL ?>
    "results": {}     // <?php echo trans('api.overview.structure.results') . PHP_EOL?>
}
</code></pre>

<br />

<div style='text-align: center'>
    <?php echo trans('app.bss') ?><br /><br />
    <a href='http://www.biblesupersearch.com' target='_NEW'>http://www.BibleSuperSearch.com</a>
</div>