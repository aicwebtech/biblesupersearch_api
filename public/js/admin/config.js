
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
        Dialogs.confirm('Are you sure? \nThis will delete ALL retained rendered Bibles.', function(thing) {
            
            if(thing) {
                // Dialogs.set('loadingShowing', true);

                $.ajax({
                    url: '/admin/config/download/delete',
                    type: 'POST',
                    dataType: 'json',

                    success: function(data, statux, xhr) {
                        // Dialogs.set('loadingShowing', false);
                        console.log(data);
                        $('#rendered_space_used').htmo(data.space_used);

                    },
                    error: function(xhr, status, error) {
                        // Dialogs.set('loadingShowing', false);

                    }
                });
            }
        });

        return false;
    });    

    $('#button_clean_up_rendered').click(function() {
        Dialogs.confirm('Are you sure? \nThis will clean up temporary rendered Bibles.', function(thing) {
            console.log('wat', thing);

            if(thing) {
                alert('clean up not implemented????');
            }
        });

        return false;
    });

    Dialogs = new AICWEBTECH.Enyo.jQuery.EmbeddedDialogs();
    Dialogs.renderInto('dialog_container');
});

