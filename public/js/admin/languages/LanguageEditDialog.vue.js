
const template = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card :_title='title' :loading='loading' _color='primary'>

                    <v-card-title :text='title' >{{title}}</v-card-title>

                <v-card-text class='vue_editdialog_body'>
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
            console.log('showingChanged', newValue, oldValue);
            // do something here?
        },
        recordId(newValue, oldValue) {
            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            this.newRecord = (newValue == '' || newValue == '-1');

            if(newValue && newValue != '') {
                // Editing existing record
                if(this.loadRecord) {
                    var t = this;

                    axios.request({
                        url: this.url + '/' + newValue,
                        method: 'GET',
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                        params: []
                    }).then(function(response) {
                        t.recordInternal = response.data.Language;
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
                }
            } else {
                // Creating new record
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
            var url = this.newRecord ? this.url : this.url + '/' + this.recordId,
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
