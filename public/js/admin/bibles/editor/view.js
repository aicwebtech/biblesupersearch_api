enyo.kind({
    name: 'BibleEditor.View',
    gridHandle: null,
    selections: [],
    classes: 'bible_editor',
    title: null,

    handlers: {
        onSelectionsChanged: 'selectionsChanged'
    },

    components: [
        {
            classes: 'button_row',
            components: [
                {kind: 'enyo.Button', content: 'Save', ontap: 'save', classes: 'button'},
                {tag: 'span'},
                {kind: 'enyo.Button', content: 'Cancel', ontap: 'cancel', classes: 'button'}
            ]
        },
        {tag: 'h2', name: 'Title', classes: 'title'},
        {
            name: 'Form', 
            kind: 'BibleManager.Components.Forms.Edit',
            standalone: true,
        },
        {
            classes: 'button_row',
            components: [
                {kind: 'enyo.Button', content: 'Save', ontap: 'save', classes: 'button'},
                {tag: 'span'},
                {kind: 'enyo.Button', content: 'Cancel', ontap: 'cancel', classes: 'button'}
            ]
        },
        {name: 'Dialogs', components: [
            {name: 'Alert', kind: 'AICWEBTECH.Enyo.jQuery.Alert'},
            {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'},
            {name: 'Loading', kind: 'AICWEBTECH.Enyo.jQuery.Loading'}
        ]},
        {
            kind: 'enyo.Signals',
            onBibleInstall: 'bibleInstall',
            onBibleExport: 'bibleExport',
            onConfirmAction: 'confirmAction',
            onDoAction: 'doAction',
            onBibleTest: 'bibleTest',
            onViewDescription: 'viewDescription',
            onEdit: 'openEdit',
            onkeydown: 'handleKeyDown'
        }
    ],

    bindings: [
        {from: 'app.ajaxLoading', to: '$.Loading.showing'},
        {from: 'title', to: '$.Title.content', transform: function(value, dir) {
            document.title = value;

            return value;
        }},
        // {from: 'title', to: 'window.document.title'}
    ],

    create: function() {
        this.inherited(arguments);

        this.$.Form.set('pk', bootstrap.bibleId);
        this.$.Form.openLoad();
    },

    openEdit: function(inSender, inEvent) {
        // this.log(inEvent.id);
        // this.$.Edit.set('pk', inEvent.id);
        // this.$.Edit.openLoad();
    },
    save: function() {
        this.$.Form.save();
    },

    cancel: function() {
        this.close();
    },
    close: function() {
        // do something
    },
    handleKeyDown: function(inSender, inEvent) {
        this.log(inEvent);

        if(inEvent.ctrlKey && inEvent.key == 's') {
            this.save();
            inEvent.preventDefault();
        }
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
    },
    triggerSearch: function() {
        this.$.GridContainer.openSearchDialog();
    }
});
