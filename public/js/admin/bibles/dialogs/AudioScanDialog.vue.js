const tpl = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>Audio Bible Scan: {{bible?.name}}</v-card-title>
                <v-card-text>
                   Todo: insert description here ... <br /><br />
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn
                        text='scan'
                        :loading='scanLoading'
                        @click='scan()'
                    ></v-btn>

                    <v-btn
                        text='Close'
                        @click='handleCancel()'
                    ></v-btn>                    
                </v-card-actions>
            </v-card>

        </template>

    </v-dialog>
`;

export default {
    template: tpl,
    inject: ['bootstrap', 'defaultProps'],
    props: {
        bible: {
            type: Object,   
            default: null,
        },
    },
    data() {
        return {
            scanLoading: false,
            showing: false
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        },
        openDialog() {
            this.showing = true;
        },
        scan() {
            this.scanLoading = true;
            
            axios.post('/admin/bibles/audio/scan', {module: this.bible.module, bible_id: this.bible.id})
                .then(response => {
                    this.scanLoading = false;
                    this.$emit('scanSuccess');
                    this.closeDialog();
                })
                .catch(error => {
                    console.error('Error scanning audio:', error);
                    this.scanLoading = false;
                });
        },
    }
};