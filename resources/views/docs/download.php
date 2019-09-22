<form action='/api/download' method='POST' id='bible_download_form'>
    <div style='float:left; width: 40%'>
        <h2>Select Bible(s)</h2>
        Some Bibles are not available due to copyright restrictions. <br /><br />

        <table class='parameters' cellspacing="0">
            <tr>
                <th>&nbsp;</th>
                <th><?php echo trans('api.bible_fields.name') ?></th>
                <th><?php echo trans('api.bible_fields.lang') ?></th>
                <th><?php echo trans('api.bible_fields.year') ?></th>
                <th><?php echo trans('api.bible_fields.downloadable') ?></th>
            </tr>

            <?php foreach($bibles as $bible) : ?>
                <tr class='<?php if(!$bible['downloadable']) { echo 'download_disabled'; }?>'>
                    <td>
                        <?php if($bible['downloadable']): ?>
                            <input type='checkbox' name='bible[]' value='<?php echo $bible['module'] ?>' id='bible_download_<?php echo $bible['module'] ?>' />
                        <?php else: ?>
                            <input type='checkbox' name='bible_null[]' value='1' disabled="disabled" />
                        <?php endif; ?>
                    </td>

                    <td><label for='bible_download_<?php echo $bible['module'] ?>'><?php echo $bible['name'] ?></label></td>
                    <td><?php echo $bible['lang'] ?></td>
                    <td><?php echo $bible['year'] ?></td>
                    <td><?php echo $bible['downloadable'] ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>

        </table>
    </div>
    <div style='float:left; width: 40%; margin-left: 100px'>
        <h2>Select a Format</h2>

        <?php foreach($formats as $fmt => $format) : ?>
            <div class='format_box'>
                <input type='radio' name='format' value='<?php echo $fmt; ?>' id='format_<?php echo $fmt; ?>' />
                <label class='format_name' for='format_<?php echo $fmt; ?>'><?php echo $format['name']; ?></label><br /><br />
                <div class="format_description"><?php echo $format['desc']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div style='clear:both'></div>

    <div class='center' style='width: 100px'>
        <input id='bible_download_submit' type='submit' value='Download' class='button' />
    </div>
</form>





