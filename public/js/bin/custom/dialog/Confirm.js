enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.Confirm',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    alert: null,
    autoOpen: true,
    title: null,
    callback: null,
    confirmed: false,
    closing: false,

    components: [
        {name: 'Alert', allowHtml: true, classes: 'dialogCenterText'}
    ],

    create: function() {
        this.inherited(arguments);

        this.setDialogOptions({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'OK',
                    icon: 'ui-icon-check',
                    click: enyo.bind(this, this.ok)
                },                      
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel',
                    click: enyo.bind(this, this.close)
                },            
            ]
        });

        this.$.Alert.set('content', this.alert);
    },

    alertChanged: function(was, is) {
        this.$.Alert.set('content', is);
        return this;
    },

    open: function() {
        this.inherited(arguments);
        this.confirmed = false;
        this.closing = false;
    },
    close: function() {
        this.inherited(arguments);

        if(this.closing) {
            return;
        }

        this.log();
        this.closing = true;

        if(typeof this.callback == 'function') {
            this.callback(this.confirmed);
        }
    },
    ok: function() {
        // this.log();
        this.confirmed = true;
        this.close();
    },
    confirm: function(text, callback) {
        this.confirmed = false;
        this.set('alert', text);
        this.callback = (typeof callback == 'function') ? callback : null;
        this.open();
    }
});