var AICWEBTECH = AICWEBTECH || {};
AICWEBTECH.jQuery = AICWEBTECH.jQuery || {};  

AICWEBTECH.jQuery.Dialogs = {
    // call upon document ready
    init: function() {
        $('#jquery_dialogs_alert').dialog({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            dialogClass: 'dialogNoTitle',
            buttons: [
                {
                    text: 'OK',
                    icon: 'ui-icon-check',
                    click: AICWEBTECH.jQuery.Dialogs._confirmOk
                },                      
            ]
        });

        $('#jquery_dialogs_confirm').dialog({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            dialogClass: 'dialogNoTitle',
            buttons: [
                {
                    text: 'OK',
                    icon: 'ui-icon-check',
                    click: AICWEBTECH.jQuery.Dialogs._confirmOk
                },                      
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel',
                    click: AICWEBTECH.jQuery.Dialogs._confirmCancel
                },            
            ]
        });

        $('#jquery_dialogs_loading').dialog({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            closeOnEscape: false,
            dialogClass: 'dialogNoTitle'
        });
    },

    callback: null,
    confirmWord: null,
     
    alert: function(text, callback) {
        AICWEBTECH.jQuery.Dialogs.callback = callback || null;
        AICWEBTECH.jQuery.Dialogs.confirmWord = null;

        $('#jquery_dialogs_alert_message').html(text);
        $('#jquery_dialogs_alert').dialog('open');

        return false;
    },      
    confirm: function(text, callback) {
        AICWEBTECH.jQuery.Dialogs.callback = callback || null;
        AICWEBTECH.jQuery.Dialogs.confirmWord = null;

        $('#text_confirm_container').hide();
        $('#jquery_dialogs_confirm_message').html(text);
        $('#jquery_dialogs_confirm').dialog('open');
        
        return false;
    },    
    textConfirm: function(text, confirmWord, callback) {
         AICWEBTECH.jQuery.Dialogs.callback = callback || null;
         AICWEBTECH.jQuery.Dialogs.confirmWord = confirmWord;

        $('#text_confirm_container').show();
        $('#text_confirm_word').html(confirmWord);
        $('#text_confirm_input').val('');
        $('#text_confirm_word').removeClass('dialog_red_alert');
        $('#jquery_dialogs_confirm_message').html(text);
        $('#jquery_dialogs_confirm').dialog('open');

        return false;
    },

    set: function(name, value) {
        if(name === 'loadingShowing') {
            if(value) {
                $('#jquery_dialogs_loading').dialog('open');    
            } else {
                $('#jquery_dialogs_loading').dialog('close');
            }   
        }
    },

    // private methods
    _confirmOk: function() {
        if(AICWEBTECH.jQuery.Dialogs.confirmWord) {
            var word = $('#text_confirm_input').val().trim();

            if(word !== AICWEBTECH.jQuery.Dialogs.confirmWord) {
                $('#text_confirm_word').addClass('dialog_red_alert');
                console.log('confirm word mismatch');   
                return;
            }
        }

        if(typeof AICWEBTECH.jQuery.Dialogs.callback == 'function') {
            AICWEBTECH.jQuery.Dialogs.callback(true);
        }

        $('#jquery_dialogs_alert').dialog('close');
        $('#jquery_dialogs_confirm').dialog('close');
    },
    _confirmCancel: function() {
        if(typeof AICWEBTECH.jQuery.Dialogs.callback == 'function') {
            AICWEBTECH.jQuery.Dialogs.callback(false);
        }

        $('#jquery_dialogs_confirm').dialog('close');
    }
}