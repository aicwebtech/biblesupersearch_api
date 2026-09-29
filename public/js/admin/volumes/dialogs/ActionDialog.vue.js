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
                        <v-list hide-details>
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

/**
 * Processes a queue of volumes one at a time, POSTing /admin/volumes/{action}/{id} for each
 */
export default {
    template: tpl,
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
    emits: ['onClose', 'onSave', 'onSuccess'],
    setup(props, { emit }) {
        const confirmed = Vue.ref(false);
        const showing = Vue.ref(false);
        const enable = Vue.ref(false);
        const queueItemsTotal = Vue.ref(0);
        const queueItemsProcessed = Vue.ref(0);
        const queueItemCurrent = Vue.ref(null);
        const queueProcessing = Vue.ref(false);
        const queueErrors = Vue.ref([]);

        let queueAbort = false;
        let queueInternal = [];

        const selectedAction = Vue.computed(() => {
            return (props.actions || []).find(item => item.action == props.action) || null;
        });

        const title = Vue.computed(() => {
            if(!selectedAction.value) {
                return null;
            }

            return selectedAction.value.dialogTitle || selectedAction.value.label + ' Volumes';
        });

        const confirmButtonLabel = Vue.computed(() => selectedAction.value ? selectedAction.value.label : null);
        const actioningLabel = Vue.computed(() => selectedAction.value ? selectedAction.value.actioning : null);

        const confirmText = Vue.computed(() => {
            if(!selectedAction.value) {
                return null;
            }

            return selectedAction.value.confirmText ||
                'Are you sure that you want to ' + selectedAction.value.action + ' the following volumes?';
        });

        const queueItemsProcessedPercent = Vue.computed(() => {
            if(queueItemsTotal.value < 1) {
                return 0;
            }

            return queueItemsProcessed.value * 100 / queueItemsTotal.value;
        });

        function clearForm() {
            confirmed.value = false;
            enable.value = false;
        }

        function closeDialog() {
            showing.value = false;
            emit('onClose');
        }

        function handleCancel() {
            if(queueProcessing.value) {
                queueAbort = true;
            } else {
                closeDialog();
            }
        }

        function handleOk() {
            confirmed.value = true;
            queueProcessStart();
        }

        function handleShowingChange(e) {
            if(!e) {
                closeDialog();
            } else {
                clearForm();
            }
        }

        function queueProcessStart() {
            queueItemsTotal.value = props.queue.length;
            queueItemsProcessed.value = 0;
            queueAbort = false;
            queueProcessing.value = true;
            queueErrors.value = [];
            queueInternal = [...props.queue];
            queueProcessNext();
        }

        function queueProcessNext() {
            if(queueAbort) {
                queueProcessing.value = false;
                closeDialog();
                return;
            }

            if(queueInternal.length == 0) {
                queueProcessEnd();
                return;
            }

            queueItemCurrent.value = queueInternal.shift();

            const params = {};

            if(props.action == 'install') {
                params.enable = enable.value ? 1 : 0;
            }

            axios.request({
                url: '/admin/volumes/' + props.action + '/' + queueItemCurrent.value.id,
                method: 'POST',
                params: params,
            })
            .then(response => {
                if(response.data.success == false) {
                    queueHandleError(response);
                    return;
                }

                queueItemsProcessed.value ++;
                queueProcessNext();
            })
            .catch(error => {
                queueHandleError(error.response || error);
            });
        }

        function queueProcessEnd() {
            queueProcessing.value = false;

            if(queueErrors.value.length > 0) {
                return;
            }

            emit('onSuccess');
            emit('onSave');
            closeDialog();
        }

        function queueHandleError(response) {
            const errors = response && response.data ? response.data.errors : null;
            const subtitle = Array.isArray(errors) ? errors.join('; ') : 'An unknown error occurred';

            queueErrors.value.push({
                title: queueItemCurrent.value.name,
                subtitle: subtitle
            });

            queueProcessNext();
        }

        Vue.watch(() => props.action, (newValue) => {
            clearForm();
            showing.value = !(newValue === false || newValue === null);
        });

        return {
            confirmed,
            showing,
            enable,
            queueItemCurrent,
            queueProcessing,
            queueErrors,
            title,
            confirmButtonLabel,
            actioningLabel,
            confirmText,
            queueItemsProcessedPercent,
            handleCancel,
            handleOk,
            handleShowingChange,
        };
    }
};
