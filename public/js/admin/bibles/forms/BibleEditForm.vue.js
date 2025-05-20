import '/js/bin/ckeditor5/build/ckeditor.js';

const template = `
    <div 
        max-width='1000' 
    >
        <v-text-field 
            label='Name' 
            v-model='record.name'
            density='compact'
            hide-details='auto'
            :rules='[v=> !!v || "Name is required", v => errorShow("name")]'
            @keydown='errorClear("name")'
        ></v-text-field>

        <v-text-field 
            label='Short Name' 
            v-model='record.shortname'
            density='compact'
            hide-details='auto'
            :rules='[v=> !!v || "Short Name is required", v => errorShow("shortname")]'
            @keydown='errorClear("shortname")'
        ></v-text-field>

        <v-text-field 
            label='Module' 
            v-model='record.module'
            density='compact'
            hide-details='auto'
            :disabled='record.id > 0'
            :rules='[
                v=> !!v || "Module is required", 
                v => /^[a-z]{2}([a-zA-Z0-9_]+)?$/.test(v) || "Module can contain only lowercase letters, numbers, and underscores. The first two characters must be letters",
                v => errorShow("module")
            ]'
            @keydown='errorClear("module")'
        ></v-text-field> 

        <v-row>
            <v-col>
                <v-text-field 
                    label='Rank' 
                    v-model='record.rank'
                    density='compact'
                    hide-details='auto'
                    hint='Customizable sort order.'
                    :rules='[
                        v => !v || /^-?[0-9]+$/.test(v) || "Rank must be an integer",  
                        v => errorShow("rank")
                    ]'
                    @keydown='errorClear("rank")'
                ></v-text-field>             
            </v-col>
            <v-col>Customizable sort order.</v-col>
        </v-row>

        <v-row>
            <v-col>
            <v-autocomplete
                :items='bootstrap.languages'
                label='Language'
                v-model='record.lang_short'
                :item-props='languageItemProps'
                :rules='[v=> !!v || "Language is required", v => errorShow("lang_short")]'
                @keydown='errorClear("lang_short")'
                clearable
                density='compact'
                hide-details='auto'
            ></v-autocomplete>    
            </v-col>
            <v-col>
                <v-text-field 
                    label='Language Code' 
                    v-model='record.lang_short'
                    density='compact'
                    hide-details='auto'
                    hint='2 or 3 characters'
                    @update:modelValue='langCodeChanged'
                ></v-text-field>  
            </v-col>
        </v-row>    

        <v-divider class='mt-2 mb-2 border-opacity-50'></v-divider>

        <v-row>
            <v-col></v-col>
            <v-col cols='2'>
                <v-switch
                    v-model='record.enabled'
                    label='Enabled'
                    hide-details='auto'
                    hint='Whether the Bible is enabled for use'
                    :false-value="0"
                    :true-value="1"
                    color='primary'
                    density='compact'
                ></v-switch>    
            </v-col>
            <v-col cols='2'>
                <v-switch
                    v-model='record.research'
                    hide-details='auto'
                    label='Research'
                    :false-value="0"
                    :true-value="1"
                    color='primary'
                    density='compact'
                ></v-switch>   
            </v-col>
            <v-col></v-col>
        </v-row>

        <v-divider class='mt-2 mb-2 border-opacity-50'></v-divider>

        <v-autocomplete
            :items='bootstrap.copyrights'
            label='Copyright'
            v-model='record.copyright_id'
            item-title='name'
            item-value='id'
            clearable
            density='compact'
            hide-details='auto'
            :rules='[v=> !!v || "Copyright is required"]'
            @click:clear='eventTest("cl:clear", $event)'
            @update:focused='eventTest("u:focused", $event)'
            @update:menu='eventTest("u:menu", $event)'
            @update:modelValue='copyRightChanged'
        ></v-autocomplete>

        <v-text-field 
            label='Copyright Owner' 
            v-model='record.owner'
            density='compact'
            hide-details='auto'
        ></v-text-field>          

        <v-text-field 
            label='Publisher' 
            v-model='record.publisher'
            density='compact'
            hide-details='auto'
        ></v-text-field>          

        <v-text-field 
            label='Publication Year' 
            v-model='record.year'
            density='compact'
            hide-details='auto'
        ></v-text-field>    

        <v-divider class='mt-2 mb-2 border-opacity-50'></v-divider>

        <label>Copyright Statement / Short Description</label>&nbsp; &nbsp;
        <small>Will be displayed with Bible on search results page.</small>

        <textarea
            ref='copyright_statement'
            label='copyright_statement' 
            v-model='record.copyright_statement'
            density='compact'
            hide-details='auto'
        ></textarea>

        <label v-if='showDefaultCopyright'>Default Copyright Statement</label>&nbsp; &nbsp;
        <small v-if='showDefaultCopyright'>Will be displayed if copyright statement is left blank.</small>

        <div 
            class='default-copyright-statement'
            v-html='defaultCopyrightStatement'
            v-if='showDefaultCopyright'
        ></div>

        <v-divider class='mt-2 mb-2 border-opacity-50'></v-divider>

        <label>Description</label>

        <textarea id='description'
            ref='description'
            label='Description' 
            v-model='record.description'
            density='compact'
            hide-details='auto'
        ></textarea>

    </div>
`;

