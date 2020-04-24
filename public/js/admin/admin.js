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
            if(confirmed) {                
                $(field).prop('readonly', false);
                $(button).hide();
            }
        })

        // if(window.confirm(msg)) {
        // };

        return false;
    });

    $('#top_menu a').click(function() {
        // alert('heree');
    })

    $('#page_loading_dialog').dialog({
        modal: true,
        title: 'Loading ...',
        autoOpen: false,
        height: 'auto',
        width: 'auto',
        closeOnEscape: false
    });

});

window.addEventListener('beforeunload', function(event) {
    window.setTimeout(function() {
        window.console && console.log('unload');
        $('#page_loading_dialog').dialog('open');
    }, 1000);
});

function confirmEnableField() {

}

