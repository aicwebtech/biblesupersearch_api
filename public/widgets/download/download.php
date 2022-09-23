<script>
    var BibleSuperSearchAPIURL = '<?php echo $BibleSuperSearchAPIURL; ?>';
    var BibleSuperSearchDownloadLimit = <?php echo (int) $BibleSuperSearchDownloadLimit; ?>;
</script>

<?php
    $downloadable_bibles = 0;

    foreach($BibleSuperSearchBibles as $bible) {
        if($bible['downloadable']) {
            $downloadable_bibles ++;
        }
    }

    $show_check_all = (!$BibleSuperSearchDownloadLimit || $BibleSuperSearchDownloadLimit >= $downloadable_bibles) ? TRUE : FALSE;

    if(!isset($BibleSuperSearchIsAdmin)) {
        $BibleSuperSearchIsAdmin = FALSE;
    }
?>

<form action='<?php echo $BibleSuperSearchAPIURL ?>/api/download' method='POST' id='bible_download_form'>
    <input type='hidden' name='pretty_print' id='bible_download_pretty_print' value='1' />
    <input type='hidden' name='bypass_limit' id='bible_download_bypass_limit' value='1' /> <!-- will be set to 0 if JavaScript is enabled -->

    <div class='container format_container'>
        <h2>Select a Format</h2>

        <?php foreach($BibleSuperSearchDownloadFormats as $kind => $info) : ?>
            <div class='format_group_box'>
                <h2 class='name'><?php echo $info['name']; ?></h2>

                <?php if($info['desc']): ?>
                    <div class='desc'><?php echo $info['desc']; ?></div>
                <?php endif; ?>

                <?php foreach($info['renderers'] as $fmt => $format) : ?>
                    <div class='format_box'>
                        <input type='radio' name='format' value='<?php echo $fmt; ?>' id='format_<?php echo $fmt; ?>' />
                        <label class='format_name' for='format_<?php echo $fmt; ?>'><?php echo $format['name']; ?></label> <?php echo $format['desc']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <br /><br />
    <div class='container bible_container'>
        <h2>Select Bible(s)</h2>

        <?php if($BibleSuperSearchIsAdmin): ?>
            <b>NOTE: You are logged in as ADMIN - all Bibles are downloadable regardless of copyright status!</b><br /><br />
        <?php else: ?>
            Some Bibles may not be available due to copyright restrictions. <br /><br />
        <?php endif; ?>
        <?php $lang_colspan = $BibleSuperSearchDownloadVerbose ? 5 : 3; ?>

        <div class='scrollable'>

            <table class='parameters' cellspacing="0">
                <tr>
                    <th>
                        <?php if($show_check_all): ?>
                            <input type='checkbox' id='bible_download_check_all' title='Check All'>
                        <?php else: ?>
                            &nbsp;
                        <?php endif; ?>
                    </th>
                    <th colspan='2'>
                            &nbsp;
                    </th>
                    <?php if($BibleSuperSearchDownloadVerbose): ?><th>Year</th><?php endif; ?>
                    <?php if($BibleSuperSearchDownloadVerbose): ?><th>Downloadable</th><?php endif; ?>
                    <?php if($BibleSuperSearchDownloadVerbose): ?><th>Research*</th><?php endif; ?>
                </tr>

                <?php $current_language = null; ?>
                <?php foreach($BibleSuperSearchBibles as $bible) : ?>
                    <?php if($BibleSuperSearchIsAdmin) {$bible['downloadable'] = TRUE;} ?>

                    <?php if(!$BibleSuperSearchDownloadVerbose && !$bible['downloadable']) { continue; } ?>

                    <?php if($bible['lang'] != $current_language): ?>
                        <?php $current_language = $bible['lang']; ?>

                        <tr class='language_header'>
                            <th>
                                <input type='checkbox' id='bible_download_check_all_<?php echo $bible['lang_short']?>' 
                                    class='bible_download_check_all_lang' title='Check All <?php echo $current_language; ?>'>
                            </th>
                            <th colspan='<?php echo $lang_colspan; ?>'>
                                <label for='bible_download_check_all_<?php echo $bible['lang_short']?>'>
                                    <?php echo $current_language; ?>
                                </label>
                            </th>
                        </tr>
                    <?php endif; ?>

                    <tr class='<?php if(!$bible['downloadable']) { echo 'download_disabled'; }?>'>
                        <td>&nbsp;</td>
                        <td>
                            <?php if($bible['downloadable']): ?>
                                <input 
                                    type='checkbox' name='bible[]' value='<?php echo $bible['module'] ?>' 
                                    id='bible_download_<?php echo $bible['module'] ?>' class='bible_download_select' lang='<?php echo $bible['lang_short']?>' />
                            <?php elseif ($BibleSuperSearchDownloadVerbose): ?>
                                <input type='checkbox' name='bible_null[]' value='1' disabled="disabled" />
                            <?php endif; ?>
                        </td>

                        <td><label for='bible_download_<?php echo $bible['module'] ?>'><?php echo $bible['name'] ?></label></td>
                        <?php if($BibleSuperSearchDownloadVerbose): ?><td><?php echo $bible['year'] ?></td><?php endif; ?>
                        <?php if($BibleSuperSearchDownloadVerbose): ?><td><?php echo $bible['downloadable'] ? 'Yes' : 'No' ?></td><?php endif; ?>
                        <?php if($BibleSuperSearchDownloadVerbose): ?><td><?php echo $bible['research'] ? 'Yes' : 'No' ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>

        </div>

        <?php if($BibleSuperSearchDownloadVerbose): ?>
            <br /><br />
            <small>
                * Bibles marked as 'Research' are provided for research purposes only and are not necessarily recommended.
            </small>
        <?php endif; ?>
    </div>

    <div style='clear:both'></div>

    <br /><br />

    <div class='centered_div' style='width: 100px'>
        <input id='bible_download_submit' type='submit' value='Download' class='button bible_download_submit' />
    </div>

</form>

<div class='pseudo_dialog' id='bible_download_dialog'>
    <div class='pseudo_dialog_container' style='width: 80%; max-width:600px'>
        <div class='pseudo_dialog_contents' id='bible_download_dialog_content' style='height:200px'>
            
        </div>
        <div class='pseudo_dialog_buttons'>
            <button id='render_cancel'>Cancel</button>
        </div>
    </div>
</div>