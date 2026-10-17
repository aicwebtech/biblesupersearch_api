import '../../../../js/bin/ckeditor5/build/ckeditor.js';

const template = `
    <div>
        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-select
                    :items='typeList'
                    label='Type'
                    v-model='record.type'
                    v-bind='defaultProps.selects'
                    :disabled='isExisting'
                    :rules='[v => !!v || "Type is required", v => errorShow("type")]'
                    @update:modelValue='errorClear("type")'
                    hint='Type of content in this volume. It cannot be changed once set.'
                ></v-select>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Name'
                    v-model='record.name'
                    v-bind='defaultProps.texts'
                    :rules='[v => !!v || "Name is required", v => errorShow("name")]'
                    @keydown='errorClear("name")'
                    hint='Full display name of the volume. It must be unique for its type.'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Short Name'
                    v-model='record.shortname'
                    v-bind='defaultProps.texts'
                    :rules='[v => !!v || "Short Name is required", v => errorShow("shortname")]'
                    @keydown='errorClear("shortname")'
                    hint='Short display name of the volume. It must be unique for its type.'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Module'
                    v-model='record.module'
                    v-bind='defaultProps.texts'
                    :disabled='isExisting'
                    hint='Identifies the volume in the system. It must be unique for its type, and cannot be changed once set.'
                    :rules='[
                        v => !!v || "Module is required",
                        v => /^[a-z]{2}/.test(v) || "Module name must start with at least two letters",
                        v => /^[a-z0-9_]*$/.test(v) || "Module name contains invalid characters. Only lowercase letters, numbers and underscores are allowed",
                        v => !v || v.length <= 50 || "Module name must be 50 characters or less",
                        v => errorShow("module")
                    ]'
                    @keydown='errorClear("module")'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Rank'
                    v-model='record.rank'
                    v-bind='defaultProps.texts'
                    hint='Customizable sort order, must be an integer number.'
                    :rules='[
                        v => !v || /^-?[0-9]+$/.test(v) || "Rank must be an integer",
                        v => errorShow("rank")
                    ]'
                    @keydown='errorClear("rank")'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col cols='9'>
                <v-autocomplete
                    :items='bootstrap.languages'
                    label='Language'
                    v-model='record.language'
                    v-bind='defaultProps.selects'
                    :item-props='languageItemProps'
                    :rules='[v => !!v || "Language is required", v => errorShow("language")]'
                    hint="Tip: entering a code will cause the language to be selected, and vice-versa."
                    @update:modelValue='errorClear("language")'
                ></v-autocomplete>
            </v-col>
            <v-col cols='3'>
                <v-text-field
                    label='Code'
                    v-model='record.language'
                    v-bind='defaultProps.texts'
                    hint='ISO-639-1 code if exists, otherwise ISO 639-2 code'
                    :rules='[v => !v || /^[a-z]{2,3}(_[a-z]{2})?$/.test(v) || "Must be a 2 or 3 lowercase letter code", v => errorShow("language")]'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-autocomplete
                    :items='bootstrap.copyrights'
                    label='Copyright'
                    v-model='record.copyright_id'
                    v-bind='defaultProps.selects'
                    :item-props='defaultProps.itemPropsFunction'
                    item-title='name'
                    item-value='id'
                    :rules='[v => !!v || "Copyright is required", v => errorShow("copyright_id")]'
                    @update:modelValue='copyrightChanged'
                ></v-autocomplete>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Copyright Owner'
                    v-model='record.owner'
                    v-bind='defaultProps.texts'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Publisher'
                    v-model='record.publisher'
                    v-bind='defaultProps.texts'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-row v-bind='defaultProps.vrows'>
            <v-col>
                <v-text-field
                    label='Publication Year'
                    v-model='record.year'
                    v-bind='defaultProps.texts'
                ></v-text-field>
            </v-col>
        </v-row>

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <label>Copyright Statement / Short Description</label><br />
        <small>This will be displayed with the volume's content.</small>
        <br />

        <textarea ref='copyrightStatementEl'></textarea>

        <template v-if='showDefaultCopyright'>
            <v-spacer class='pt-2' />
            <label>Default Copyright Statement</label>
            <br />
            <small>This will be displayed instead if copyright statement is left blank.</small>
            <br />

            <div
                class='default-copyright-statement'
                v-html='defaultCopyrightStatement'
            ></div>
        </template>

        <v-divider v-bind='defaultProps.dividers'></v-divider>

        <label>Description</label><br />
        <small>Full description of this volume.</small>
        <br />

        <textarea ref='descriptionEl'></textarea>
    </div>
`;

