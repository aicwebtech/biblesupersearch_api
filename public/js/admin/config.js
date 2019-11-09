
var Dialogs = null;

$( function() {
    $( "#tabs" ).tabs();

    $('input[name=download__enable]').click(function(e) {
        if( $(this).val() == '1' ) {
            $('#download_addl_settings').show();
        }
        else {
            $('#download_addl_settings').hide();
        }
    });

    $('#button_clear_all_rendered').click(function() {
        Dialogs.confirm('Are you sure? \nThis will delete all retained rendered Bibles.', function(thing) {
            console.log('wat', thing);

            if(thing) {
                alert('delete not implemented????');
            }
        });

        return false;
    });

    Dialogs = new AICWEBTECH.Enyo.jQuery.EmbeddedDialogs();
    Dialogs.renderInto('dialog_container');
});

