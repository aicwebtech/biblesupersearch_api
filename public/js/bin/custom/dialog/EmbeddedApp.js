enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.EmbeddedDialogs',
    loadingShowing: false,

    components: [
        {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'},
        {name: 'Loading', kind: 'AICWEBTECH.Enyo.jQuery.Loading'}
    ],

    bindings: [
        {from: 'loadingShowing', to: '$.loading.showing'}
    ],

    confirm: function(text, callback) {
        this.$.Confirm.confirm(text, callback)
    },

});
