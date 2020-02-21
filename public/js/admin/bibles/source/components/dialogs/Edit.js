enyo.kind({
    name: 'BibleManager.Components.Dialogs.Edit',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    pk: null,
    formData: {},

    handlers: {
        onOpen: 'open',
        onClose: 'close'
    },

    components: [],

    bindings: [
        {from: 'pk', to: '$.Form.pk'},
    ],

    create: function() {
        this.inherited(arguments);

        var kind = bootstrap.premToolsEnabled ? 'BibleManager.Components.Forms.Edit' : 'BibleManager.Components.Forms.EditBasic';

        this.createComponent({name: 'Form', kind: kind});

        this.setDialogOptions({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'Save',
                    icon: 'ui-icon-check',
                    click: enyo.bind(this, this.save)
                },
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel',
                    click: enyo.bind(this, this.close)
                },
            ]
        });
    },

    openLoad: function() {
        this.$.Form.openLoad();
    },

    save: function() {
        this.$.Form.save();
    }
});
