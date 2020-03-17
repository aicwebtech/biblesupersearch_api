enyo.kind({
    name: 'BibleManager.Components.Forms.Import.Edit.Base',
    kind: 'BibleManager.Components.Forms.Edit',

    disabledElements: [],

    create: function() {
        this.inherited(arguments);

        this.$.EnabledContainer.set('showing', false);
    }
});
