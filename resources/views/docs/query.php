<?php
    $context = 'query';
    $url = '';
    //include( dirname(__FILE__) . '/generic.php');

    renderActionHeader('query', '');

    $url = getServerUrl();

    ?>
<h3>Examples</h3>
<?php echo trans('query.examples.lookup1') ?><br />
<a href="<?php echo $url ?>/api?bible=kjv&reference=Rom 4:1-10" target="querylookup1">
         <?php echo $url ?>/api?bible=kjv&reference=Rom 4:1-10
</a><br /><br />
<?php echo trans('query.examples.lookup2') ?><br />
<a href="<?php echo $url ?>/api?bible=kjv&reference=Rom 1:1-2; Matt 5:6-8; John 3:16" target="querylookup1">
         <?php echo $url ?>/api?bible=kjv&reference=Rom 1:1-2; Matt 5:6-8; John 3:16
</a><br /><br />
<?php echo trans('query.examples.search1') ?><br />
<a href="<?php echo $url ?>/api?bible=kjv&search=faith" target="querylookup1">
         <?php echo $url ?>/api?bible=kjv&search=faith
</a><br /><br />
<?php echo trans('query.examples.search2') ?><br />
<a href="<?php echo $url ?>/api?bible=kjv&reference=Rom&search=faith" target="querylookup1">
         <?php echo $url ?>/api?bible=kjv&reference=Rom&search=faith
</a><br /><br />

<?php

    $params = array(
        'reference',
        'search',
        'bible',
        'whole_words',
        'exact_case',
        'data_format',
        'highlight',
        'highlight_tag',
        'search_type',
        'proximity_limit',
    );

    $advanced_params = array(
        'search_all',
        'search_any',
        'search_one',
        'search_none',
        'search_phrase',
        //'search_regexp',  // Future feature
        //'search_boolean', // Future feature
    );

    renderParameterHeader();
    renderParameters($params, $context);
    renderCommonParameters(['callback']);
    renderParameterFooter();

    ?><div><?php echo trans('query.advanced') ?>:</div><?php

    renderParameterHeader('advanced_parameters');
    renderParameters($advanced_params, $context);
    renderParameterFooter();

    include( dirname(__FILE__) . '/query_structures.php');
