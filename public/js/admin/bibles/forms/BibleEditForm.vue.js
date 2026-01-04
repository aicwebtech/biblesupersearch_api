import '../../../../js/bin/ckeditor5/build/ckeditor.js';

const template = `
    <div 
        max-width='600' 
    >
    
        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Name'
                    v-model='record.name'
                    v-bind='defaultProps.texts'
                    :rules='[v=> !!v || "Name is required", v => errorShow("name")]'
                    @keydown='errorClear("name")'
                    hint='Full display name of the Bible. It must be unique.'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Short Name' 
                    v-model='record.shortname'
                    v-bind='defaultProps.texts'
                    :rules='[v=> !!v || "Short Name is required", v => errorShow("shortname")]'
                    @keydown='errorClear("shortname")'
                    hint='Short display name of the Bible. It must be unique.'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Module' 
                    v-model='record.module'
                    v-bind='defaultProps.texts'
                    :disabled='record.id > 0'
                    hint='Module name is used to identify the Bible in the system. It must be unique.'
                    :rules='[
                        v=> !!v || "Module is required", 
                        v => /^[a-z]{2}([a-zA-Z0-9_]+)?$/.test(v) || "Module can contain only lowercase letters, numbers, and underscores. The first two characters must be letters",
                        v => errorShow("module")
                    ]'
                    @keydown='errorClear("module")'
                ></v-text-field> 
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Rank' 
                    v-model='record.rank'
                    v-bind='defaultProps.texts'
                    hint='Customizable sort order, must be an integer number.'
                    :rules='[
                        v => !v || /^-?[0-9]+$/.test(v) || "Rank must be an integer",  
                        v => errorShow("rank")
                    ]'
                    @keydown='errorClear("rank")'
                ></v-text-field>             
            </v-col>
        </v-row>
        <v-row v-bind='defaultProps.vrows'>
            <v-col cols='9'>
                <v-autocomplete
                    :items='bootstrap.languages'
                    label='Language'
                    v-model='record.lang_short'
                    v-bind='defaultProps.selects'
                    :item-props='languageItemProps'
                    :rules='[v=> !!v || "Language is required", v => errorShow("lang_short")]'
                    hint="Tip: entering a code will cause the language to be selected, and vice-versa."
                    @keydown='errorClear("lang_short")'
                ></v-autocomplete>    
            </v-col>
            <v-col cols='3'>
                <v-text-field 
                    label='Code' 
                    v-model='record.lang_short'
                    v-bind='defaultProps.texts'
                    hint='ISO-639-1 code if exists, otherwise ISO 639-2 code'
                    @update:modelValue='langCodeChanged'
                    :rules='[v => !v || /^[a-z]{2,3}$/.test(v) || "Must be 2 or 3 lowercase letters", v => errorShow("lang_short")]'
                ></v-text-field>  
            </v-col>
        </v-row>    

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-switch
                    class='ml-3'
                    v-model='record.enabled'
                    label='Enabled - whether the Bible is enabled for use'
                    v-bind='defaultProps.switches'
                ></v-switch>    
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-switch
                    class='ml-3'
                    v-model='record.research'
                    label="Research - select this if you don't reccomend this Bible for general use."
                    v-bind='defaultProps.switches'
                ></v-switch>    
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows' v-if='bootstrap.audio_enabled'>
            <v-col>
                <v-switch
                    class='ml-3'
                    v-model='record.audio_enable'
                    label='Audio Enabled - whether the Bible is enabled for audio'
                    v-bind='defaultProps.switches'
                ></v-switch>    
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows' v-if='record.audio_enable'>
            <v-col>
                <v-radio-group v-model='record.audio_structure' inline class='pl-4'>
                    <v-radio
                        label='By Chapter'
                        value='chapters'
                        v-bind='defaultProps.radios'
                    ></v-radio>   
                    <v-radio
                        label='By Verse'
                        value='verses'
                        v-bind='defaultProps.radios'
                    ></v-radio>      
                    <v-radio
                        label='By Both Verse and Chapter'
                        value='both'
                        v-bind='defaultProps.radios'
                    ></v-radio>
                </v-radio-group>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows' v-if='bootstrap.tts_enabled && record.audio_enable'>
            <v-col>
                <v-switch
                    class='ml-3'
                    v-model='record.tts_enable'
                    label='Text to Speech - whether the Bible is enabled for text to speech conversion'
                    v-bind='defaultProps.switches'
                ></v-switch>    
            </v-col>
        </v-row>

        <v-row 
            v-bind='defaultProps.vrows' 
            v-if='bootstrap.tts_enabled && record.audio_enable && record.tts_enable && ttsApiRequiresVoice'
        >
            <v-col>
                <v-text-field 
                    :label='"Text to Speech Voice (" + ttsApiName + ")"' 
                    v-model='record.tts_voice'
                    v-bind='defaultProps.texts'
                    :rrules='[v=> !!v || "TTS Voice is required", v => errorShow("shortname")]'
                    :hint='ttsVoicePlaceHolder'
                    persistent-hint
                ></v-text-field>  
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows' v-if='false'>
            <v-col></v-col>
            <v-col>
                <v-switch
                    v-model='record.enabled'
                    label='Enabled'
                    hint='Whether the Bible is enabled for use'
                    v-bind='defaultProps.switches'
                ></v-switch>    
            </v-col>
            <v-col>
                <v-switch
                    v-model='record.research'
                    label='Research'
                    v-bind='defaultProps.switches'
                ></v-switch>   
            </v-col>
            <v-col></v-col>
        </v-row>

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-autocomplete
                    :items='bootstrap.copyrights'
                    label='Copyright'
                    v-model='record.copyright_id'
                    v-bind='defaultProps.selects'
                    :item-props='defaultProps.itemPropsFunction'
                    item-title='name'
                    item-value='id'
                    :rules='[v=> !!v || "Copyright is required"]'
                    @click:clear='eventTest("cl:clear", $event)'
                    @update:focused='eventTest("u:focused", $event)'
                    @update:menu='eventTest("u:menu", $event)'
                    @update:modelValue='copyRightChanged'
                ></v-autocomplete>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Copyright Owner' 
                    v-model='record.owner'
                    v-bind='defaultProps.texts'
                ></v-text-field>      
            </v-col>
        </v-row>    

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Publisher' 
                    v-model='record.publisher'
                    v-bind='defaultProps.texts'
                ></v-text-field>    
            </v-col>
        </v-row>      

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Publication Year' 
                    v-model='record.year'
                    v-bind='defaultProps.texts'
                ></v-text-field>    
            </v-col>
        </v-row>

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <label>Copyright Statement / Short Description</label><br />
        <small>This will be displayed with Bible on search results page.</small>
        <br />

        <textarea
            ref='copyright_statement'
            label='copyright_statement' 
            v-model='record.copyright_statement'
            v-bind='defaultProps.texts'
        ></textarea>

        <v-spacer v-if='showDefaultCopyright' class='pt-2' />
        <label v-if='showDefaultCopyright'>Default Copyright Statement</label>
        <br v-if='showDefaultCopyright' />
        <small v-if='showDefaultCopyright'>This will be displayed instead if copyright statement is left blank.</small>
        <br v-if='showDefaultCopyright' />
        
        <div 
            class='default-copyright-statement'
            v-html='defaultCopyrightStatement'
            v-if='showDefaultCopyright'
        ></div>

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <label>Description</label><br />
        <small>Full description of this Bible.</small>
        <br />

        <textarea id='description'
            ref='description'
            label='Description' 
            v-model='record.description'
            density='compact'
            hide-details='auto'
        ></textarea>

    </div>
`;

