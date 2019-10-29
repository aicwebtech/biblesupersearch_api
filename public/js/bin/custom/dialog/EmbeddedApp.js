enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.EmbeddedDialogs',
    
    components: [
        {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'}
    ],

    confirm: function(text, callback) {
        this.$.Confirm.confirm(text, callback)
    }
});
