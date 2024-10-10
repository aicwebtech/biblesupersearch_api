import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';

const template = `
    <EditDialog 
        v-model='showing'
        max-width='600' 
    >
        <v-text-field 
            label='Name' 
            _v-model='recording.native_name'
            density='compact'
        ></v-text-field>

        <v-text-field 
            label='Default Name' 
            _v-model='recording.iso_endonym'
            readonly
            density='compact'
        ></v-text-field>

        <v-text-field 
            label='English Name' 
            _v-model='recording.name'
            density='compact'
        ></v-text-field>                    

        <v-text-field 
            label='Default English Name' 
            _v-model='recording.iso_name'
            readonly
            density='compact'
        ></v-text-field>

        <v-textarea 
            label='Common Words - One word per line' 
            _v-model='recording.common_words'
            density='compact'
        ></v-textarea>

        <div>
            Add words to this list to prevent them from being used as search keywords.
            One word per line.
        </div>
    </EditDialog>
`;

export default {
    template: template,
    components: {
        EditDialog
    },
}
