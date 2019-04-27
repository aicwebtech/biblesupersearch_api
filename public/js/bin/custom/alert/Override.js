
enyo.kind({
    name: 'AICWEBTECH.Enyo.jQuery.Override',

    components: [
        {name: 'Alert', kind: 'AICWEBTECH.Enyo.jQuery.Alert'},
        {name: 'Confirm', kind: 'AICWEBTECH.Enyo.jQuery.Confirm'},
    ],

    alert: function(text) {
        this.$.Alert.alert(text);
    },
    confirm: function(text, callback) {
        this.$.Confirm.confirm(text, callback);
    }
});

document.write("<div id='aicwebtech-overrides'></div>");
var override = new AICWEBTECH.Enyo.jQuery.Override().renderInto(document.getElementById('aicwebtech-overrides'));

AICWEBTECH.Custom = AICWEBTECH.Custom || {};
AICWEBTECH.Custom.Overrides = override;

        // <!-- Container for the app -->
        // <div id='biblesupersearch'></div>

        // <!-- This script must be AFTER your container div -->
        // <script>
        //     new AICWS.BibleSuperSearch.Application().renderInto(document.getElementById('biblesupersearch'));
        // </script>