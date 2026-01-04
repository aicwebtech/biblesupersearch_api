$( function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $( "#tabs" ).tabs();

    $('input[name=download__enable]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('.download_addl_settings').show();

            if($('#download_retain_1').prop('checked') == true) {
                $('#retained_file_settings').show();
            }
        }
        else {
            $('.download_addl_settings').hide();
            $('#retained_file_settings').hide();
        }
    });    

    $('input[name=download__retain]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('#retained_file_settings').show();
        }
        else {
            $('#retained_file_settings').hide();
        }
    });    

    $('input[name=bss__public_access]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('.public_access').show();
        }
        else {
            $('.public_access').hide();
        }
    });

    $('input[name=audio__enable]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('.audio_bible_section').show();

            if($('input[name=audio__tts_api_enable]:checked').val() == '1') {
                $('.audio_bible_tts_section').show();
            }
        }
        else {
            $('.audio_bible_section').hide();
            $('.audio_bible_tts_section').hide();
        }
    });

    $('input[name=audio__tts_api_enable]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('.audio_bible_tts_section').show();
        }
        else {
            $('.audio_bible_tts_section').hide();
        }
    });

    $('#audio_tts_api').change(function(e) {
        var api = $(this).val();

        if(api == 'openai') {
            $('.audio_tts_api_openai_options').show();
        }
        else {
            $('.audio_tts_api_openai_options').hide();
        }

        if(api == 'narakeet') {
            $('.audio_tts_api_narakeet_options').show();
        }
        else {
            $('.audio_tts_api_narakeet_options').hide();
        }
    });

    $('#button_clear_all_rendered').click(function() {
        AICWEBTECH.jQuery.Dialogs.textConfirm('Are you sure? \nThis will delete ALL retained rendered Bibles.', 'DELETE', function(confirm) {

            if(confirm) {
                AICWEBTECH.jQuery.Dialogs.set('loadingShowing', true);
                
                $.ajax({
                    url: baseAppUrl + '/admin/config/download/delete',
                    type: 'POST',
                    dataType: 'json',

                    success: function(data, statux, xhr) {
                        AICWEBTECH.jQuery.Dialogs.set('loadingShowing', false);
                        $('#rendered_space_used').html(data.space_used);
                    },
                    error: function(xhr, status, error) {
                        AICWEBTECH.jQuery.Dialogs.set('loadingShowing', false);
                        alert('An error has occurred');
                    }
                });
            }
        });

        return false;
    });    

    $('#button_clean_up_rendered').click(function(e) {
        AICWEBTECH.jQuery.Dialogs.confirm('Are you sure? \nThis will clean up temporary rendered Bibles.', function(confirm) {

            if(confirm) {
                AICWEBTECH.jQuery.Dialogs.set('loadingShowing', true);

                $.ajax({
                    url: baseAppUrl + '/admin/config/download/cleanup',
                    type: 'POST',
                    dataType: 'json',

                    success: function(data, statux, xhr) {
                        AICWEBTECH.jQuery.Dialogs.set('loadingShowing', false);
                        $('#rendered_space_used').html(data.space_used);
                    },
                    error: function(xhr, status, error) {
                        AICWEBTECH.jQuery.Dialogs.set('loadingShowing', false);
                        alert('An error has occurred');
                    }
                });
            }
        });

        return false;
    });
    
    $( "#rendered_space_slider" ).slider({
      range: true,
      min: 30,
      max: 5000,
      values: [ downloadTempCacheSize, downloadTempCacheSize + downloadCacheSize ],
      slide: function( event, ui ) {
        var temp = ui.values[0];
        var total = ui.values[1];
        var cache = total - temp;

        $('#download_temp_cache_size').val(temp);
        $('#download_cache_size').val(cache);
        $('#download_total_cache_size').val(total);
      }
    });

    $('.download_size').change(function(e) {
        var elmId = e.currentTarget.id;
        var min = temp = parseInt( $('#download_temp_cache_size').val(), 10);
        var cache = parseInt( $('#download_cache_size').val(), 10);
        var max = total = parseInt( $('#download_total_cache_size').val(), 10);

        if(elmId == 'download_total_cache_size') {
            cache = total - temp;

            if(cache < 0) {
                AICWEBTECH.jQuery.Dialogs.alert('Error: This total size would push the retained size below zero.', function() {
                    $('#download_temp_cache_size').val(downloadTempCacheSize);
                    $('#download_cache_size').val(downloadCacheSize);
                    $('#download_total_cache_size').val(downloadCacheSize + downloadTempCacheSize);
                });
                return;
            }

            $('#download_cache_size').val(cache);
        }
        else {
            max = temp + cache;
            $('#download_total_cache_size').val(max);
        }

        downloadCacheSize = cache;
        downloadTempCacheSize = temp;
        $('#rendered_space_slider').slider('option', 'values', [min, max]);
    });

    AICWEBTECH.jQuery.Dialogs.init();
});

