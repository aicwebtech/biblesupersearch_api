enyo.kind({
    name: 'BibleManager.Components.Forms.Import.Config.Base',
    classes: 'import_config',
    configProps: {},
    disabled: false,

    components: [
        {tag: 'hr'}
    ],

    validate: function() {
        return true;
    },
    disabledChanged: function(was, is) {
        // disable all form elements here!
    }
});
