<div>
    <?php echo trans('api.overview.description') ?>
</div>

<?php
    $client_url = config('app.client_url');
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

<h3><?php echo trans('api.overview.official.label') ?></h3>

<div id='link-accordion'>
    <h4><?php echo trans('api.overview.official.client.label') ?></h4>
    <div>
        <p><?php echo trans('api.overview.official.client.desc') ?>: "<?php echo $my_url ?>"</p>
        <p><?php echo trans('api.overview.official.it_should_look_like_this') ?></p>
        <pre><code> // URL of Bible SuperSearch API         (string)
 //      Note:  You can install the Bible SuperSearch API on your own server
 //             If you do, you will need to change this
 //             Default: https://api.biblesupersearch.com
 "apiUrl": "<?php echo $my_url ?>",</code></pre>
        <p><?php echo trans('api.overview.official.client.desc2') ?></p>
    </div>
    <h4><?php echo trans('api.overview.official.wp.label') ?></h4>
    <div>
        <p><?php echo trans('api.overview.official.wp.desc1') ?></p>
        <img src='images/screenshots/WP-Admin-Settings-Link.png' class='linking' />
        <p><?php echo trans('api.overview.official.wp.desc2') ?></p>
        <img src='images/screenshots/WP-Admin-Advanced-Tab-Select.png' class='linking' />
        <p><?php echo trans('api.overview.official.wp.desc3') ?> <?php echo $my_url ?></p>
        <div class='wp-link-container'>
            <img src='images/screenshots/WP-Admin-API-URL-Empty-Blank.png' class='linking' />
            <div class='wp-link-float'><?php echo $my_url ?></div>
        </div>
        <p><?php echo trans('api.overview.official.wp.desc4') ?></p>
        <div class='wp-link-container'>
            <img src='images/screenshots/WP-Admin-API-URL-Save.png' class='linking' />
            <div class='wp-link-float'><?php echo $my_url ?></div>
        </div>
        <p><?php echo trans('api.overview.official.wp.desc5') ?></p>
    </div>
</div>
<br /><br />

<div style='text-align: center'>
    <?php echo trans('app.bss') ?><br /><br />
    <a href='http://www.biblesupersearch.com' target='_NEW'>http://www.BibleSuperSearch.com</a>
</div>