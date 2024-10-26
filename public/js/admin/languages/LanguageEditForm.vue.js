const template = `
    <div 
        max-width='600' 
    >
        <v-text-field 
            label='Name' 
            v-model='record.native_name'
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-text-field 
            label='Default Name' 
            v-model='record.iso_endonym'
            readonly
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-text-field 
            label='English Name' 
            v-model='record.name'
            density='compact'
            hide-details='auto'
        ></v-text-field>                    

        <v-text-field 
            label='Default English Name' 
            v-model='record.iso_name'
            readonly
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-textarea 
            label='Common Words - One word per line' 
            v-model='record.common_words'
            density='compact'
            hide-details='auto'
        ></v-textarea>

        <div>
            Add words to this list to prevent them from being used as search keywords.
            One word per line.
        </div>
    </div>
`;

export default {
    template: template,

    props: {
        record: {
            type: Object,
            default: {}
        }
    },
}
