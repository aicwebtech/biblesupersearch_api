enyo.kind({
    name: 'BibleManager.Components.Forms.Import.Edit.Base',
    kind: 'BibleManager.Components.Forms.Edit',
    // classes: 'import_form',
    disabledElements: [],
    readOnlyElements: [],

    create: function() {
        this.inherited(arguments);
        this.$.EnabledContainer.set('showing', false);

        this.disabledElements.forEach(function(item, key) {
            this.$[item] && this.$[item].set('disabled', true);
        }, this);        

        this.readOnlyElements.forEach(function(item, key) {
            this.$[item] && this.$[item].setAttribute('readonly', true);
        }, this);
    }
});
