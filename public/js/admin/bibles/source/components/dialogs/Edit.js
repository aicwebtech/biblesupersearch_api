enyo.kind({
    name: 'BibleManager.Components.Dialogs.Edit',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    pk: null,
    formData: {},
    classes: 'edit_dialog',
    components: [],

    handlers: {
        onOpen: 'open',
        onClose: 'close'
    },

    bindings: [
        {from: 'pk', to: '$.Form.pk'},
    ],

    create: function() {
        this.inherited(arguments);
        var kind = bootstrap.premToolsEnabled ? 'BibleManager.Components.Forms.Edit' : 'BibleManager.Components.Forms.EditBasic';
        this.createComponent({name: 'Form', kind: kind}).render();

        this.setDialogOptions({
            height: bootstrap.premToolsEnabled ? '750' : 'auto',
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

    render: function() {
        this.inherited(arguments);
        this.log();
        this.$.Form.render();
    },
    openLoad: function() {
        this.$.Form.openLoad();
    },
    save: function() {
        this.$.Form.save();
    }
});
