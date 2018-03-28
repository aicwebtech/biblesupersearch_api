enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.Dialog',
    handle: null,

    published: {
        showing: false,
        title: null,

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
        this.dialogOptions.close = enyo.bind(this, this.close);

        this.titleChanged(null, this.title);
    },

    rendered: function() {
        this.inherited(arguments);

        if(this.hasNode() && this.handle == null) {
            this.handle = $(this.hasNode()).dialog(this.dialogOptions);

            if(this.showing) {
                this.handle.dialog('open');
            }
        }
    },

    dialogOptionsChanged: function(was, is) {

    },
    titleChanged: function(was, is) {
        this.dialogOptions.title = is;
        this.dialogOptions.dialogClass = (is) ? '' : 'dialogNoTitle';

        this.log(this.dialogOptions);

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
        if(is) {
            this.handle.dialog('open');
        }
        else {
            this.handle.dialog('close');
        }
    },
    close: function() {
        this.set('showing', false);
    },
    open: function() {
        this.set('showing', true);
    }
});