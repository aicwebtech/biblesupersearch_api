<?php
    $context = 'api.download';
    $url = '/download';
    renderActionHeader($context, $url);
    renderParameterHeader();
    renderCommonParameters(['language', 'callback']);
    renderParameterFooter();
?>

<div>
    TODO - FINISH THIS!
    <?php echo trans('api.data_structure') . ' ' . trans('api.example'); ?>:
</div>