export default {
    template: template,
    props: {
        record: {
            type: Object,
            default: () => ({})
        },
        errors: {
            type: Object,
            default: () => ({})
        },
    },
    setup(props) {
        const bootstrap = Vue.inject('bootstrap');
        const defaultProps = Vue.inject('defaultProps');

        const descriptionEl = Vue.ref(null);
        const copyrightStatementEl = Vue.ref(null);

        let descriptionEditor = null;
        let copyrightEditor = null;
        let prevCopyrightId = props.record.copyright_id || null;

        const typeList = Vue.computed(() => {
            return Object.entries(bootstrap.volume_types || {}).map(([value, type]) => ({value, title: type.label}));
        });

        const isExisting = Vue.computed(() => props.record && props.record.id > 0);

        const showDefaultCopyright = Vue.computed(() => !props.record.copyright_statement);

        const defaultCopyrightStatement = Vue.computed(() => {
            const cr = bootstrap.copyrights.find(element => element.id == props.record.copyright_id);
            return cr ? cr.copyright_statement_processed : '';
        });

        function initRecord() {
            // Default the type when only one is registered
            if(!props.record.type && typeList.value.length == 1) {
                props.record.type = typeList.value[0].value;
            }

            descriptionEditor && descriptionEditor.setData(props.record.description || '');
            copyrightEditor && copyrightEditor.setData(props.record.copyright_statement || '');
        }

        function initEditor(el, field, callback) {
            ClassicEditor
                .create(el, defaultProps.ckeditor.settings)
                .then(editor => {
                    editor.setData(props.record[field] || '');

                    editor.model.document.on('change:data', () => {
                        props.record[field] = editor.getData();
                    });

                    callback(editor);
                })
                .catch(error => {
                    console.error(error);
                });
        }

        function languageItemProps(item) {
            return item && item.code ? {
                ...defaultProps.items,
                title: item.code.toUpperCase() + ' ' + item.name,
                value: item.code,
            } : {};
        }

        function copyrightChanged(value) {
            errorClear('copyright_id');

            const cr = bootstrap.copyrights.find(item => item.id == value);

            if(!cr) {
                prevCopyrightId = value;
                return;
            }

            let msg = 'Please verify this is the correct copyright for this volume\n\n';
            msg += cr.name;
            msg += '\n\nWarning: Selecting the wrong copyright may put you at risk of civil or criminal penalties!';

            if(window.confirm(msg)) {
                prevCopyrightId = value;
            } else {
                props.record.copyright_id = prevCopyrightId;
            }
        }

        function errorShow(field) {
            if(!props.errors || !props.errors[field]) {
                return true;
            }

            return props.errors[field].join(', ');
        }

        function errorClear(field) {
            if(props.errors && props.errors[field]) {
                delete props.errors[field];
            }
        }

        Vue.watch(() => props.record, () => {
            prevCopyrightId = props.record.copyright_id || null;
            initRecord();
        });

        Vue.onMounted(() => {
            initRecord();
            initEditor(descriptionEl.value, 'description', editor => descriptionEditor = editor);
            initEditor(copyrightStatementEl.value, 'copyright_statement', editor => copyrightEditor = editor);
        });

        Vue.onBeforeUnmount(() => {
            descriptionEditor && descriptionEditor.destroy();
            copyrightEditor && copyrightEditor.destroy();
        });

        return {
            bootstrap,
            defaultProps,
            descriptionEl,
            copyrightStatementEl,
            typeList,
            isExisting,
            showDefaultCopyright,
            defaultCopyrightStatement,
            languageItemProps,
            copyrightChanged,
            errorShow,
            errorClear,
        };
    }
}
