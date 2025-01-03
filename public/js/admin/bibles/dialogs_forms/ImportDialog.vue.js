import EditForm from './BibleEditForm.vue.js';

// Importer-specific forms
import Analyzer from '../forms/importers/Analyzer.vue.js';
import BibleSuperSearch from '../forms/importers/BibleSuperSearch.vue.js';
import MySword from '../forms/importers/MySword.vue.js';
import Spreadsheet from '../forms/importers/Spreadsheet.vue.js';
import Unbound from '../forms/importers/Unbound.vue.js';
import Usfm from '../forms/importers/USFM.vue.js';


const tpl = `

    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>{{title}}</v-card-title>
                <v-card-text class='vue_dialog_body'>
                    <div v-for='e in errors' color='error'>{{e}}</div>

                    <v-select
                        label='Importer'
                        clearable
                        v-model='importer'
                        :items='bootstrap.importers'
                        item-title='name'
                        item-value='type'
                        :readonly='confirmed'
                        density='compact'
                    ></v-select>

                    <v-sheet v-html='importDescription'></v-sheet>

                    <v-file-input
                        v-model='file'
                        density='compact'
                        persistent-hint

                        :hint="'Maximum upload size of ' + bootstrap.maxUploadSize.fmt + 'B'"
                    ></v-file-input>

                    <component ref='ImportComponent' :is='importerComponent' :settings='settings'></component>

                        
                    <v-sheet v-if = '!confirmed'>

                    </v-sheet>
                    <v-sheet v-if='confirmed && errors.length > 0' background-color='warn' class='mt-10'>
                        <h3>Errors:</h3> 
                        <v-list :items='errors' hide-details> 
                            <v-list-item v-for='e in errors'
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
    inject: ['bootstrap'],
    template: tpl,
    components: {
        EditForm,
        Analyzer,
        BibleSuperSearch,
        MySword,
        Spreadsheet,
        Unbound,
        Usfm
    },
    props: {
        showing: {
            type: Boolean,
            default: false,
        },        
        // // Queue of items to process
        // queue: {
        //     type: Array,
        //     default: null
        // },
        // action: {
        //     type: String,
        //     default: null,
        // },
    },
    data() {
        return {
            confirmed: false,
            // showing: false,
            importer: null,
            file: null,
            settings: {},
            errors: [],
        }
    },
    computed: {
        title() {
            return this.confirmed ? 'Bible Import: Import File' : 'Bible Import: Select File';
        },
        confirmButtonLabel() {
            return 'Check File';
        },
        selectedImporter() {
            return bootstrap.importers.find(item => item.type == this.importer) || null;
        }, 
        importDescription() {
            return this.selectedImporter 
                ? this.selectedImporter.desc : '(Please select an importer to see it\'s description.)';
        },
        importerComponent() {
            return this.selectedImporter ? this.selectedImporter.kind : null;
        }
    },
    watch: {
        importer: function(is, was) {
            // this.$refs.ImportComponent && this.$refs.ImportComponent.reset();
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        handleOk() {
            // this.confirmed = true;

            this.loading = true;
            this.errors = [];
            var t = this;

            axios.request({
                url: '/admin/bibles/importcheck',
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'multipart/form-data'
                },
                data: {
                    ...{                        
                        _token: laravelCsrfToken,
                        file: this.file,
                        importer: this.importer,
                    },

                    ...this.settings
                    // ...this.$refs.ImporterComponent.getSettings()
                }
            }).then(function(response) {
                t.loading = false;
                t.$emit('onSave');
                t.closeDialog();
            }).catch(function(error) {
                console.log('error', error);

                if(error.response.data.errors) {
                    t.errors = error.response.data.errors;
                }

                if(error.response.data.message) {
                    t.errors.unshift(error.response.data.message);
                } 

                if(t.errors.length == 0) {
                    t.errors.push('An unknown error has occurred');
                }
            });
        },
        closeDialog() {
            // this.showing = false;
            this.$emit('onClose');
        },
        closeDialogSave() {
            this.closeDialog();
            this.$emit('onSave');
        }
    }
};