enyo.kind({
    name: 'BibleEditor.Application',
    kind: 'enyo.Application',

    view: 'BibleEditor.View',
    renderTarget: 'enyo_container',
    ajaxLoading: false,

    defaultAjaxHeaders: {
        'X-Requested-With' : 'XMLHttpRequest'
    },

    create: function() {
        this.inherited(arguments);
        this.defaultAjaxHeaders['X-CSRF-TOKEN'] = laravelCsrfToken;
    },
    ajaxLoadingChanged: function(was, is) {

    },
    alert: function(text) {
        this.view.$.Alert.alert(text);
    },
    confirm: function(text, callback) {
        this.view.$.Confirm.confirm(text, callback);
    },
    alertPrem: function() {
        var msg = [
            'This is a premium feature.',
            "Buy your premium license at",
            "",
            "<a href='https://www.biblesupersearch/premium'>www.biblesupersearch/premium</a>"
        ];

        this.alert( msg.join('<br />') );
    },
    _errorHandler: function(inSender, inResponse) {
        this.log(inSender, inResponse);

        // Treat 405 (method not allowed) as a session time out
        if(inSender && inSender.xhrResponse && inSender.xhrResponse.status == 405) {
            inResponse = {
                message: 'Your session has timed out, please log in again'
            }
        };

        var msg = inResponse.message || 'An Error has occurred';

        if(inResponse.errors) {
            msg += '<br /><br />';

            for(field in inResponse.errors) {
                var err = inResponse.errors[field];

                if(Array.isArray(err)) {
                    err.forEach(function(e) {
                        if(typeof e == 'string') {
                            msg += e + '<br />';
                        }
                    });
                }
                else if(typeof err == 'string') {
                    msg += err + '<br />';
                }
            }
        }

        this.alert(msg);
    }

});
