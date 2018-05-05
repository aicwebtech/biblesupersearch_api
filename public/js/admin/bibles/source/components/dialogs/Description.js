enyo.kind({
    name: 'BibleManager.Components.Dialogs.Description',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    text: null,
    title: 'Bible Description',

    components: [
        {name: 'Container', allowHtml: true}
    ], 

    bindings: [
        {from: 'text', to: '$.Container.content'}
    ],

    create: function() {
        this.inherited(arguments);

        this.setDialogOptions({
            height: 400,
            width: 800,
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'OK',
                    icon: 'ui-icon-check',
                    click: enyo.bind(this, this.close)
                },            
            ]
        });

        this.$.Container.set('content', this.text);
    }
});
