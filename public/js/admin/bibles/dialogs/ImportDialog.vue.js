import EditForm from '../forms/BibleEditForm.vue.js';
import Loading from '../../../bin/custom_vue/dialogs/LoadingDialog.vue.js';
import Confirm from '../../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';
import ErrorDialog from '../../../bin/custom_vue/dialogs/ErrorDialog.vue.js';
import ErrorPane from '../../../bin/custom_vue/components/ErrorPane.vue.js';

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
                    <ErrorPane :errors='errors' color='error' />

                    <v-form ref='form' v-model='formValid' lazy-validation>
                        <v-select
                            label='Importer'
                            clearable
                            v-model='importer'
                            :items='bootstrap.importers'
                            item-title='name'
                            item-value='type'
                            :disabled='confirmed'
                            density='compact'
                            :rules='[v => !!v || "Importer is required"]'
                        ></v-select>

                        <v-sheet v-html='importDescription'></v-sheet>

                        <v-file-input
                            v-model='file'
                            density='compact'
                            persistent-hint
                            :disabled='confirmed'
                            :hint="'Maximum upload size of ' + bootstrap.maxUploadSize.fmt + 'B'"
                            :rules='[v => !!file || "File is required", v => validateFileExtension(), v => validateFileSize()]'
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
                    </v-form>

                    <Loading :showing='loading'></Loading>

                    <ErrorDialog
                        :errors='errors'
                        :title="'Errors Importing Bible'"
                    ></ErrorDialog>
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn v-if='!confirmed'e
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
        ErrorDialog,
        ErrorPane,
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
        replace: {
            type: Number,
            default: null,
        },        
    },
    data() {
        return {
            loading: false,
            confirmed: false,
            importer: null,
            file: null,
            fileSanitized: null,
            formValid: false,
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
        },
        replacing() {
            return this.replace > 0;
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
        async handleCheckFile() {
            if(this.loading) {
                return;
            }

            const { valid } = await this.$refs.form.validate();

            if (!valid) {
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
                var matchesExt = false;

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
                        bible_id: this.replace || null
                    },

                    ...this.settings
                    // ...this.$refs.ImporterComponent.getSettings()
                }
            }).then(function(response) {
                t.loading = false;
                t.bibleRecord = response.data.bible;
                t.fileSanitized = response.data.file;
                
                t.$refs.ConfirmDialog.alert(
                    'This file is ready to import.  Please fill out the rest of ' +
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
        async handleImport() {
            if(this.loading || !this.confirmed) {
                return;
            }

            const { valid } = await this.$refs.form.validate();

            if (!valid) {
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
                t.loading = false;
                t.$emit('onSave'); // Cause grid to refresh immediately
                var msg = 'Bible has imported successfully!  \n Would you like to test it?';

                t.$refs.ConfirmDialog.confirmSingle(msg, function(confirmed) {                    
                    console.log('confirmed', confirmed);
                    t.closeDialog();
                    confirmed && t.$emit('onTest', response.data.bible);
                });
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
        },
        validateFileExtension() {
            var importer = this.selectedImporter,
                matchesExt = false;
            
            if(importer && importer.ext && importer.ext.length > 0 && this.file) {
                for(var i in importer.ext) {
                    var e = importer.ext[i];

                    if(this.file.name.endsWith(e)) {
                        matchesExt = true;
                        break;
                    }
                }

                if(!matchesExt) {
                    if(importer.ext.length == 1) {
                        return 'Invalid file extension. File must have .' + importer.ext[0] + ' extension';
                    }
                    else {
                        return 'Invalid file extension. Extension must be one of the following: .' + importer.ext.join(', .');
                    }
                }
            }

            return true;
        },
        validateFileSize() {
            if(this.file && this.file.size > bootstrap.maxUploadSize.raw) {
                return 'File is too large.  Max upload file size is ' + bootstrap.maxUploadSize.fmt + 'B.';
            }

            return true;
        }
    }
};