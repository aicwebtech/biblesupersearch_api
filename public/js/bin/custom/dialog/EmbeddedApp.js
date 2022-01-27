enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.EmbeddedDialogs',
    loadingShowing: false,

    components: [
        {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'},
        {name: 'Alert', kind: 'AICWEBTECH.Enyo.jQuery.Alert'},
        {name: 'TextConfirm', kind: 'AICWEBTECH.Enyo.jQuery.TextConfirm'},
        {name: 'Loading', kind: 'AICWEBTECH.Enyo.jQuery.Loading'}
    ],

    bindings: [
        {from: 'loadingShowing', to: '$.Loading.showing'}
    ],

    alert: function(text, callback) {
        this.$.Alert.alert(text, callback);
    },      
    confirm: function(text, callback) {
        this.$.Confirm.confirm(text, callback);
    },    
    textConfirm: function(alert, confirmText, callback) {
        this.$.TextConfirm.confirm(alert, confirmText, callback);
    },
});
