const template = `
    <div 
        max-width='600' 
    >
        <v-text-field 
            label='Name' 
            v-model='record.name'
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-text-field 
            label='Short Name' 
            v-model='record.shortname'
            readonly
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-text-field 
            label='Module' 
            v-model='record.module'
            density='compact'
            hide-details='auto'
        ></v-text-field>      

        <v-switch
            v-model='record.enabled'
            label='Enabled'
            hide-details='auto'
            hint='Whether the Bible is enabled for use'
            :false-value="0"
            :true-value="1"
        ></v-switch>              
        
        <v-switch
            v-model='record.research'
            hide-details='auto'
            label='Research'
            :false-value="0"
            :true-value="1"
        ></v-switch>    

        <v-text-field 
            label='Rank' 
            v-model='record.rank'
            density='compact'
            hide-details='auto'
            hint='Customizable sort order.'
        ></v-text-field>             

        <v-autocomplete
            :items='bootstrap.languages'
            label='Language'
            v-model='record.lang_short'
            :item-props='languageItemProps'
            clearable
        ></v-autocomplete>

        <v-textarea 
            label='Description' 
            v-model='record.description'
            density='compact'
            hide-details='auto'
        ></v-textarea>
    </div>
`;

export default {
    template: template,
    inject: ['bootstrap'],

    props: {
        record: {
            type: Object,
            default: {}
        }
    },
    methods: {
        languageItemProps(item) {
            return {
                title: item.code.toUpperCase() + ' ' + item.name,
                value: item.code
            }
        }
    }
}
