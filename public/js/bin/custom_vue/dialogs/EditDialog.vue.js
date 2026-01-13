import ErrorDialog from './ErrorDialog.vue.js';
import ErrorPane from '../components/ErrorPane.vue.js';

const template = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card :loading='loading'>
                <v-card-title :text='title' >{{title}}</v-card-title>

                <v-card-text class='vue_editdialog_body' ref='body'>
                    <ErrorPane :errors='responseErrors' color='error' />
                
                    <v-form ref='form' v-model='formValid' lazy-validation>
                        <slot :data='recording' :errors='responseErrors'></slot>
                    </v-form>

                    <ErrorDialog
                        :errors='responseErrors'
                        :title="'Errors Saving ' + this.recordType + ':'"
                    />
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
    props: {
        url: {
            type: String,
            required: true
        },
        recordId: {
            type: Number,
            default: null
        },
        record: {
            type: Object,
            default: {}
        },
        recordType: {
            type: String,
            default: 'Item',
        },
        recordIndex: {
            type: String,
            default: 'Item',
        },
        loadRecord: {
            type: Boolean,
            default: false
        }
    },
    template: template,
    data() {
        return { 
            recordInternal: {},
            formValid: false,
            showing: false,
            loading: false,
            responseErrors: null,
        }
    },
    components: {
        ErrorDialog,
        ErrorPane
    },
    watch: {
        showing(newValue, oldValue) {
            // do something here?
            this.responseErrors = null;
        },
        recordId(newValue, oldValue) {
            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            if(!this.newRecord) {
                // Editing existing record
                if(this.loadRecord) {
                    this.loading = true;
                    var t = this;

                    axios.request({
                        url: this.url + '/' + newValue,
                        method: 'GET',
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                        params: []
                    }).then(function(response) {
                        t.recordInternal = response.data[t.recordIndex];
                        t.loading = false;
                        t.showing = true;
                    }).catch(function(error) {
                        t.loading = false;
                        
                        if(error.response.data.message) {
                            alert(error.response.data.message);
                        } else {
                            alert('An unknown error has occurred');
                        }
                    });
                } else {
                    // do something?
                    this.showing = true;
                }
            } else {
                // Creating new record
                this.recordInternal = {};
                this.showing = true;
                this.loading = false;
            }
        }
    }, 
    computed: {
        recording() {
            return this.loadRecord && this.recordId ? this.recordInternal : this.record;
        },
        newRecord() {
            return this.recordId == '-1' || this.recordId == -1;
        },
        title() {
            return (this.newRecord ? 'New' : 'Update') + ' ' + this.recordType;
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        async handleSave() {
            this.responseErrors = null;
            
            if(this.$refs.form) {
                const { valid } = await this.$refs.form.validate();
                console.log('form valid:', valid);
                
                if (!valid) {
                    return;
                }
            }
            
            var record = this.recording,
                url = this.newRecord ? this.url : this.url + '/' + this.recordId,
                method = this.newRecord ? 'POST' : 'PUT',
                t = this;

            this.loading = true;

            axios.request({
                url: url,
                method: method,
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                data: this.recording
            }).then(function(response) {
                t.loading = false;
                t.closeDialog();
                t.$emit('onSave');
            }).catch(function(error) {
                t.responseErrors = error?.response?.data?.errors || null;
                t.loading = false;
                
                if(error.response.data.message) {
                    alert(error.response.data.message);
                } else if(!error.response.data.errors) {
                    alert('An unknown error has occurred');
                }

                t.$refs.form.validate();
            });
        }, 
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
}
