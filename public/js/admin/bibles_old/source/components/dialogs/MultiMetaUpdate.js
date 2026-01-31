enyo.kind({
    name: 'BibleManager.Components.Dialogs.MultiMetaUpdate',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    // classes: 'dialogCenterText',
    alert: null,
    autoOpen: true,
    title: 'Update Bible Info',
    callback: null,
    confirmed: false,
    closing: false,
    props: null,
    action: null,
    items: [],

    components: [
        {content: 'Are you sure you want to update metadata'},
        {content: 'on the export files for the following Bibles?'},
        {tag: 'br'},
        {content: 'NOTE: This does not export the Bible text.'},
        {tag: 'br'},
        {content: 'This may take a while.'},
        {name: 'ListContainer', tag: 'ul'},
        {tag: 'br'},
        {
            components: [
                {name: 'create_new', kind: 'enyo.Checkbox', id: 'bible_multi_export_meta_new'},
                {tag: 'label', content: ' Create new module file if needed.', attributes: {for: 'bible_multi_export_meta_new'}}
            ]
        }
    ],

    bindings: [
        {from: 'props.create_new', to: '$.create_new.checked', oneWay: false, transform: function(value, dir) {
            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return value ? 1 : 0;
            }
        }}
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
        this.set('props', {create_new: 0});
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