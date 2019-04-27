enyo.kind({
    name: 'BibleManager.Components.ErrorItem',
    bibleName: null,
    errors: [],

    components: [
        {name: 'BibleName'},
        {tag: 'ul', name: 'Errors'}
    ],

    bindings: [
        {from: 'bibleName', to: '$.BibleName.content'}
    ],

    create: function() {
        this.inherited(arguments);

        this.errors.forEach(function(error) {
            this.log('error', error);

            this.$.Errors.createComponent({
                tag: 'li',
                content: error
            })
        }, this);
    }
});
