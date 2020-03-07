enyo.kind({
    name: 'AICWEBTECH.Enyo.CKEDITOR.Editor',
    ckeditor: null,
    published:  { value: '' },
    components: [ {kind: 'enyo.TextArea', name: 'Editor', attributes: {contenteditable: 'true'}} ],

    editorSettings: {
        height: 300,
        width: 1200,
    },

    editorToolbarGroups: [
        {
          name: 'editing',
          groups: ['basicstyles', 'links']
        },
        {
          name: 'undo'
        },
        {
          name: 'clipboard',
          groups: ['selection', 'clipboard']
        }
    ],

    editorRemovePlugins: 'colorbutton,find,flash,font,' +
                'forms,iframe,image,newpage,removeformat,' +
                'smiley,specialchar,stylescombo,templates',

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
        this.inherited(arguments);
        this.log();

        var id = this.$.Editor.get('id');

        this.log('EDITRENDER', id);

        // var element = document.getElementById(id);
        // element.setAttribute('contenteditable', true);

        var settings = this.editorSettings;
        settings.toolbarGroups = this.editorToolbarGroups;
        settings.removePlugins = this.editorRemovePlugins;

        this.ckeditor = CKEDITOR.replace(id, settings);

        this.ckeditor.on('change', enyo.bind(this, function() {
            this.$.Editor.set('value', this.ckeditor.getData());
        }));

        // CKEDITOR.on('instanceCreated', enyo.bind(this, function(e) {
        //     console.log('instanceCreated');

        //     this.ckeditor.config.removePlugins = 'colorbutton,find,flash,font,' +
        //         'forms,iframe,image,newpage,removeformat,' +
        //         'smiley,specialchar,stylescombo,templates';

        //     this.ckeditor.config.toolbarGroups = this.editorToolbarGroups;
        // }));
    }, 
});