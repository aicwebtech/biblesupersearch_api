enyo.kind({
    name: 'AICWEBTECH.Enyo.CKEDITOR.Editor',
    ckeditor: null,
    published:  { value: '' },
    components: [ {kind: 'enyo.TextArea', name: 'Editor', id: 'edit'} ],

    editorSettings: {
        height: 300,
        width: 1200,
    },

    bindings: [
        {from: 'value', to: '$.Editor.value', oneWay: false, transform: function(value, dir) {
            this.log('EDITOR', value, dir);
            
            if(dir == 1 && this.ckeditor) {
                this.ckeditor.setData(value); // feed it to the CKEDITOR
            }
            
            return value || '';
        }},
    ],

    create: function() {
        this.inherited(arguments);
        this.log();
        // this.render();
    },

    render: function() {
        this.inherited(arguments);
        this.log();
        // this.rendered();
    },

    rendered: function() {
        // this.inherited(arguments);
        this.log();

        var id = this.$.Editor.get('id');

        this.log('EDITRENDER', id);

        this.ckeditor = CKEDITOR.replace(id, this.editorSettings);

        this.ckeditor.on('change', enyo.bind(this, function() {
            this.$.Editor.set('value', this.ckeditor.getData());
        }));
    }, 
});