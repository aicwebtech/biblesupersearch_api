enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.TextConfirm',
    kind: 'AICWEBTECH.Enyo.jQuery.Confirm',
    confirmText: 'DELETE',
    
    components: [
        {name: 'Alert', allowHtml: true, classes: 'dialogCenterText'},
        {tag: 'br'},
        { name: 'ExpectedNotification', components: [
            {tag: 'span', content: 'Please type '},
            {tag: 'span', name: 'ExpectedText'},
            {tag: 'span', content: ' in the box if you wish to proceed.'},
        ]},
        {tag: 'br'},
        {style: 'text-align:center', components: [
            {kind: 'enyo.Input', name: 'Text'},
        ]}
    ],

    bindings: [
        {from: 'confirmText', to: '$.ExpectedText.content'}
    ],

    statics: {
        confirmStatic: function(alert, confirmText, callback) {
            var Confirm = new AICWEBTECH.Enyo.jQuery.TextConfirm();
            Confirm.confirm(alert, confirmText, callback);
        }
    },

    confirm: function(alert, confirmText, callback) {
        this.confirmed = false;
        this.set('alert', alert);
        this.set('confirmText', confirmText);
        this.callback = (typeof callback == 'function') ? callback : null;
        this.open();
    },

    ok: function() {
        if(this.$.Text.get('value') != this.confirmText) {
            this.$.ExpectedNotification.addClass('dialog_red_alert');
            return;
        }

        // this.log();
        this.confirmed = true;
        this.close();
    },
    close: function() {
        this.$.ExpectedNotification.removeClass('dialog_red_alert');
        this.$.Text.set('value', '');
        this.inherited(arguments);
    }

});