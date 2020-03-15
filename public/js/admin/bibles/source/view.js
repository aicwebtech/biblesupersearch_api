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
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Install',
                    ontap: 'multiInstall',
                    action: 'install',
                    actioning: 'Installing'
                },
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Uninstall',
                    ontap: 'multiUninstall',
                    action: 'uninstall',
                    actioning: 'Uninstalling'
                },
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Enable',
                    ontap: 'multiEnable',
                    action: 'enable',
                    actioning: 'Enabling'
                },
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Disable',
                    ontap: 'multiDisable',
                    action: 'disable',
                    actioning: 'Disabling'
                },
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Test',
                    ontap: 'multiTest',
                    action: 'test',
                    actioning: 'Testing'
                },                
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Flag As For Research Only',
                    ontap: 'multiFlagResearch',
                    action: 'research',
                    actioning: 'Flagging'
                },                
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Unflag As For Research Only',
                    ontap: 'multiUnflagResearch',
                    action: 'unresearch',
                    actioning: 'Unflagging'
                },                
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Revert Changes',
                    ontap: 'multiRevert',
                    action: 'revert',
                    actioning: 'Reverting'
                },               
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Delete',
                    ontap: 'multiDelete',
                    action: 'revert',
                    actioning: 'Reverting'
                },
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Export Module File',
                    ontap: 'multiExport',
                    action: 'export',
                    actioning: 'Exporting',
                    requireDevTools: true
                },
                {
                    tag: 'button',
                    classes: 'button bulk',
                    content: 'Update Module File',
                    ontap: 'multiUpdate',
                    action: 'meta',
                    actioning: 'Updating Meta',
                    requireDevTools: true
                },
            ]},
            {name: 'Options', style: 'float: right', components: [
                // {tag: 'button', classes: 'button bulk', content: 'Auto Sort'},
                {kind: 'BibleManager.Components.Elements.Button', isPrem: true, classes: 'button bulk', content: 'Import Bible', ontap: 'tapImportBible'},
            ]},
            {style: 'clear: both'},
        ]},
        {name: 'GridContainer', kind: 'BibleManager.Components.Grid'},
        {name: 'Dialogs', components: [
            {name: 'Alert', kind: 'AICWEBTECH.Enyo.jQuery.Alert'},
            {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'},
            {name: 'Loading', kind: 'AICWEBTECH.Enyo.jQuery.Loading'},
            {name: 'Install', kind: 'BibleManager.Components.Dialogs.Install'},
            {name: 'Export', kind: 'BibleManager.Components.Dialogs.Export'},
            {name: 'Import', kind: 'BibleManager.Components.Dialogs.Import'},
            {name: 'Edit', kind: 'BibleManager.Components.Dialogs.Edit'},
            {name: 'Description', kind: 'BibleManager.Components.Dialogs.Description'},
            {name: 'MultiConfirm', kind: 'BibleManager.Components.Dialogs.MultiConfirm'},
            {name: 'MultiInstall', kind: 'BibleManager.Components.Dialogs.MultiInstall'},
            {name: 'MultiExport', kind: 'BibleManager.Components.Dialogs.MultiExport'},
            {name: 'MultiUpdate', kind: 'BibleManager.Components.Dialogs.MultiMetaUpdate'},
            {name: 'MultiQueue', kind: 'BibleManager.Components.Dialogs.MultiQueue'}
        ]},
        {
            kind: 'enyo.Signals',
            onBibleInstall: 'bibleInstall',
            onBibleExport: 'bibleExport',
            onConfirmAction: 'confirmAction',
            onDoAction: 'doAction',
            onViewDescription: 'viewDescription',
            onEdit: 'openEdit'
        }
    ],

    bindings: [
        {from: 'app.ajaxLoading', to: '$.Loading.showing'}
    ],

    create: function() {
        this.inherited(arguments);

        var multiTools = this.$.BulkActions.getClientControls();

        multiTools.forEach(function(tool) {
            if(tool.requireDevTools && tool.requireDevTools == true && !bootstrap.devToolsEnabled) {
                tool.destroy();
            }
        }, this);
    },

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
        var title = inEvent.title || null;
        var displayAction = inEvent.displayAction || inEvent.action;

        this.$.Confirm.set('title', title);

        var text = "Are you sure that you want to <b>" + displayAction + "</b><br /><br />'" + rowData.name + "'?";
        this.log('confirming', text);

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
            method: 'GET',
            headers: this.app.defaultAjaxHeaders
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
        this.app.set('ajaxLoading', true);
        postData._token = laravelCsrfToken;

        var ajax = new enyo.Ajax({
            url: url,
            method: 'POST',
            postBody: postData,
            headers: this.app.defaultAjaxHeaders
        });

        ajax.response(this, function(inSender, inResponse) {
            if(!inResponse.success) {
                var msg = 'An Error has occurred';
                this.app.alert(msg);
            }

            this.app.set('ajaxLoading', false);
            this.app.refreshGrid();
        });

        ajax.error(this, 'handleError');

        // ajax.error(this, function(inSender, inResponse) {
        //     console.log('ERROR', inSender, inResponse);
        //     var response = JSON.parse(inSender.xhrResponse.body);
        //     this.app.set('ajaxLoading', false);
        //     this.app._handleError(inSender, response);

        //     // var errors = response.errors || ['Unknown Error'];
        //     // var msg = 'An Error has occurred: <br /><ul><li>' + errors.join('</li><li>') + '</li></ul>';
        //     // this.app.alert(msg);
        // });

        ajax.go();
    },

    multiEnable: function(inSender, inEvent) {
        this._confirmMultiAction('enable', 'Enabling');
    },
    multiDisable: function(inSender, inEvent) {
        this._confirmMultiAction('disable', 'Disabling');
    },
    multiUninstall: function(inSender, inEvent) {
        this._confirmMultiAction('uninstall', 'Uninstalling');
    },
    multiFlagResearch: function(inSender, inEvent) {
        this._confirmMultiAction('research', 'Flag as "For Research Only"', 'flag');
    },    
    multiUnflagResearch: function(inSender, inEvent) {
        this._confirmMultiAction('unresearch', 'Unflagging as "For Research Only"', 'unflag');
    },    
    multiRevert: function(inSender, inEvent) {
        this._confirmMultiAction('revert', 'Reverting Changes to Bible Properties', 'revert changes to');
    },    
    multiDelete: function(inSender, inEvent) {
        this._confirmMultiAction('delete', 'Deleting Bible(s)', 'delete');
    },
    multiInstall: function(inSender, inEvent) {
        this._processSelections();

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }

        this.$.MultiInstall.set('items', enyo.clone(this.selections));

        this.$.MultiInstall.confirm(enyo.bind(this, function(confirmed, props) {
            if(confirmed) {
                this._multiActionHelper('install', 'Installing', props);
            }
        }));
    },
    multiExport: function(inSender, inEvent) {
        this._processSelections();

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }

        this.$.MultiExport.set('items', enyo.clone(this.selections));

        this.$.MultiExport.confirm(enyo.bind(this, function(confirmed, props) {
            if(confirmed) {
                this._multiActionHelper('export', 'Exporting', props);
            }
        }));
    },
    multiUpdate: function(inSender, inEvent) {
        this._processSelections();

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }

        this.$.MultiUpdate.set('items', enyo.clone(this.selections));

        this.$.MultiUpdate.confirm(enyo.bind(this, function(confirmed, props) {
            if(confirmed) {
                this._multiActionHelper('meta', 'Updating Meta', props);
            }
        }));
    },
    multiTest: function(inSender, inEvent) {
        this._multiAction('test', 'Testing', {}, false);
    },
    _multiAction: function(action, actioning, postData, closeWhenFinished) {
        this._processSelections();

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }

        this.$.MultiExport.set('items', enyo.clone(this.selections));
        this._multiActionHelper(action, actioning, {}, closeWhenFinished);
    },
    _confirmMultiAction: function(action, actioning, displayAction) {
        this._processSelections();
        var actioning = (typeof actioning == 'undefined') ? 'Processing' : actioning;
        var action    = (typeof action == 'undefined') ? 'process' : action;
        var displayAction = (typeof displayAction == 'undefined') ? action : displayAction;

        if(this.selections.length == 0) {
            this.$.Alert.alert('Nothing selected');
            return;
        }

        this.$.MultiConfirm.set('items', enyo.clone(this.selections));
        this.$.MultiConfirm.set('action', displayAction);
        this.$.MultiConfirm.set('title', actioning);

        this.$.MultiConfirm.confirm(enyo.bind(this, function(confirmed) {
            if(confirmed) {
                this._multiActionHelper(action, actioning, {});
            }
        }));
    },

    _multiActionHelper: function(action, actioning, postData, closeWhenFinished) {
        var closeWhenFinished = (typeof closeWhenFinished == 'undefined') ? true : closeWhenFinished;
        this.log(action, actioning, postData, closeWhenFinished);

        var actionLabel = action;

        if(action == 'meta') {
            actionLabel = 'update info on';
        }

        this.log('actionLabel', actionLabel);

        this.$.MultiQueue.set('action', action);
        this.$.MultiQueue.set('actionLabel', actionLabel);
        this.$.MultiQueue.set('actioning', actioning);
        this.$.MultiQueue.set('closeWhenFinished', closeWhenFinished);
        this.$.MultiQueue.set('postData', enyo.clone(postData));
        this.$.MultiQueue.set('queue', enyo.clone(this.selections));
        this.$.MultiQueue.open();
    },

    _processSelections: function() {
        this.selections = enyo.clone(this.$.GridContainer.getSelectionsWithName());
    },
    openEdit: function(inSender, inEvent) {
        this.log(inEvent.id);
        this.$.Edit.set('pk', inEvent.id);
        // this.log('PKQ', this.$.Edit.get('pk'));
        this.$.Edit.openLoad();
    },
    selectionsChanged: function(inSender, inEvent) {
        this.$.BulkActions.set('showing', inEvent.length ? true : false);
    },
    handleError: function(inSender, inResponse) {
        console.log('ERROR', inSender, inResponse);
        var response = JSON.parse(inSender.xhrResponse.body);
        this.app.set('ajaxLoading', false);

        this.app._errorHandler(inSender, response);

        // this.$.Alert.alert('An unknown error has occurred');
    },
    tapImportBible: function(inSender, inResponse) {
        this.$.Import.openLoad();
    }
});
