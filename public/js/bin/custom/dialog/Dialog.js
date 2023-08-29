enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.Dialog',
    handle: null,

    published: {
        showing: false,
        title: null,
        closeable: true,

        dialogOptions: {
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'OK',
                    icon: 'ui-icon-check'
                    // click: your click handler here
                },            
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel'
                    // click: 
                },

            ]
        },
    },

    create: function() {
        this.inherited(arguments);
        this.dialogOptions.beforeClose = enyo.bind(this, this.close);

        this.titleChanged(null, this.title);
    },

    rendered: function() {
        this.inherited(arguments);

        if(this.hasNode() && this.handle == null) {
            this.handle = $(this.hasNode()).dialog(this.dialogOptions);
            this.titleChanged(null, this.title);

            if(this.showing) {
                this.handle.dialog('open');
            }
        }
    },

    dialogOptionsChanged: function(was, is) {
        this.dialogOptions.beforeClose = enyo.bind(this, this.close);
        this.handle && this.handle.dialog('option', this.dialogOptions);
    },
    titleChanged: function(was, is) {
        this.dialogOptions.title = is;
        this.dialogOptions.dialogClass = (is) ? '' : 'dialogNoTitle';

        if(this.handle) {        
            if(is) {
                this.handle.dialog('option', 'dialogClass', '');
                this.handle.dialog('option', 'title', is);
            }
            else {
                this.handle.dialog('option', 'dialogClass', 'dialogNoTitle');
            }
        }
    },
    showingChanged: function(was, is) {
        if(this.handle && this.handle.dialog) {        
            if(is) {
                this.handle.dialog('open');
                this.handle.scrollTop(0);
            }
            else {
                this.handle.dialog('close');
            }
        }
    },
    close: function() {
        if(!this.closeable) {
            // return false;
        }

        this.set('showing', false);
        return true;
    },
    open: function() {
        var dialogClass = (this.title) ? '' : 'dialogNoTitle';
        this.handle.dialog('option', 'dialogClass', dialogClass);
        this.set('showing', true);
    }
});