<script>
    var BibleSuperSearchAPIURL = '<?php echo $BibleSuperSearchAPIURL; ?>';
</script>

<form action='<?php echo $BibleSuperSearchAPIURL ?>/api/download' method='POST' id='bible_download_form'>
    <input type='hidden' name='pretty_print' id='bible_download_pretty_print' value='1' />
    <input type='hidden' name='bypass_limit' id='bible_download_bypass_limit' value='1' /> <!-- will be set to 0 if JavaScript is enabled -->

    <div class='container bible_container'>
        <h2>Select Bible(s)</h2>
        Some Bibles may not be available due to copyright restrictions. <br /><br />

        <table class='parameters' cellspacing="0">
            <tr>
                <th><input type='checkbox' id='bible_download_check_all' title='Check All'></th>
                <th>Name</th>
                <th>Language</th>
                <?php if($BibleSuperSearchDownloadVerbose): ?><th>Year</th><?php endif; ?>
                <?php if($BibleSuperSearchDownloadVerbose): ?><th>Downloadable</th><?php endif; ?>
            </tr>

            <?php foreach($BibleSuperSearchBibles as $bible) : ?>
                <?php if(!$BibleSuperSearchDownloadVerbose && !$bible['downloadable']) { continue; } ?>

                <tr class='<?php if(!$bible['downloadable']) { echo 'download_disabled'; }?>'>
                    <td>
                        <?php if($bible['downloadable']): ?>
                            <input type='checkbox' name='bible[]' value='<?php echo $bible['module'] ?>' id='bible_download_<?php echo $bible['module'] ?>' class='bible_download_select' />
                        <?php elseif ($BibleSuperSearchDownloadVerbose): ?>
                            <input type='checkbox' name='bible_null[]' value='1' disabled="disabled" />
                        <?php endif; ?>
                    </td>

                    <td><label for='bible_download_<?php echo $bible['module'] ?>'><?php echo $bible['name'] ?></label></td>
                    <td><?php echo $bible['lang'] ?></td>
                    <?php if($BibleSuperSearchDownloadVerbose): ?><td><?php echo $bible['year'] ?></td><?php endif; ?>
                    <?php if($BibleSuperSearchDownloadVerbose): ?><td><?php echo $bible['downloadable'] ? 'Yes' : 'No' ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>

        </table>
    </div>
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
    <div style='clear:both'></div>

    <br /><br />

    <div class='centered_div' style='width: 100px'>
        <input id='bible_download_submit' type='submit' value='Download' class='button bible_download_submit' />
    </div>

</form>

<div class='pseudo_dialog' id='bible_download_dialog'>
    <div class='pseudo_dialog_container' style='width:600px'>
        <div class='pseudo_dialog_contents' id='bible_download_dialog_content' style='height:200px'>
            
        </div>
        <div class='pseudo_dialog_buttons'>
            <button id='render_cancel'>Cancel</button>
        </div>
    </div>
</div>