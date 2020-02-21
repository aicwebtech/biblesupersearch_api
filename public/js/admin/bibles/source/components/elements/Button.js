enyo.kind({
    name: 'BibleManager.Components.Elements.Button',
    kind: 'enyo.Button',
    isPrem: false,

    handlers: {
        ontap: 'handleTap'
    },

    create: function() {
        this.inherited(arguments);

        if(this.isPrem && !bootstrap.premToolsEnabled) {
            this.addClass('prem');
            // this.setContent( this.getContent() + ' (Premium)' );

            this.createComponent({tag: 'label', content: this.getContent()});
            this.createComponent({tag: 'span', content: '(Premium)'});
        }
    },

    handleTap: function(inSender, inEvent) {
        if(this.isPrem && !bootstrap.premToolsEnabled) {
            this.app.alertPrem();
            inEvent.preventDefault();
            return true;
        }
    }
});