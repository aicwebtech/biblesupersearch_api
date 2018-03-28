enyo.kind({
    name: 'BibleManager.View',
    gridHandle: null,

    components: [
        {name: 'BulkActionsContainer', classes: 'buik_actions_container', components: [
            {name: 'BulkActions', style: 'float: left', showing: false, components: [
                {tag: 'span', content: 'With Selected: '},
                {tag: 'button', classes: 'button bulk', content: 'Install'},
                {tag: 'button', classes: 'button bulk', content: 'Uninstall'},
                {tag: 'button', classes: 'button bulk', content: 'Enable'},
                {tag: 'button', classes: 'button bulk', content: 'Disable'},
                {tag: 'button', classes: 'button bulk', content: 'Export Module File'},
            ]},
            {name: 'SortOptions', style: 'float: right', components: [
                {tag: 'button', classes: 'button bulk', content: 'Auto Sort'},
            ]},
            {name: 'Dialogs', components: [
                {name: 'Alert', kind: 'AICWEBTECH.Enyo.jQuery.Alert'},
                {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'}
            ]},
            {style: 'clear: both'},
        ]},
        {name: 'GridContainer', kind: 'BibleManager.Components.Grid'},
        {kind: 'enyo.Signals', onBibleInstall: 'bibleInstall', onBibleUninstall: 'bibleUninstall', onConfirmAction: 'confirmAction'}
    ],

    bibleInstall: function(inSender, inEvent) {
        this.$.Alert.alert('Heya, this might take a while, are you sure?');

        // this.$.Confirm.confirm('Are you sure?', enyo.bind(this, function(confirmed) {
        //     this.log('confirmed', confirmed);
        // }));

        // this.log(inEvent);
    },    
    bibleUninstall: function(inSender, inEvent) {
        this.log(inEvent);
        this.$.Alert.alert('You wure u want to uninstall it?');
    },
    confirmAction: function(inSender, inEvent) {
        this.log(inEvent);
        var id = inEvent.id;
        var action = inEvent.action;
        var rowData = this.$.GridContainer.getRowByPk(inEvent.id);
        var text = "Are you sure that you want to <b>" + inEvent.action + "</b><br /><br />'" + rowData.name + "'?";

        this.$.Confirm.confirm(text, enyo.bind(this, function(confirmed) {
            this.log('confirmed', confirmed);

            if(confirmed) {
                var url = '/admin/bibles/' + action + '/' + id;
                this.log('about to load', url);
            }
        }));

    }
});
