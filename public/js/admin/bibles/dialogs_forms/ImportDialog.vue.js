import EditForm from './BibleEditForm.vue.js';
import Loading from '/js/bin/custom_vue/dialogs/LoadingDialog.vue.js';
import Confirm from '/js/bin/custom_vue/dialogs/ConfirmDialog.vue.js';

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
        :max-width='confirmed ? 1000 : 600' 
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
                        :disabled='confirmed'
                        density='compact'
                    ></v-select>

                    <v-sheet v-html='importDescription'></v-sheet>

                    <v-file-input
                        v-model='file'
                        density='compact'
                        persistent-hint
                        :disabled='confirmed'
                        :hint="'Maximum upload size of ' + bootstrap.maxUploadSize.fmt + 'B'"
                    ></v-file-input>

                    <component ref='ImportComponent' :is='importerComponent' :settings='settings'></component>

                    <Confirm ref='ConfirmDialog'></Confirm>    
                    
                    <v-sheet v-if = '!confirmed'>

                    </v-sheet>
                    
                    <EditForm 
                        :record='bibleRecord'
                        v-if = 'confirmed'
                    ></EditForm>

                    <v-sheet v-if='confirmed && errors.length > 0' background-color='warn' class='mt-10'>
                        <h3>Errors:</h3> 
                        <v-list :items='errors' hide-details> 
                            <v-list-item v-for='e in errors'
                                :title='e.title'
                                :subtitle='e.subtitle'
                            ></v-list-item>
                        </v-list>
                    </v-sheet>

                    <Loading :showing='loading'></Loading>
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn v-if='!confirmed'
                        text='Check File'
                        @click='handleCheckFile()'
                    ></v-btn>                       
                    <v-btn v-if='confirmed'
                        text='Import Bible'
                        @click='handleImport()'
                    ></v-btn>                         
                    <v-btn
                        text='Cancel'
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
        Loading,
        Confirm,
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
    },
    data() {
        return {
            loading: false,
            confirmed: false,
            importer: null,
            file: null,
            fileSanitized: null,
            settings: {},
            bibleRecord: {},
            errors: [],
        }
    },
    computed: {
        title() {
            return this.confirmed ? 'Bible Import: Import File' : 'Bible Import: Select File';
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
        showing: function(is, was) {
            this.importer = null;
            this.file = null;
            this.fileSanitized = null;
            this.settings = {};
            this.bibleRecord = {};
            this.confirmed = false;
        },
        importer: function(is, was) {
            // this.$refs.ImportComponent && this.$refs.ImportComponent.reset();
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        handleCheckFile() {
            if(this.loading) {
                return;
            }

            this.errors = [];

            // Experimental confirm dialog, seems to be working tho
            // console.log('conf', this.$refs.ConfirmDialog.confirm('bacon', 
            //     () => alert('OK'), 
            //     () => alert('Cancel')
            // ));

            // return;

            var t = this,
                importer = this.selectedImporter;

            if(!this.importer) {
                this.errors.push('Importer is required');
            }

            if(!this.file) {
                this.errors.push('File is required');
            } else {
                var fnParts = this.file.name.split('.'),
                ext = fnParts.pop(),
                matchesExt = false;

                if(importer && importer.ext && importer.ext.length > 0) {
                    for(var i in importer.ext) {
                        var e = importer.ext[i];

                        if(this.file.name.endsWith(e)) {
                            matchesExt = true;
                            break;
                        }
                    }

                    if(!matchesExt) {
                        if(importer.ext.length == 1) {
                            this.errors.push('Invalid file extension. File must have .' + importer.ext[0] + ' extension');
                        }
                        else {
                            this.errors.push('Invalid file extension. Extension must be one of the following: .' + importer.ext.join(', .'));
                        }
                    }
                }

                if(this.file.size > bootstrap.maxUploadSize.raw) {
                    this.errors.push('File is too large.  Max upload file size is ' + bootstrap.maxUploadSize.fmt + 'B.');
                }
            }

            if(this.errors.length > 0) {
                return;
            }

            this.loading = true;

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
                t.bibleRecord = response.data.bible;
                t.fileSanitized = response.data.file;
                
                alert(
                    'This file is ready to import.  Please fill out the rest of' +
                    'the information for this Bible, then click \'Import File.\''
                );

                t.confirmed = true;
            }).catch(function(error) {
                t.loading = false;
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
        handleImport() {
            if(this.loading || !this.confirmed) {
                return;
            }

            this.loading = true;
            this.errors = [];
            var t = this;

            var postData = this.bibleRecord;
            postData._token = laravelCsrfToken;
            postData._file = this.fileSanitized;
            postData._importer = this.importer;
            postData._force_use_module = 0; // todo
            postData._settings = JSON.stringify(this.settings);

            axios.request({
                url: '/admin/bibles/import',
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'multipart/form-data'
                },
                data: postData
            }).then(function(response) {
                alert('Bible has imported successfully!');

                t.loading = false;
                t.$emit('onSave');
                t.closeDialog();
            }).catch(function(error) {
                t.loading = false;
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