
$( function() {
    $('input[name=download__enable]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('#download_addl_settings').show();
        }
        else {
            $('#download_addl_settings').hide();
        }
    });
});

