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
                        v-if='mode != "alert"'
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
            callback: null,
            okCallback: null,
            cancelCallback: null,
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
        /* Public Methods */

        alert(message) {
            this.resetData();
            this.modeInit('alert', message);
        },
        confirm(message, okCallback, cancelCallback) {
            this.resetData();
            this.okCallback = okCallback || null;
            this.cancelCallback = cancelCallback || null;
            this.modeInit('confirm', message);
        },
        confirmSingle(message, callback) {
            this.resetData();
            this.callback = callback || null;
            this.modeInit('confirm', message);
        },
        
        /* Private Methods */
        handleCancel() {
            if(typeof this.cancelCallback == 'function') {
                this.cancelCallback();
            }

            if(typeof this.callback == 'function') {
                this.callback(false);
            }

            this.$emit('onCancel');
            this.closeDialog();
        },        
        handleOk() {
            if(typeof this.okCallback == 'function') {
                this.okCallback();
            }

            if(typeof this.callback == 'function') {
                this.callback(true);
            }

            this.$emit('onOk');
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        },
        resetData() {
            this.okCallback = null;
            this.cancelCallback = null;
            this.callback = null;
        },
        modeInit(mode, message) {
            this.mode = mode;
            this.message = message;
            this.showing = true;
        }
    }
}