export default {
    template: template,
    inject: ['bootstrap', 'defaultProps'],

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
            .create( dr, this.defaultProps.ckeditor.settings )
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
            .create( cr, this.defaultProps.ckeditor.settings )
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
        language() {
            return bootstrap.languages.find(element => element.code == this.record.lang_short);
        },
        ttsVoicePlaceHolder() {
            if(!this.language) {
                return null;
            }
            
            var tts_api = this.record.tts_api || this.language.tts_api || this.bootstrap.tts_api_default;
            
            if(!tts_api) {
                return 'ERROR: No default TTS API configured';
            }

            var voices = this.language.tts_api_voices || null;

            var voice = voices && voices[tts_api] && voices[tts_api].length > 0 ? voices[tts_api] : null;

            if(voice) {
                return 'Leave blank for defalt of "' + voice + '"';
            } else {
                return 'ERROR: No default voice configured for this language / TTS API';
            }
        },
        ttsApiName() {
            var tts_api = this.record.tts_api ? this.record.tts_api : this.bootstrap.tts_api_default;
            
            if(!tts_api) {
                return 'ERROR: No default TTS API configured';
            }

            return this.bootstrap.tts_apis.find(api => api.key === tts_api).name || 'Unknown';
        },
        ttsApiRequiresVoice() {
            var tts_api = this.record.tts_api ? this.record.tts_api : this.bootstrap.tts_api_default;
            
            if(!tts_api) {
                return false;
            }

            return this.bootstrap.tts_apis.find(api => api.key === tts_api).requires_voice || false;
        }
    },
    methods: {
        languageItemProps(item) {
            return item && item.code ? {
                ...this.defaultProps.items,
                ... {
                    title: item.code.toUpperCase() + ' ' + item.name,
                    value: item.code,
                }
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
