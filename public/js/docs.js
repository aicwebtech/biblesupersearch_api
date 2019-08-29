$( function() {
    $( "#tabs" ).tabs();
    $( "#actions_tabs" ).tabs();

    $('#link-accordion').accordion({
        header: 'h4'
    });

    $('.button').button();

    $('#bible_download_submit').click(function(e) {
        console.log('hahaha');

        var hasBibles = false,
            hasFormat = false;

        $('input[name="bible[]"]').each(function() {
            if( ($(this).prop('checked') )) {
                hasBibles = true;
            }
        });

        $('input[name=format]').each(function() {
            if( ($(this).prop('checked') )) {
                hasFormat = true;
            }
        });

        console.log(hasBibles, hasFormat);

        var err = '';

        if(!hasBibles) {
            err += 'Please select at least one Bible. \n';
        }

        if(!hasFormat) {
            err += 'Please select a format.';
        }

        if(!hasBibles || !hasFormat) {
            alert(err);
            e.preventDefault();
            return false;
        }
    });
});
