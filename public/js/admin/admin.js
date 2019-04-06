$( function() {
    $( ".button" ).button();
    jQuery.browser = {};

    $('button.enable').click(function() {
        console.log('here');
        var button = this;
        var label = $(this).parent().parent().children()[0];
        var labelText = $(label).html().trim().replace(':', '');
        console.log($(label).html());
        var field = $(this).parent().children('input')[0];
        console.log(field);

        var msg = 'You asked to enable the field \'' + labelText + '\'. <br><br> Editing this field may cause issues. <br><br> Are you sure you know what you\'re doing?';
        // AICWEBTECH.Custom.Overrides.alert(msg);

        AICWEBTECH.Custom.Overrides.confirm(msg, function(confirmed) {
            console.log('CONFIRMEING');

            if(confirmed) {                
                $(field).prop('readonly', false);
                $(button).hide();
            }
        })

        // if(window.confirm(msg)) {
        // };

        return false;
    })
});

function confirmEnableField() {

}

