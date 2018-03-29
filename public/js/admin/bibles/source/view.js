enyo.kind({
    name: 'BibleManager.View',
    gridHandle: null,
    selections: [],

    handlers: {
        onSelectionsChanged: 'selectionsChanged'
    },

    components: [
        {name: 'BulkActionsContainer', classes: 'buik_actions_container', components: [
            {name: 'BulkActions', style: 'float: left', showing: false, components: [
                {tag: 'span', content: 'With Selected: '},
                {tag: 'button', classes: 'button bulk', content: 'Install', ontap: 'multiInstall', action: 'install', actioning: 'Installing'},
                {tag: 'button', classes: 'button bulk', content: 'Uninstall', ontap: 'multiUninstall', action: 'uninstall', actioning: 'Uninstalling'},
                {tag: 'button', classes: 'button bulk', content: 'Enable', ontap: 'multiEnable', action: 'enable', actioning: 'Enabling'},
                {tag: 'button', classes: 'button bulk', content: 'Disable', ontap: 'multiDisable', action: 'disable', actioning: 'Disabling'},
                {tag: 'button', classes: 'button bulk', content: 'Export Module File', ontap: 'multiExport', action: 'export', actioning: 'Exporting'},
            ]},
            {name: 'SortOptions', style: 'float: right', components: [
                {tag: 'button', classes: 'button bulk', content: 'Auto Sort'},
            ]},
            {style: 'clear: both'},
        ]},
        {name: 'GridContainer', kind: 'BibleManager.Components.Grid'},
        {name: 'Dialogs', components: [
            {name: 'Alert', kind: 'AICWEBTECH.Enyo.jQuery.Alert'},
            {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'},
            {name: 'Install', kind: 'BibleManager.Components.Dialogs.Install'},
            {name: 'Export', kind: 'BibleManager.Components.Dialogs.Export'},
            {name: 'Description', kind: 'BibleManager.Components.Dialogs.Description'}
        ]},
        {
            kind: 'enyo.Signals', 
            onBibleInstall: 'bibleInstall', 
            onBibleExport: 'bibleExport', 
            onConfirmAction: 'confirmAction', 
            onDoAction: 'doAction', 
            onViewDescription: 'viewDescription'
        }
    ],

    bibleInstall: function(inSender, inEvent) {
        var rowData = this.$.GridContainer.getRowByPk(inEvent.id);
        var id = inEvent.id;
        this.$.Install.set('bible', rowData.name);
        
        this.$.Install.confirm(enyo.bind(this, function(confirmed, props) {
            if(confirmed) {
                this._singleActionHelper('install', id, props);
            }
        }));
    },        
    bibleExport: function(inSender, inEvent) {
        var rowData = this.$.GridContainer.getRowByPk(inEvent.id);
        var id = inEvent.id;
        this.$.Export.set('bible', rowData.name);
        
        this.$.Export.confirm(enyo.bind(this, function(confirmed, props) {
            if(confirmed) {
                this._singleActionHelper('export', id, props);
            }
        }));
    },    
    confirmAction: function(inSender, inEvent) {
        var id = inEvent.id;
        var action = inEvent.action;
        var rowData = this.$.GridContainer.getRowByPk(inEvent.id);
        var text = "Are you sure that you want to <b>" + inEvent.action + "</b><br /><br />'" + rowData.name + "'?";

        this.$.Confirm.confirm(text, enyo.bind(this, function(confirmed) {
            this.log('confirmed', confirmed);

            if(confirmed) {
                this._singleActionHelper(action, id, {});
            }
        }));
    },
    doAction: function(inSender, inEvent) {
        this._singleActionHelper(inEvent.action, inEvent.id, {});
    },
    viewDescription: function(inSender, inEvent) {
        var ajax = new enyo.Ajax({
            url: '/admin/bibles/' + inEvent.id,
            method: 'GET'
        });

        ajax.response(this, function(inSender, inResponse) {
            this.$.Description.set('title', inResponse.Bible.name);
            this.$.Description.set('text', inResponse.Bible.description);
            this.$.Description.open();
        });

        ajax.error(this, 'handleError');
        ajax.go();
    },
    _singleActionHelper: function(action, id, postData) {
        var url = '/admin/bibles/' + action + '/' + id;
        this.log('about to load', url);
        this.log('postData', postData);
    },
    
    multiEnable: function(inSender, inEvent) {
        this.log(inSender);
        this._confirmMultiAction('enable', 'Enabling');
    },    
    multiDisable: function(inSender, inEvent) {
        this._confirmMultiAction('disable', 'Disabling');
    },    
    multiUninstall: function(inSender, inEvent) {
        this._confirmMultiAction('uninstall', 'Uninstalling');
    },
    multiInstall: function(inSender, inEvent) {
        this._processSelections();

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }
    },    
    multiExport: function(inSender, inEvent) {
        this._processSelections();

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }
    }, 
    _confirmMultiAction: function(action, actioning) {
        this._processSelections();
        var actioning = (typeof actioning == 'undefined') ? 'Processing' : actioning;
        var action    = (typeof action == 'undefined') ? 'process' : action;

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }

        this.log('selections', this.selections);
    },

    _multiActionHelper: function(action, actioning, postData) {

    },

    _processSelections: function() {
        this.selections = enyo.clone(this.$.GridContainer.getSelectionsWithName());
    },

    selectionsChanged: function(inSender, inEvent) {
        this.$.BulkActions.set('showing', inEvent.length ? true : false);
    },
    handleError: function(inSender, inResponse) {
        this.$.Alert.alert('An unknown error has occurred');
    }
});
