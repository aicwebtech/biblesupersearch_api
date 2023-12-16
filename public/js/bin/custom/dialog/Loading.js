enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.Loading',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    text: 'Loading ...',
    autoOpen: true,
    title: null,

    components: [
        {name: 'Alert', content: 'Loading ...'},
        {name: 'Img', kind: 'enyo.Image', src: '../images/Spinner.gif', style: 'margin-top: 20px'}
    ],

    create: function() {
        this.inherited(arguments);

        this.setDialogOptions({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            closeOnEscape: false
        });

        this.$.Alert.set('content', this.text);
    },

    textChanged: function(was, is) {
        this.$.Alert.set('content', is);
        return this;
    }
});