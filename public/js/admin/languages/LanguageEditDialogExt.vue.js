import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';

const template = `
    <div 
        max-width='600' 
    >
        <v-text-field 
            label='Name' 
            v-model='recording.native_name'
            density='compact'
        ></v-text-field>

        <v-text-field 
            label='Default Name' 
            v-model='recording.iso_endonym'
            readonly
            density='compact'
        ></v-text-field>

        <v-text-field 
            label='English Name' 
            v-model='recording.name'
            density='compact'
        ></v-text-field>                    

        <v-text-field 
            label='Default English Name' 
            v-model='recording.iso_name'
            readonly
            density='compact'
        ></v-text-field>

        <v-textarea 
            label='Common Words - One word per line' 
            v-model='recording.common_words'
            density='compact'
        ></v-textarea>

        <div>
            Add words to this list to prevent them from being used as search keywords.
            One word per line.
        </div>
    </div>
`;

export default {
    // template: template,
    extends: EditDialog,
    // components: {
    //     EditDialog
    // },
    // computed: {
    //     recording() {
    //         return EditDialog.recording();
    //     }
    // }
}
