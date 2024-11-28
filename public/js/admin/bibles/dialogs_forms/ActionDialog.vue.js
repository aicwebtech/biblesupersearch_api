const tpl = `

    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>{{title}}</v-card-title>
                <v-card-text>


                    <v-sheet v-if = '!confirmed'>
                        {{confirmText}} <br /><br />

                        <ul>
                            <li v-for='q in queue'>
                                {{q.name}}
                            </li>
                        </ul>
                    </v-sheet>


                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn
                        :text='confirmButtonLabel'
                        @click='handleOk()'
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
    props: {
        actions: {
            type: Array,
            default: null,
        },        
        // Queue of items to process
        queue: {
            type: Array,
            default: null
        },
        action: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            confirmed: false,
            showing: false
        }
    },
    computed: {
        title() {
            if(!this.selectedAction) {
                return null;
            }

            if(this.selectedAction.dialogTitle) {
                return this.selectedAction.dialogTitle;
            } else {
                return this.selectedAction.label + ' Bibles';
            }
        },
        confirmButtonLabel() {
            return this.selectedAction ? this.selectedAction.label : null;
        },
        selectedAction() {
            return this.actions.find(item => item.action == this.action);
        }, 
        confirmText() {
            if(!this.selectedAction) {
                return null;
            }

            if(this.selectedAction.confirmText) {
                return this.selectedAction.confirmText;
            } else {
                return 'Are you sure that you want to ' + this.selectedAction.action + ' the following Bibles?';
            }
        }
    },
    watch: {
        action(newValue, oldValue) {
            console.log('newValue', newValue);
            this.confirmed = false;

            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            this.showing = true;
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        handleOk() {

        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
};