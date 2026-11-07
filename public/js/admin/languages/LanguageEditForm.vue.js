const template = `
    <div 
        max-width='600' 
    >
        
        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Name' 
                    v-model='record.native_name'
                    v-bind='defaultProps.texts'
                    :rules="[
                        v => !!v || 'Name is required',
                        v => (v && v.length <= 255) || 'Name must be less than 255 characters'
                    ]"
                ></v-text-field>
            </v-col>
        </v-row>   

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Default Name' 
                    v-model='record.iso_endonym'
                    v-bind='defaultProps.texts'
                    readonly
                ></v-text-field>
            </v-col>
        </v-row>   

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='English Name' 
                    v-model='record.name'
                    v-bind='defaultProps.texts'
                    :rules="[
                        v => !!v || 'English Name is required',
                        v => (v && v.length <= 255) || 'English Name must be less than 255 characters'
                    ]"
                ></v-text-field>        
            </v-col>
        </v-row>                

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field 
                    label='Default English Name' 
                    v-model='record.iso_name'
                    v-bind='defaultProps.texts'
                    readonly
                ></v-text-field>
            </v-col>
        </v-row>   

        <v-row v-bind='defaultProps.vrows' v-if='bootstrap.tts_enabled'>
            <v-col>
                <v-select
                    :items='ttsApiList'
                    label="Text to Speech API"
                    v-model='record.tts_api'
                    v-bind='defaultProps.selects'
                    :item-props='defaultProps.itemPropsFunction'
                ></v-select>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows' v-if='bootstrap.tts_enabled'>
            <v-col>
                <v-text-field 
                    :label="'Text to Speech Voice (' + ttsApiName + ')'"
                    v-model='record.tts_voice'
                    v-bind='defaultProps.texts'
                    :hint="ttsVoicePlaceHolder"
                    persistent-hint
                ></v-text-field>
            </v-col>
        </v-row>   

        <v-row v-bind='defaultProps.vrows' v-if='bootstrap.tts_enabled'>
            <v-col>
                <v-text-field 
                    :label="'Text to Speech Speed (' + ttsApiName + ')'"
                    v-model='record.tts_speed'
                    v-bind='defaultProps.texts'
                    :rules="[
                        v => (v === null || v === '' || (!isNaN(v) && v >= 0.25 && v <= 4.0)) || 'Speed must be a number between 0.25 and 4.0'
                    ]"
                    persistent-hint
                ></v-text-field>
            </v-col>
        </v-row>  

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-select
                    :items='strongsDictionaryList'
                    label="Default Strong's Dictionary"
                    v-model='record.strongs_dictionary'
                    v-bind='defaultProps.selects'
                    :item-props='defaultProps.itemPropsFunction'
                    hint="Used by the API when this language is requested and no dictionary is given."
                    persistent-hint
                >
                    <template v-slot:item='{ props, item }'>
                        <v-list-subheader v-if='item.raw.header'>{{ item.raw.title }}</v-list-subheader>
                        <v-list-item v-else v-bind='props'></v-list-item>
                    </template>
                </v-select>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-textarea
                    label='Common Words - One word per line'
                    v-model='record.common_words'
                    v-bind='defaultProps.textareas'
                    hint='Add words to this list to prevent them from being used as search keywords.  One word per line.'
                    persistent-hint
                ></v-textarea>
            </v-col>
        </v-row>   
    </div>
`;

export default {
    template: template,
    inject: ['bootstrap', 'defaultProps'],

    props: {
        record: {
            type: Object,
            default: {}
        }
    },

    computed: {
        ttsVoicePlaceHolder() {
            var tts_api = this.record.tts_api ? this.record.tts_api : this.bootstrap.tts_api_default;
            
            if(!tts_api) {
                return 'ERROR: No default TTS API configured';
            }

            var voice = this.record.tts_api_voices && this.record.tts_api_voices[tts_api] && this.record.tts_api_voices[tts_api].length > 0 ? this.record.tts_api_voices[tts_api] : null;

            if(voice) {
                return 'Leave blank for default of "' + voice + '"';
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
        ttsApiList() {
            var defaultApi = this.bootstrap.tts_api_default ? this.bootstrap.tts_apis.find(api => api.key === this.bootstrap.tts_api_default) : null;
            var list = [{value: null, title: 'Global Default: ' + (defaultApi ? defaultApi.name : '')}];

            for(var api of this.bootstrap.tts_apis) {
                list.push({value: api.key, title: api.name});
            }

            return list;
        },
        // Grouped by language, under a heading per language.  v-select (Vuetify 3.7) has no
        // subheader items of its own, so the headings are items flagged 'header', drawn by the
        // item slot as a non-selectable subheader.
        strongsDictionaryList() {
            var list = [{value: null, title: 'Use global default'}];
            var groups = {};

            for(var dict of (this.bootstrap.strongs_dictionaries || [])) {
                var language = (this.bootstrap.languages || []).find(lang => lang.code === dict.language);
                var name = language ? language.name : dict.language;

                groups[name] = groups[name] || [];
                groups[name].push({value: dict.module, title: dict.name});
            }

            for(var name of Object.keys(groups).sort((a, b) => a.localeCompare(b))) {
                list.push({value: '__header__' + name, title: name, header: true, disabled: true});
                list.push(...groups[name]);
            }

            return list;
        }
    }
}
