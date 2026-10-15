import EditForm from '../forms/VolumeEditForm.vue.js';
import Loading from '../../../bin/custom_vue/dialogs/LoadingDialog.vue.js';
import Confirm from '../../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';
import ErrorPane from '../../../bin/custom_vue/components/ErrorPane.vue.js';

// Import source settings forms, by the source's `kind`.  A kind with no form uses Base.
import Base from '../forms/importers/Base.vue.js';
import Spreadsheet from '../forms/importers/Spreadsheet.vue.js';

const importerForms = { Base, Spreadsheet };

/**
 * Imports a volume's content from a third party file.
 *
 * Select the volume type, then an importer the type supports, then the file; Check File
 * validates it against the importer's settings and stores it, then the volume's details are
 * filled in and Import creates the volume.  With `replace`, the content of that (unofficial)
 * volume is replaced instead.
 */
const template = `
    <v-dialog
        v-model='showingInternal'
        :max-width='confirmed ? 1000 : 700'
    >
        <v-card>
            <v-card-title>{{title}}</v-card-title>
            <v-card-text class='vue_dialog_body' ref='body'>
                <ErrorPane :errors='errors' color='error' />

                <v-alert v-if='confirmed && formValid === false' type='error' outlined>
                    Please correct the errors in the form before importing.
                </v-alert>

                <v-form ref='form' v-model='formValid' validate-on='lazy'>
                    <v-select
                        label='Volume Type'
                        v-model='contentType'
                        :items='typeList'
                        :disabled='confirmed || !!replace'
                        density='compact'
                        :rules='[v => !!v || "Volume type is required"]'
                        v-bind='defaultProps.selects'
                    ></v-select>

                    <v-select
                        label='Importer'
                        clearable
                        v-model='importer'
                        :items='importers'
                        item-title='name'
                        item-value='type'
                        :disabled='confirmed || !contentType'
                        :no-data-text='"No importers are available for this volume type"'
                        density='compact'
                        :rules='[v => !!v || "Importer is required"]'
                        v-bind='defaultProps.selects'
                        :item-props='defaultProps.itemPropsFunction'
                    ></v-select>

                    <v-sheet class='mb-3'>{{importDescription}}</v-sheet>

                    <v-file-input
                        v-model='file'
                        density='compact'
                        persistent-hint
                        :disabled='confirmed'
                        :hint="'Maximum upload size of ' + bootstrap.maxUploadSize.fmt + 'B'"
                        :rules='[v => !!file || "File is required", v => validateFileExtension(), v => validateFileSize()]'
                        v-bind='defaultProps.fileInputs'
                    ></v-file-input>

                    <component
                        v-if='selectedImporter'
                        :is='importerForm'
                        :settings='settings'
                        :contentType='contentType'
                    ></component>

                    <Confirm ref='ConfirmDialog'></Confirm>

                    <template v-if='confirmed'>
                        <v-divider class='my-4'></v-divider>
                        <h3 class='mb-3'>Volume Details</h3>
                        <EditForm :record='volumeRecord' :errors='fieldErrors'></EditForm>
                    </template>
                </v-form>

                <Loading :showing='loading'></Loading>
            </v-card-text>

            <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn text='Cancel' @click='closeDialog()'></v-btn>
                <v-btn v-if='!confirmed' text='Check File' @click='handleCheckFile()'></v-btn>
                <v-btn v-if='confirmed' text='Import' @click='handleImport()'></v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
`;

