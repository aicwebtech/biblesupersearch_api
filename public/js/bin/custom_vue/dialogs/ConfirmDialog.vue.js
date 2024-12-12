// NOT FINISHED (or even started, LOL)

const template = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card :loading='loading'>
                <v-card-title :text='title' >{{title}}</v-card-title>

                <v-card-text class='vue_editdialog_body'>
                    <slot :data='recording'></slot>
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
            showing: false,
            loading: false,
        }
    },
    watch: {
        showing(newValue, oldValue) {
            // do something here?
        },
        recordId(newValue, oldValue) {
            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            this.newRecord = (newValue == '-1' || newValue == -1);

            if(!this.newRecord) {
                // Editing existing record
                if(this.loadRecord) {
                    this.loading = true;
                    this.showing = true;
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
        title() {
            return (this.newRecord ? 'New' : 'Update') + ' ' + this.recordType;
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        handleSave() {
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
                t.$emit('onSave');
                t.closeDialog();
            }).catch(function(error) {
                if(error.response.data.message) {
                    alert(error.response.data.message);
                } else {
                    alert('An unknown error has occurred');
                }
            });

            this.handleCancel();
        }, 
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
}