/*
        <v-autocomplete
            :items='bootstrap.languages'
            label='Language Code'
            v-model='record.lang_short'
            :item-props='languageCodeProps'
            clearable
            density='compact'
            hide-details='auto'
        ></v-autocomplete>
        */

var ckeditorSettings = {
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
};

export default {
    template: template,
    inject: ['bootstrap'],

    components: {
        // Ckeditor
    },

    props: {
        record: {
            type: Object,
            default: {}
        },
        errors: {
            type: Object,
            default: {}
        }
    },
    data() {
        return {
            prevCopyrightId: null
        }
    },
    watch: {
        'record.copyright_id'(is, was) {
            this.prevCopyrightId = was || is;

            console.log('copyright_id', is, was);

            // if(!window.confirm('Please verify this is the correct copyright for this Bible')) {
            //     this.record.copyright_id = was;
            // }
        },
        'record.description'(is, was) {
            // this.descriptionEditor && this.descriptionEditor.setData(is);
        },
        'record.id'(is, was) {
            console.log('record id', is, was);
            this.descriptionEditor && this.descriptionEditor.setData(this.record.description || '');
            this.copyrightEditor && this.copyrightEditor.setData(this.record.copyright_statement || '');
        }
    },
    mounted() {
        // init ckeditors
        var t = this,
            dr = this.$refs.description,
            cr = this.$refs.copyright_statement;

        ClassicEditor
            .create( dr, ckeditorSettings )
            .then( newEditor => {
                t.descriptionEditor = newEditor;
                // t.descriptionEditor.setData(t.record.description);

                t.descriptionEditor.model.document.on('change:data', function() {
                    t.record.description = t.descriptionEditor.getData();
                });
            } )
            .catch( error => {
                console.error( error );
            } );            

        ClassicEditor
            .create( cr, ckeditorSettings )
            .then( newEditor => {
                t.copyrightEditor = newEditor;
                // t.copyrightEditor.setData(t.record.description);

                t.copyrightEditor.model.document.on('change:data', function() {
                    t.record.copyright_statement = t.copyrightEditor.getData();
                });
            } )
            .catch( error => {
                console.error( error );
            } );
    },
    computed: {
        showDefaultCopyright() {
            var cs = this.record.copyright_statement;
            return !cs || cs == ''; 
        },
        defaultCopyrightStatement() {
            var cr = bootstrap.copyrights.find(element => element.id == this.record.copyright_id);
            return cr ? cr.copyright_statement_processed : '';
        },
    },
    methods: {
        languageItemProps(item) {
            return item && item.code ? {
                title: item.code.toUpperCase() + ' ' + item.name,
                value: item.code
            } : {};
        },        
        languageCodeProps(item) {
            return item && item.code ? {
                title: item.code.toUpperCase(),
                value: item.code
            } : {};
        },
        langCodeChanged() {
            
        },
        copyRightChanged(event) {
            console.log('new', event);
            console.log('prev', this.prevCopyrightId);
            var prev = this.prevCopyrightId;
            var cr = bootstrap.copyrights.find((item) => item.id == event);
            var msg = 'Please verify this is the correct copyright for this Bible\n\n';

            msg += cr.name;

            msg += '\n\nWarning: Selecting the wrong copyright may put you at risk of civil or criminal penalties!';

            if(!window.confirm(msg)) {
                this.record.copyright_id = prev;
                this.prevCopyrightId = prev;
            }
        },
        errorShow(field) {
            if(!this.errors || !this.errors[field]) {
                return true;
            }

            return this.errors[field].join(', ');
        },
        errorClear(field) {
            if(this.errors && this.errors[field]) {
                delete this.errors[field];
            }
        },
        eventTest(type, event) {
            // console.log(type, event);

            // if(type == 'u:modelValue') {
            // }
        }
    }
}
