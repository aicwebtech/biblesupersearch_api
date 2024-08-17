var bibleDownloadGo = false;
var bibleRenderQueue = [];
var biblesRendered = 0;
var biblesTotal = 0;
var bibleNeedsRender = null;
var bibleRenderQueueProcess = false;
var bibleRenderSelectedFormat = null;
var bibleDownloadDirectSubmit = false;

if(!jQuery) {
    alert('jQuery is required!');
}

if(!$) {
    $ = jQuery; // Because WordPress uses an archaic version of jQuery
}

$( function() {
    $('#bible_download_bypass_limit').val('0');

    $('#bible_download_form').submit(function(e) {
        // return true;

        bibleRenderSelectedFormat = null;

        var hasBibles = false,
            hasFormat = false,
            hasError = false,
            bibleCount = 0,
            err = '';

        $('input[name="bible[]"]').each(function() {
            if( ($(this).prop('checked') )) {
                hasBibles = true;
                bibleCount ++;
            }
        });

        $('input[name=format]').each(function() {
            if( ($(this).prop('checked') )) {
                hasFormat = true;
                bibleRenderSelectedFormat = $(this).val();
            }
        });

        // console.log(hasBibles, hasFormat);
        if(BibleSuperSearchDownloadLimit > 0 && bibleCount > BibleSuperSearchDownloadLimit) {
            err += 'You may download a maximum of ' + BibleSuperSearchDownloadLimit + ' Bibles at once.<br>';
            hasError = true;
        }

        if(!hasBibles) {
            err += 'Please select at least one Bible. <br>';
            hasError = true;
        }

        if(!hasFormat) {
            err += 'Please select a format.';
            hasError = true;
        }

        if(hasError) {
            bibleDownloadAlert('<br>Please correct the following error(s):<br><br>' + err);
            e.preventDefault();
            return false;
        }

        if(bibleDownloadDirectSubmit) {
            console.log('direct submit');
            $('#bible_download_pretty_print').val('1');
            bibleDownloadDirectSubmit = false;
            return true;
        }
        else {
            $('#bible_download_pretty_print').val('0');
        }

        bibleDownloadLoading();

        $.ajax({
            url: BibleSuperSearchAPIURL + '/api/render_needed',
            data: $('#bible_download_form').serialize(),
            dataType: 'json',
            success: function(data, status, xhr) {
                console.log('success', data);
                $('#bible_download_dialog').hide();

                if(data.results.success) {
                    bibleDownloadDirectSubmit = true;
                    $('#bible_download_form').submit();
                }
                else {
                    if(data.results.separate_process_supported) {
                        bibleDownloadAlert(response.errors.join('<br>'));
                    }
                    else {
                        bibleNeedsRender = Array.isArray(data.results.bibles_needing_render) ? data.results.bibles_needing_render : null;
                        bibleDownloadInitProcess();
                    }
                }
            },
            error: function(xhr, status, error) {
                $('#bible_download_dialog').hide();

                try {
                    var response = JSON.parse(xhr.responseText);
                }
                catch(error) {
                    response = false;
                }

                if(!response) {
                     bibleDownloadAlert('An unknown error has occurred 1');
                }
                else if(response.results.separate_process_supported) {
                    bibleDownloadAlert(response.errors.join('<br>'));
                }
                else if(response.results.render_needed) {
                    bibleNeedsRender = Array.isArray(response.results.bibles_needing_render) ? response.results.bibles_needing_render : null;
                    bibleDownloadInitProcess();
                }
                else if (!response.results.success) {
                    bibleDownloadAlert(response.errors.join('<br>'));
                }
                else {
                    bibleDownloadAlert('An unknown error has occurred 2');
                }
            }
        });

        e.preventDefault();
        return false;
    });

    $('#render_cancel').click(function() {
        $('#bible_download_dialog').hide();
        bibleRenderQueueProcess = false;
        bibleRenderQueue = [];
    });

    $('#bible_download_check_all').click(function() {
        var checked = ($(this).prop('checked')) ? true : false;

        $('input[name="bible[]"]').each(function() {
            $(this).prop('checked', checked);
        });

        $('.bible_download_check_all_lang').prop('checked', checked);
    });

    $('.bible_download_check_all_lang').click(function() {
        var checked = ($(this).prop('checked')) ? true : false;
        var lang = $(this).prop('id').substring(25);

        $('input[name="bible[]"]').each(function() {
            if($(this).prop('lang') == lang) {
                $(this).prop('checked', checked);
            }
        });
    });
});

function bibleDownloadError(text) {

}

function bibleDownloadAlert(text) {
    $('#bible_download_dialog_content').html(text);
    $('#bible_download_dialog').show();
}

function bibleDownloadLoading() {
    bibleDownloadAlert("<br /><br /><span class='loading'><b>Loading, please wait ...</b></span>");
}

function bibleDownloadInitProcess() {
    bibleRenderQueueProcess = true;
    biblesRendered = 0;
    
    if(Array.isArray(bibleNeedsRender)) {
        bibleRenderQueue = bibleNeedsRender;
    }
    else {    
        bibleRenderQueue = [];

        $('.bible_download_select:checkbox:checked').each(function(i) {
            bibleRenderQueue.push( $(this).val() );
        });
    }

    bibleDownloadAlert('<h2>Rendering Bibles, this may take a while</h2>');

    if(bibleRenderQueue.length > 0) {
        $('#bible_download_dialog_content').append("<div id='bible_download_process'><div id='bible_download_process_bar'></div></div>");
        biblesTotal = bibleRenderQueue.length;
        $('#bible_download_process_bar').css('width', '0%');
        bibleDownloadProcessNext();
    }
    else {
        $('#bible_download_dialog_content').append('Error: No Bible selected');
    }
}

function bibleDownloadProcessNext() {
    if(bibleRenderQueueProcess) {
        var bible = bibleRenderQueue.shift();
        var name = $('label[for="bible_download_' + bible +'"]').html();
        var msg = '<i>' + name + '</i> ';
        var text = '<span class="float_left rendering_name">Rendering: ' + msg + '</span>';

        $('#bible_download_dialog_content').append(text);

        $.ajax({
            url: BibleSuperSearchAPIURL + '/api/render',
            data: {bible: bible, format: bibleRenderSelectedFormat},
            dataType: 'json',
            success: function(data, status, xhr) {
                _bibleDownloadItemDone();
            },
            error: function(xhr, status, error) {
                try {
                    var response = JSON.parse(xhr.responseText);
                }
                catch(error) {
                    response = false;
                }

                if(response && response.results && response.results.success) {
                    _bibleDownloadItemDone();
                    return;
                }

                // if(xhr && xhr.status == 504) {
                //     _bibleDownloadItemDone();
                //     return;
                // }

                bibleDownloadAlert('An error has occurred, please try again later.');
                bibleRenderQueueProcess = false;
                return;
            }
        });
    }
}

function _bibleDownloadItemDone() {
    $('#bible_download_dialog_content').append('<span class="float_right">-- Done</span><br>');

    if(bibleRenderQueue.length == 0) {
        bibleDownloadProcessFinal();
    }
    else {
        biblesRendered ++;
        var pct = Math.round(1000 * biblesRendered / biblesTotal) / 10;
        $('#bible_download_process_bar').css('width', pct + '%');
        bibleDownloadProcessNext();
    }
}

function bibleDownloadProcessFinal() {
    // return; // debugging
    $('#bible_download_process_bar').css('width', '100%');
    $('#bible_download_dialog').hide();
    bibleDownloadDirectSubmit = true;
    $('#bible_download_form').submit();
}