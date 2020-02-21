enyo.kind({
    name: 'BibleManager.Application',
    kind: 'enyo.Application',

    view: 'BibleManager.View',
    renderTarget: 'enyo_container',

    ajaxLoading: false,

    ajaxLoadingChanged: function(was, is) {

    },
    alert: function(text) {
        this.view.$.Alert.alert(text);
    },
    confirm: function(text, callback) {
        this.view.$.Confirm.confirm(text, callback);
    },
    refreshGrid: function() {
        this.view.$.GridContainer.refreshGrid();
    },
    alertPrem: function() {
        var msg = [
            'This is a premium feature.',
            "Buy your premium license at",
            "",
            "<a href='https://www.biblesupersearch/premium'>www.biblesupersearch/premium</a>"
        ];

        this.alert( msg.join('<br />') );
    }

});
