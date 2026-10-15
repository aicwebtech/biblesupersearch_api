/**
 * Saved import settings (spreadsheet column mappings ...) for an import dialog.
 *
 * Choosing a preset copies its settings into `settings`; Save As / Update store the current
 * settings.  Presets are per type: 'bible', or a volume type key.
 */
const template = `
    <v-sheet class='mb-4'>
        <v-row dense align='center'>
            <v-col>
                <v-select
                    label='Saved Settings (Preset)'
                    v-model='selectedId'
                    :items='presets'
                    item-title='name'
                    item-value='id'
                    clearable
                    density='compact'
                    hide-details
                    :loading='loading'
                    no-data-text='No saved settings yet'
                    @update:modelValue='applyPreset'
                ></v-select>
            </v-col>
            <v-col cols='auto'>
                <v-btn size='small' variant='tonal' prepend-icon='mdi-content-save-plus' @click='saveAs'>Save As</v-btn>
                <v-btn size='small' variant='tonal' prepend-icon='mdi-content-save' class='ml-1' :disabled='!selectedId' @click='update'>Update</v-btn>
                <v-btn size='small' variant='tonal' prepend-icon='mdi-trash-can' class='ml-1' :disabled='!selectedId' @click='remove'>Delete</v-btn>
            </v-col>
        </v-row>
        <v-alert v-if='message' :type='messageType' density='compact' class='mt-2' closable @click:close='message = null'>
            {{message}}
        </v-alert>
    </v-sheet>
`;

export default {
    template: template,
    props: {
        type: {
            type: String,
            required: true,
        },
        settings: {
            type: Object,
            required: true,
        },
    },
    emits: ['applied'],
    setup(props, { emit }) {
        const presets = Vue.ref([]);
        const selectedId = Vue.ref(null);
        const loading = Vue.ref(false);
        const message = Vue.ref(null);
        const messageType = Vue.ref('success');

        const selected = Vue.computed(() => presets.value.find(item => item.id == selectedId.value) || null);

        function showError(error, fallback) {
            const data = error && error.response ? error.response.data : null;
            let text = fallback;

            if(data && data.errors) {
                text = Object.values(data.errors).flat().join(' ');
            }

            messageType.value = 'error';
            message.value = text;
        }

        function showSuccess(text) {
            messageType.value = 'success';
            message.value = text;
        }

        function load() {
            if(!props.type) {
                presets.value = [];
                return;
            }

            loading.value = true;

            axios.get('/admin/import-presets', {params: {type: props.type}})
                .then(response => {
                    presets.value = response.data.presets || [];
                })
                .catch(error => showError(error, 'Could not load saved settings'))
                .finally(() => loading.value = false);
        }

        function applyPreset() {
            if(!selected.value) {
                return;
            }

            Object.keys(props.settings).forEach(key => delete props.settings[key]);
            Object.assign(props.settings, JSON.parse(JSON.stringify(selected.value.settings || {})));
            message.value = null;
            emit('applied', selected.value);
        }

        function save(method, url, name) {
            loading.value = true;

            return axios.request({
                url: url,
                method: method,
                data: {
                    _token: laravelCsrfToken,
                    type: props.type,
                    name: name,
                    settings: props.settings,
                },
            }).then(response => {
                const preset = response.data.preset;
                presets.value = presets.value.filter(item => item.id != preset.id).concat([preset]);
                presets.value.sort((a, b) => a.name.localeCompare(b.name));
                selectedId.value = preset.id;
                showSuccess('Settings saved as \'' + preset.name + '\'');
            }).catch(error => showError(error, 'Could not save settings'))
              .finally(() => loading.value = false);
        }

        function saveAs() {
            const name = window.prompt('Name for these settings:');

            if(name && name.trim()) {
                save('POST', '/admin/import-presets', name.trim());
            }
        }

        function update() {
            if(selected.value && window.confirm('Overwrite \'' + selected.value.name + '\' with the current settings?')) {
                save('PUT', '/admin/import-presets/' + selected.value.id, selected.value.name);
            }
        }

        function remove() {
            if(!selected.value || !window.confirm('Delete the saved settings \'' + selected.value.name + '\'?')) {
                return;
            }

            const preset = selected.value;
            loading.value = true;

            axios.delete('/admin/import-presets/' + preset.id, {data: {_token: laravelCsrfToken}})
                .then(() => {
                    presets.value = presets.value.filter(item => item.id != preset.id);
                    selectedId.value = null;
                    showSuccess('Deleted \'' + preset.name + '\'');
                })
                .catch(error => showError(error, 'Could not delete saved settings'))
                .finally(() => loading.value = false);
        }

        Vue.watch(() => props.type, () => {
            selectedId.value = null;
            load();
        });

        Vue.onMounted(load);

        return {
            presets,
            selectedId,
            loading,
            message,
            messageType,
            applyPreset,
            saveAs,
            update,
            remove,
        };
    }
};
