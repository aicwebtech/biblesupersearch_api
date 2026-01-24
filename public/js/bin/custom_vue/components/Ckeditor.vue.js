import '../../../bin/ckeditor5/build/ckeditor.js';

const template = `
    <v-textarea ref='ta' label='ckeditor' id='thing'
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    ></v-textarea>
`;

export default {
    template: template,
    props: ['modelValue'],
    emits: ['update:modelValue'],
    mounted() {
        this.initEditor();
    },
    data() {
        return {
            editor: null
        }
    },
    methods: {
        initEditor() {
            var t = this,
                ref = this.$refs.ta;
                
            ref = document.getElementById('thing');

            ClassicEditor
                .create( ref, {
                    height: 300,
                    width: 600,
                    link: {
                        decorators: {
                            openInNewTab: {
                                mode: 'manual',
                                label: 'Open in a new tab',
                                attributes: {
                                    target: '_blank',
                                    rel: 'noopener noreferrer'
                                }
                            }
                        }
                    },
                    toolbar: {
                        items: [
                            'findAndReplace', 'selectAll', '|',
                            'heading', '|',
                            'bold', 'italic', 'strikethrough', 'underline', 'subscript', 'superscript', 'removeFormat', '|',
                            'bulletedList', 'numberedList', '|',
                            'outdent', 'indent', '|',
                            'undo', 'redo',
                            '-',
                            'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
                            'alignment', '|',
                            //'link', 'insertImage', 'blockQuote', 'insertTable', '|',
                            'specialCharacters', 'horizontalLine', 'pageBreak', '|',
                            'sourceEditing'
                        ],
                        shouldNotGroupWhenFull: true
                    },
                } )
                .then( newEditor => {
                    t.editor = newEditor;

                    // :todo how to access the model value?

                    // if(t.modelValue) {
                    //     t.editor.setData(t.modelValue);
                    // }

                    t.editor.model.document.on('change:data', function() {
                        // um, can't back set prop
                    });

                    // t.$description.model.document.on('change:data', enyo.bind(t, function() {
                    //     t.$.description.set('value', t.$description.getData());
                    // }));
                } )
                .catch( error => {
                    console.error( error );
                } );
        }
    }
}