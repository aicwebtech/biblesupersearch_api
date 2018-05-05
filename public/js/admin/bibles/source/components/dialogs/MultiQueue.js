enyo.kind({
    name: 'BibleManager.Components.Dialogs.MultiQueue',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    // classes: 'dialogCenterText',
    alert: null,
    autoOpen: true,
    title: 'Processing',
    postData: {},
    action: null,
    actioning: null,
    queue: [],
    processed: [],
    errors: [],
    numItems: 0,
    numProcessed: 0,
    processing: false,
    processBarHandle: null,

    components: [
        {name: 'CurrentProcess', showing: false, components: [
            {name: 'ProcessBar'}, 
            {style: 'margin-top: 10px', components: [
                {tag: 'span', name: 'actioning'},
                {tag: 'span', content: ' \''},
                {tag: 'span', name: 'Bible'},
                {tag: 'span', content: '\''}
            ]}
        ]},
        {name: 'ErrorContainer', showing: false, components: [
            {style: 'margin-top: 10px', components: [
                {tag: 'span', content: 'Could not '},
                {tag: 'span', name: 'ErrorAction'},
                {tag: 'span', content: ' some Bibles:'}
            ]},
            {name: 'Errors', tag: 'ul'}
        ]}
    ],

    bindings: [
        {from: 'actioning', to: '$.actioning.content'},
        {from: 'action', to: '$.ErrorAction.content'},
        {from: 'processing', to: '$.CurrentProcess.showing'},
    ],

    create: function() {
        this.inherited(arguments);

        this.setDialogOptions({
            height: 300,
            width: 600,
            modal: true,
            autoOpen: false,
            closeOnEscape: false,
            buttons: [                    
                {
                    text: 'Ok',
                    icon: 'ui-icon-check',
                    click: enyo.bind(this, this.close)
                },            
            ]
        });
    },

    rendered: function() {
        this.inherited(arguments);

        if(this.$.ProcessBar.hasNode() && !this.processBarHandle) {
            this.processBarHandle = $(this.$.ProcessBar.hasNode());
            this.processBarHandle.progressbar({
                value: 0
            });
        }
    },

    queueChanged: function(was, is) {
        this.numProcessed = 0;
        this.processed = [];
        this.errors = [];
        this.numItems = is.length;
        this.$.ErrorContainer.set('showing', false);
    },
    open: function() {
        this.inherited(arguments);

        if(!this.processBarHandle) {
            this.render();
        }

        this.processBarHandle.progressbar('option', 'max', this.numItems);
        this.processBarHandle.progressbar('option', 'value', 0);
        this._processQueue();
    },
    close: function() {
        if(this.processing) {
            return;
        }

        this.inherited(arguments);
    },
    _incrementTimer: function() {
        this.numProcessed ++;
        this.processBarHandle.progressbar('option', 'value', this.numProcessed);
    },
    _processQueue: function() {
        if(this.queue.length == 0) {
            return this._finalizeQueue();
        }

        this.set('processing', true);
        var item = this.queue.shift();
        this.$.Bible.set('content', item.name);
        var url = '/admin/bibles/' + this.action + '/' + item.id;
        this.log('sim load url', url);
        this.postData._token = laravelCsrfToken;

        var ajax = new enyo.Ajax({
            url: url,
            method: 'POST',
            postBody: this.postData
        });

        ajax.response(this, function(inSender, inResponse) {
            if(!inResponse.success) {
                this.errors.push({
                    bible: item.name,
                    errors: inResponse.errors
                });
            }

            this.processed.push(item);
            this._incrementTimer();
            this._processQueue();
        });

        ajax.error(this, function(inSender, inResponse) {
            this.errors.push({
                bible: item.name,
                errors: ['Unknown Error']
            });

            // todo handle errors!
            this.processed.push(item);
            this._incrementTimer();
            this._processQueue();
        });
        
        ajax.go();

        // window.setTimeout(enyo.bind(this, function() {
        //     this.processed.push(item);
        //     this._incrementTimer();
        //     this._processQueue();
        // }), 2000);
    },
    _finalizeQueue: function() {
        this.set('processing', false);
        this.app.refreshGrid();

        if(this.errors.length > 0) {
            this.$.Errors.destroyClientControls();

            this.errors.forEach(function(item) {
                var comp = this.$.Errors.createComponent({
                    tag: 'li', 
                    components: [
                        {
                            kind: 'BibleManager.Components.ErrorItem',
                            bibleName: item.bible,
                            errors: item.errors
                        }
                    ]
                });
            }, this);

            this.$.Errors.render();
            this.$.ErrorContainer.set('showing', true);
        }
        else {
            this.close();
        }
    }
});