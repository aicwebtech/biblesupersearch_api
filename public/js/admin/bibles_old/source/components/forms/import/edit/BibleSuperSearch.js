enyo.kind({
    name: 'BibleManager.Components.Forms.Import.Edit.BibleSuperSearch',
    kind: 'BibleManager.Components.Forms.Import.Edit.Base',

    rendered: function() {
        this.inherited(arguments);
        this.$.module.setDisabled(true);
    }
});
