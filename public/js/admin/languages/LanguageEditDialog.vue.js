
const template = `
    <v-dialog max-width='600'

    >
        <template v-slot:default="{ isActive }">
            <v-card title='Edit Language'>
                <v-card-text>
                    Form elements go here ....
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn
                        text='Cancel'
                        @click='handleCancel()'
                    ></v-btn>                    

                    <v-btn
                        text='Save'
                        @click='handleSave()'
                    ></v-btn>
                </v-card-actions>
            </v-card>
        </template>
    </v-dialog>
`;

export default {
    data() {
        return { count: 1 }
    },
    template: template,
    methods: {
        handleCancel() {
            this.isActive.value = false;
            // alert('cancel');
        },
        handleSave() {
            this.handleCancel();
        }
    }
}
