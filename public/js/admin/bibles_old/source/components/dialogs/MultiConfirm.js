enyo.kind({
    name: 'BibleManager.Components.Dialogs.MultiConfirm',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    // classes: 'dialogCenterText',
    alert: null,
    autoOpen: true,
    title: 'Export Bible',
    callback: null,
    confirmed: false,
    closing: false,
    props: null,
    action: null,
    nonReversible: false,
    items: [],

    components: [
        { components: [
            {tag: 'span', content: 'Are you sure you want to '},
            {tag: 'span', name: 'action'},
            {tag: 'span', content: ' the following Bibles?:'}
        ]},
        {name: 'NonReversible', showing: false, content: 'This action is NOT reversible!', classes: 'non_reversible'},
        {name: 'ListContainer', tag: 'ul'},
        // {tag: 'br'},
        // {
        //     components: [
        //         {name: 'overwrite', kind: 'enyo.Checkbox', id: 'bible_export_overwrite'},
        //         {tag: 'label', content: ' Overwrite existing file if needed.', attributes: {for: 'bible_export_overwrite'}}
        //     ]
        // }
    ],

    bindings: [
        {from: 'action', to: '$.action.content'},
        {from: 'nonReversible', to: '$.NonReversible.showing'}
    ],

    create: function() {
        this.inherited(arguments);

        this.setDialogOptions({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'OK',
                    icon: 'ui-icon-check',
                    click: enyo.bind(this, this.ok)
                },                      
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel',
                    click: enyo.bind(this, this.close)
                },            
            ]
        });

        this._resetProps();
    },

    open: function() {
        this._populateList();
        // $.ui.dialog.overlayInstances = 1;
        this.inherited(arguments);
        this.confirmed = false;
        this.closing = false;
    },
    close: function() {
        this.inherited(arguments);

        if(this.closing) {
            return;
        }

        this.closing = true;

        if(typeof this.callback == 'function') {
            this.callback(this.confirmed, enyo.clone(this.props));
        }
    },
    ok: function() {
        this.confirmed = true;
        this.close();
    },
    confirm: function(callback) {
        this._resetProps();
        this.confirmed = false;
        this.callback = (typeof callback == 'function') ? callback : null;
        this.open();
    },
    _resetProps: function() {
        this.set('props', {overwrite: 0});
    },
    _populateList: function() {
        this.$.ListContainer.destroyClientControls();

        this.items.forEach(function(item) {
            this.$.ListContainer.createComponent({
                tag: 'li',
                content: item.name
            });
        }, this);

        this.$.ListContainer.render();
    }
});