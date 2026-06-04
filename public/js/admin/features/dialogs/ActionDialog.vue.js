const tpl = `

    <v-dialog 
        v-model='showing'
        @update:modelValue='handleShowingChange($event)'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>{{title}}</v-card-title>
                <v-card-text class='vue_dialog_body'>
                    <v-sheet v-if = '!confirmed'>
                        {{confirmText}} <br /><br />

                        <ul class='pl-10'>
                            <li v-for='q in queue'>
                                {{q.name}}
                            </li>
                        </ul>

                        <v-switch
                            v-if='action == "install"'
                            v-model='enable'
                            label='Enable'
                            color='primary'
                        />
                    </v-sheet>
                    <v-sheet v-else-if='queueProcessing'>
                        {{actioningLabel}} {{queueItemCurrent.name}}

                        <v-progress-linear 
                            v-model='queueItemsProcessedPercent'
                            color='secondary'
                            height='10'
                        ></v-progress-linear>
                    </v-sheet>
                    <v-sheet v-if='confirmed && queueErrors.length > 0' background-color='warn' class='mt-10'>
                        <h3>Errors:</h3> 
                        <v-list :items='queueErrors' hide-details> 
                            <v-list-item v-for='e in queueErrors'
                                :title='e.title'
                                :subtitle='e.subtitle'
                            ></v-list-item>
                        </v-list>
                    </v-sheet>
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn v-if='!confirmed'
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
    inject: ['bootstrap'],
    props: {
        actions: {
            type: Array,
            default: null,
        },        
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
            showing: false,
            queueItemsTotal: 0,
            queueItemsProcessed: 0,
            queueItemCurrent: null,
            queueAbort: false,
            queueProcessing: false,
            queueLoading: false,
            queueFinished: false,
            queueErrors: [],
            queueInternal: [],
            enable: false,
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
                return this.selectedAction.label + ' Features';
            }
        },
        confirmButtonLabel() {
            return this.selectedAction ? this.selectedAction.label : null;
        },
        selectedAction() {
            return this.actions.find(item => item.action == this.action) || null;
        }, 
        actioningLabel() {
            return this.selectedAction ? this.selectedAction.actioning : null;
        },
        confirmText() {
            if(!this.selectedAction) {
                return null;
            }

            if(this.selectedAction.confirmText) {
                return this.selectedAction.confirmText;
            } else {
                return 'Are you sure that you want to ' + this.selectedAction.action + ' the following features?';
            }
        },
        queueItemsProcessedPercent() {
            if(this.queueItemsTotal < 1) {
                return 0;
            }

            return this.queueItemsProcessed * 100 / this.queueItemsTotal;
        }
    },
    watch: {
        action(newValue, oldValue) {
            this.clearForm();

            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            this.showing = true;
        }
    },
    methods: {
        handleCancel() {
            if(this.queueProcessing) {
                this.queueAbort = true;
            } else {
                this.closeDialog();
            }
        },
        handleOk() {
            this.confirmed = true;
            this.queueProcessStart();
        },
        handleShowingChange(e) {
            if(!e) {
                this.closeDialog();
            } else {
                this.clearForm();
            }
        },
        clearForm() {
            this.confirmed = false;
            this.enable = false;
        },
        queueProcessStart() {
            this.queueItemsTotal = this.queue.length;
            this.queueItemsProcessed = 0;
            this.queueAbort = false;
            this.queueProcessing = true;
            this.queueFinished = false;
            this.queueErrors = [];
            this.queueInternal = [...this.queue];
            this.queueProcessNext();
        },
        queueProcessNext() {
            if(this.queueAbort) {
                this.closeDialog();
                return;
            }

            if(this.queueInternal.length == 0) {
                this.queueProcessEnd();
                return;
            }

            this.queueItemCurrent = this.queueInternal.shift();
            this.queueLoading = true;

            var params = {};

            if(this.action == 'install') {
                params.enable = this.enable ? 1 : 0;
            }

            axios.request({
                url: '/admin/features/' + this.action + '/' + this.queueItemCurrent.id,
                method: 'POST',
                params: params,
            })
            .then(function(response) {
                this.queueLoading = false;

                if(response.data.success == false) {
                    this.queueHandleError(response.response || response);
                    return;
                }

                this.queueItemsProcessed ++;
                this.queueProcessNext();
            }.bind(this))   
            .catch(function(response) {
                this.queueLoading = false;
                this.queueHandleError(response.response || response);
            }.bind(this));
        },
        queueProcessEnd() {
            this.queueProcessing = false;
            this.queueFinished = true;

            if(this.queueErrors.length > 0) {
                return;
            }

            this.$emit('onSuccess');
            this.$emit('onSave');
            this.closeDialog();
        },
        queueHandleError(response) {
            const subtitle = Array.isArray(response.data.errors) 
                ? response.data.errors.join('; ') : 'An unknown error occurred';

            this.queueErrors.push({
                title: this.queueItemCurrent.name,
                subtitle: subtitle
            });

            this.queueProcessNext();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
};
