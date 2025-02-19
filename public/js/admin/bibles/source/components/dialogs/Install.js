enyo.kind({
    name: 'BibleManager.Components.Dialogs.Install',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    classes: 'dialogCenterText',
    alert: null,
    autoOpen: true,
    title: 'Install Bible',
    callback: null,
    confirmed: false,
    props: null,
    bible: null,

    components: [
        { components: [
            {tag: 'span', content: 'Install '},
            {tag: 'span', name: 'Bible'},
            {tag: 'span', content: '?'}
        ]},
        {tag: 'br'},
        {
            components: [
                {name: 'enable', kind: 'enyo.Checkbox', id: 'bible_install_enable'},
                {tag: 'label', content: ' Enable', attributes: {for: 'bible_install_enable'}}
            ]
        }
    ],

    bindings: [
        {from: 'props.enable', to: '$.enable.checked', oneWay: false, transform: function(value, dir) {
            // this.log('enable', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return value ? 1 : 0;
            }
        }},
        {from: 'bible', to: '$.Bible.content'}
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
        this.inherited(arguments);
        this.confirmed = false;
    },
    close: function() {
        this.inherited(arguments);

        if(typeof this.callback == 'function' && !this.debounce) {
            this.debounce = window.setTimeout( enyo.bind(this, this._clearDebounce), 100);
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
        this.set('props', {enable: 1});
    },
    _clearDebounce: function() {
        this.debounce = null;
    }
});