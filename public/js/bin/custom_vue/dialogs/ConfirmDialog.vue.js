// NOT FINISHED (ok, it's started tho)

const template = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title :text='title' v-if='title'>{{title}}</v-card-title>

                <v-card-text class='vue_editdialog_body'>
                    {{message}}
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn
                        :text='cancelOption'
                        @click='handleCancel()'
                    ></v-btn>                    

                    <v-btn
                        :text='okOption'
                        @click='handleOk()'
                    ></v-btn>
                </v-card-actions>
            </v-card>
        </template>
    </v-dialog>
`;

export default {
    props: {
        // message: {
        //     type: String,
        //     required: false,
        //     default: null
        // },         
        title: {
            type: String,
            required: false,
            default: null
        },        
        okOption: {
            type: String,
            default: 'OK'
        },        
        cancelOption: {
            type: String,
            default: 'Cancel'
        },
    },
    template: template,
    data() {
        return { 
            showing: false,
            message: null,
            okCallback: null,
            cancelCallback: null
        }
    },
    watch: {
        message(was, is) {
            this.showing = true;
        }
    }, 
    computed: {

    },
    methods: {
        confirm(message, okCallback, cancelCallback) {
            this.okCallback = okCallback || null;
            this.cancelCallback = cancelCallback || null;
            this.message = message;
            this.showing = true;
        },
        handleCancel() {
            if(typeof this.cancelCallback == 'function') {
                this.cancelCallback();
            }

            this.$emit('onCancel');
            this.closeDialog();
        },        
        handleOk() {
            if(typeof this.okCallback == 'function') {
                this.okCallback();
            }

            this.$emit('onOk');
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
}
