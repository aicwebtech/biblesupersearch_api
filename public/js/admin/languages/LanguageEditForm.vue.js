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
}