export default {
    template: template,
    components: {
        EditForm,
        Loading,
        Confirm,
        ErrorPane,
        ...importerForms,
    },
    props: {
        showing: {
            type: Boolean,
            default: false,
        },
        // Volume to replace the content of: {id, type}
        replace: {
            type: Object,
            default: null,
        },
    },
    emits: ['onClose', 'onSave'],
    setup(props, { emit }) {
        const bootstrap = Vue.inject('bootstrap');
        const defaultProps = Vue.inject('defaultProps');

        const form = Vue.ref(null);
        const body = Vue.ref(null);
        const ConfirmDialog = Vue.ref(null);

        const showingInternal = Vue.ref(false);
        const loading = Vue.ref(false);
        const confirmed = Vue.ref(false);
        const formValid = Vue.ref(null);
        const contentType = Vue.ref(null);
        const importer = Vue.ref(null);
        const file = Vue.ref(null);
        const fileSanitized = Vue.ref(null);
        const settings = Vue.reactive({});
        const volumeRecord = Vue.ref({});
        const errors = Vue.ref([]);
        const fieldErrors = Vue.ref({});

        const typeList = Object.entries(bootstrap.volume_types || {}).map(([value, type]) => ({value, title: type.label}));

        const importers = Vue.computed(() => {
            const type = (bootstrap.volume_types || {})[contentType.value];
            return type ? type.importers || [] : [];
        });

        const selectedImporter = Vue.computed(() => importers.value.find(item => item.type == importer.value) || null);

        const importDescription = Vue.computed(() => {
            return selectedImporter.value ? selectedImporter.value.desc : '(Please select an importer to see its description.)';
        });

        const importerForm = Vue.computed(() => {
            const kind = selectedImporter.value ? selectedImporter.value.kind : null;
            return importerForms[kind] ? kind : 'Base';
        });

        const title = Vue.computed(() => {
            const what = props.replace ? 'Replace Volume Content' : 'Volume Import';
            return what + (confirmed.value ? ': Import File' : ': Select File');
        });

        function reset() {
            confirmed.value = false;
            importer.value = null;
            file.value = null;
            fileSanitized.value = null;
            volumeRecord.value = {};
            errors.value = [];
            fieldErrors.value = {};
            Object.keys(settings).forEach(key => delete settings[key]);

            if(props.replace) {
                contentType.value = props.replace.type;
            }
            else {
                contentType.value = typeList.length == 1 ? typeList[0].value : null;
            }
        }

        function closeDialog() {
            emit('onClose');
        }

        function validateFileExtension() {
            const source = selectedImporter.value;

            if(!source || !source.ext || source.ext.length == 0 || !file.value) {
                return true;
            }

            const name = file.value.name.toLowerCase();

            if(source.ext.some(ext => name.endsWith('.' + ext.toLowerCase()))) {
                return true;
            }

            return source.ext.length == 1
                ? 'Invalid file extension. File must have .' + source.ext[0] + ' extension'
                : 'Invalid file extension. Extension must be one of the following: .' + source.ext.join(', .');
        }

        function validateFileSize() {
            if(file.value && file.value.size > bootstrap.maxUploadSize.raw) {
                return 'File is too large.  Max upload file size is ' + bootstrap.maxUploadSize.fmt + 'B.';
            }

            return true;
        }

        /**
         * Validation errors arrive keyed by field; manager errors as a list.  Both are listed, and
         * field errors are also shown on the form.
         */
        function handleError(error) {
            loading.value = false;
            const data = error && error.response ? error.response.data : {};
            const list = [];

            fieldErrors.value = {};

            if(Array.isArray(data.errors)) {
                list.push(...data.errors);
            }
            else if(data.errors && typeof data.errors == 'object') {
                fieldErrors.value = data.errors;
                Object.values(data.errors).forEach(messages => list.push(...[].concat(messages)));
            }

            if(data.message) {
                list.unshift(data.message);
            }

            errors.value = list.length ? list : ['An unknown error has occurred'];

            if(body.value && body.value.$el) {
                body.value.$el.scrollTop = 0;
            }
        }

        async function handleCheckFile() {
            if(loading.value) {
                return;
            }

            const { valid } = await form.value.validate();

            if(!valid) {
                return;
            }

            errors.value = [];
            loading.value = true;

            axios.request({
                url: '/admin/volumes/importcheck',
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'multipart/form-data',
                },
                data: {
                    ...settings,
                    _token: laravelCsrfToken,
                    file: file.value,
                    importer: importer.value,
                    content_type: contentType.value,
                    volume_id: props.replace ? props.replace.id : '',
                },
            }).then(response => {
                loading.value = false;
                volumeRecord.value = response.data.volume || {};
                volumeRecord.value.type = contentType.value;
                fileSanitized.value = response.data.file;
                confirmed.value = true;

                ConfirmDialog.value.alert(
                    'This file is ready to import.  Please fill out the rest of ' +
                    'the information for this volume, then click \'Import.\''
                );
            }).catch(handleError);
        }

        async function handleImport() {
            if(loading.value || !confirmed.value) {
                return;
            }

            const { valid } = await form.value.validate();

            if(!valid) {
                if(body.value && body.value.$el) {
                    body.value.$el.scrollTop = 0;
                }

                return;
            }

            errors.value = [];
            fieldErrors.value = {};
            loading.value = true;

            const data = {
                ...volumeRecord.value,
                _token: laravelCsrfToken,
                _file: fileSanitized.value,
                _importer: importer.value,
                _content_type: contentType.value,
                _settings: JSON.stringify(settings),
            };

            if(props.replace) {
                data.id = props.replace.id;
            }

            axios.post('/admin/volumes/import', data).then(response => {
                loading.value = false;
                emit('onSave');

                const rows = response.data.rows;
                ConfirmDialog.value.confirmSingle(
                    'Volume has imported successfully' + (rows ? ' (' + rows + ' entries)' : '') + '!',
                    () => closeDialog()
                );
            }).catch(handleError);
        }

        Vue.watch(() => props.showing, is => {
            reset();
            showingInternal.value = is;
        });

        Vue.watch(showingInternal, (is, was) => {
            if(!is && was) {
                emit('onClose');
            }
        });

        // Settings belong to an importer: start afresh when it changes
        Vue.watch(importer, () => {
            Object.keys(settings).forEach(key => delete settings[key]);
        });

        Vue.watch(contentType, () => {
            if(!confirmed.value) {
                importer.value = null;
            }
        });

        return {
            bootstrap,
            defaultProps,
            form,
            body,
            ConfirmDialog,
            showingInternal,
            loading,
            confirmed,
            formValid,
            contentType,
            importer,
            file,
            settings,
            volumeRecord,
            errors,
            fieldErrors,
            typeList,
            importers,
            selectedImporter,
            importDescription,
            importerForm,
            title,
            closeDialog,
            validateFileExtension,
            validateFileSize,
            handleCheckFile,
            handleImport,
        };
    }
};
