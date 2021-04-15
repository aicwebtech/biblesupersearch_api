enyo.kind({
    name: 'BibleManager.Components.Elements.Button',
    kind: 'enyo.Button',
    isPrem: false,
    requireDevTools: false,

    handlers: {
        ontap: 'handleTap'
    },

    create: function() {
        this.inherited(arguments);

        if(this.isPrem && !bootstrap.premToolsEnabled) {
            this.destroy(); // Hide premium button, for now
            return;

            this.addClass('prem');
            this.createComponent({tag: 'label', content: this.getContent()});
            this.createComponent({tag: 'span', content: '(Premium)'});
        }
        else if(this.requireDevTools) {
            if(bootstrap.devToolsEnabled) {
                this.addClass('dev');
                this.createComponent({tag: 'label', content: this.getContent()});
                this.createComponent({tag: 'span', content: '(Dev)'});
            }
            else {
                this.destroy();
            }
        }

        if(this.isBeta) {
            this.addClass('dev');
            this.createComponent({tag: 'label', content: this.getContent()});
            this.createComponent({tag: 'span', content: '(Beta)'});
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