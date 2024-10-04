
const template = `
    <v-dialog 
        v-model='showing'
        max-width='600' 

    >
        <template v-slot:default="{ isActive }">
            <v-card title='Edit Language'>
                <v-card-text>
                    Form elements go here ....
                    Stuffs<br />
                    recording.name: {{recording.name}} <br />
                    record.name: {{record.name}} <br />
                    recordInternal.name: {{recordInternal.name}}
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
        showing: {
            type: Boolean,
            default: false
        },
        recordId: {
            type: Number,
            default: null
        },
        record: {
            type: Object,
            default: {}
        },
        loadRecord: {
            type: Boolean,
            default: false
        },        
        loadRecord: {
            type: Boolean,
            default: false
        }
    },
    template: template,
    data() {
        return { 
            // showing: true
            recordInternal: {},
            loading: false,
        }
    },
    watch: {
        showing(newValue, oldValue) {
            console.log('showingChanged', newValue, oldValue);
            // do something here?
        },
        recordId(newValue, oldValue) {
            if(newValue && newValue != '') {
                // Editing existing record
                if(this.loadRecord) {
                    var t = this;

                    axios.request({
                        url: this.url + '/' + newValue,
                        method: 'GET',
                        params: []
                    }).then(function(response) {
                        console.log(response);
                        t.recordInternal = response.data.Language;
                        t.loading = false;
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
    },
    methods: {
        handleCancel() {
            this.$emit('onClose');
            // alert('cancel');
        },
        handleSave() {
            this.handleCancel();
        }
    }
}
