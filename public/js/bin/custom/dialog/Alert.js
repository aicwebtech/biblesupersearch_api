enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.Alert',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    text: null,
    autoOpen: true,
    title: null,

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
                    click: enyo.bind(this, this.close)
                },            
            ]
        });

        this.$.Alert.set('content', this.text);
    },

    textChanged: function(was, is) {
        this.$.Alert.set('content', is);
        return this;
    },
    alert: function(text, callback) {
        this.set('text', text);
        this.open();
    }
});