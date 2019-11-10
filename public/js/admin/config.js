
var Dialogs = null;

$( function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

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
        Dialogs.textConfirm('Are you sure? \nThis will delete ALL retained rendered Bibles.', 'DELETE', function(confirm) {
            
            if(confirm) {
                Dialogs.set('loadingShowing', true);
                
                $.ajax({
                    url: '/admin/config/download/delete',
                    type: 'POST',
                    dataType: 'json',

                    success: function(data, statux, xhr) {
                        Dialogs.set('loadingShowing', false);
                        $('#rendered_space_used').html(data.space_used);
                    },
                    error: function(xhr, status, error) {
                        Dialogs.set('loadingShowing', false);
                        alert('An error has occurred');
                    }
                });
            }
        });

        return false;
    });    

    $('#button_clean_up_rendered').click(function() {
        Dialogs.confirm('Are you sure? \nThis will clean up temporary rendered Bibles.', function(confirm) {
            console.log('wat', confirm);

            if(confirm) {
                Dialogs.set('loadingShowing', true);

                $.ajax({
                    url: '/admin/config/download/cleanup',
                    type: 'POST',
                    dataType: 'json',

                    success: function(data, statux, xhr) {
                        Dialogs.set('loadingShowing', false);
                        $('#rendered_space_used').html(data.space_used);
                    },
                    error: function(xhr, status, error) {
                        Dialogs.set('loadingShowing', false);
                        alert('An error has occurred');
                    }
                });
            }
        });

        return false;
    });

    Dialogs = new AICWEBTECH.Enyo.jQuery.EmbeddedDialogs();
    Dialogs.renderInto('dialog_container');
});

