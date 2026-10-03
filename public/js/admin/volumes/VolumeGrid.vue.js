import EditForm from './forms/VolumeEditForm.vue.js';
import EditDialog from '../../bin/custom_vue/dialogs/EditDialog.vue.js';
import TruncateTooltip from '../../bin/custom_vue/components/Truncate.vue.js';
import YesNoSel from '../../bin/custom_vue/components/YesNoSelector.vue.js';
import ChipBool from '../../bin/custom_vue/components/ChipBool.vue.js';
import ChipBoolAlt from '../../bin/custom_vue/components/ChipBoolAlt.vue.js';
import ActionDialog from './dialogs/ActionDialog.vue.js';
import { gridTemplateProps, useGrid } from '../../bin/custom_vue/composables/grid/Grid.vue.js';

const template = `<v-sheet>
            <h2 class='app'>
                Volumes
            </h2>

            <v-switch
                v-model='extraCols'
                label='Extra Columns'
                color='primary'
            ></v-switch>

            <v-sheet v-if='hasRowSelections' class='mt-3 mb-12'>
                <span class='float-left'>
                    With Selections:
                </span>

                <span v-for='action in bulkActionsMulti' class='float-left'>
                    <v-btn
                        size='small'
                        class='ml-2'
                        @click="handleBulkAction(action.action)"
                        :prepend-icon='action.icon'
                    >
                        {{action.label}}
                    </v-btn>
                </span>
                <span class='clear-both'></span>
            </v-sheet>
            <v-sheet v-else class='mt-3 mb-12'>
                <v-btn size='small' prepend-icon='mdi-plus' class='float-right' @click='clickEdit()'>
                    Add Volume
                </v-btn>
                <span class='float-right'>&nbsp;</span>
                <span class='clear-both'></span>
            </v-sheet>

            <v-data-table-server
                ` + gridTemplateProps + `

                :headers="headers"
                show-select
                item-value="id"
                v-model='rowSelections'
            >
                <template v-slot:thead>
                    <tr class='grid-thead-search'>
                        <td>
                            <v-chip text='Reset' @click='gridResetSearch' size='small' class='ml-1'></v-chip>
                        </td>
                        <td v-for='col in headers'>
                            <component
                                :is="col.searchComponent || 'v-text-field'"
                                v-if='col.searchable != false'
                                v-model="gridData[col.searchField || col.key]"
                                class="ma-0 mr-1 pa-0 text-caption"
                                density="compact"
                                :placeholder="col.searchLabel === false ? null : 'Search ' + col.title + ' ...'"
                                hide-details
                                clearable
                                v-bind='col.searchProps || null'
                            >
                            </component>
                        </td>
                    </tr>
                </template>

                <template v-slot:item.name={item}>
                    <TruncateTooltip
                        :text='item.name'
                        :maxLen='extraCols ? 40 : 70'>
                    </TruncateTooltip>
                </template>

                <template v-slot:item.shortname={item}>
                    <TruncateTooltip :text='item.shortname' :maxLen='20'></TruncateTooltip>
                </template>

                <template v-slot:item.type={item}>
                    {{ typeLabel(item.type) }}
                </template>

                <template v-slot:item.copy={item}>
                    <TruncateTooltip :text='item.copy' :maxLen='34'></TruncateTooltip>
                </template>

                <template v-slot:item.installed={item}>
                    <ChipBool
                        :value="item.installed == '1'"
                        v-bind='chipProps'
                        @click-true="handleSingleAction('uninstall', item)"
                        @click-false="handleSingleAction('install', item)"
                    />
                </template>

                <template v-slot:item.enabled={item}>
                    <ChipBool
                        :value="item.enabled == '1'"
                        v-bind='chipProps'
                        @click-true="handleSingleAction('disable', item)"
                        @click-false="handleSingleAction('enable', item)"
                    />
                </template>

                <template v-slot:item.is_default={item}>
                    <ChipBool
                        :value="item.is_default == '1'"
                        v-bind='chipProps'
                        @click-false="handleSingleAction('default', item)"
                    />
                </template>                   

                <template v-slot:item.official={item}>
                    <ChipBoolAlt
                        :value="item.official == '1'"
                        v-bind='chipProps'
                    />
                </template>

                <template v-slot:item.updated_at={item}>
                    {{ formatDateTime(item.updated_at) }}
                </template>

                <template v-slot:item.actions={item}>
                    <v-chip v-bind='chipProps'
                        text='Edit'
                        @click='clickEdit(item)'
                    />

                    <v-menu>
                        <template v-slot:activator="{ props }">
                            <v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" density='compact' size='small'></v-btn>
                        </template>

                        <v-list density='compact'>
                            <v-list-item @click="clickEdit(item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-pencil"></v-icon>
                                </template>
                                <v-list-item-title>Edit</v-list-item-title>
                            </v-list-item>

                            <v-list-item v-if='item.installed == "0"' @click="handleSingleAction('install', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-plus-box"></v-icon>
                                </template>
                                <v-list-item-title>Install</v-list-item-title>
                            </v-list-item>
                            <v-list-item v-else @click="handleSingleAction('uninstall', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-minus-box"></v-icon>
                                </template>
                                <v-list-item-title>Uninstall</v-list-item-title>
                            </v-list-item>

                            <v-list-item
                                v-if='item.installed == "1" && item.enabled == "0"'
                                @click="handleSingleAction('enable', item)"
                            >
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-lock-open"></v-icon>
                                </template>
                                <v-list-item-title>Enable</v-list-item-title>
                            </v-list-item>
                            <v-list-item
                                v-else-if='item.installed == "1" && item.enabled == "1"'
                                @click="handleSingleAction('disable', item)"
                            >
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-lock"></v-icon>
                                </template>
                                <v-list-item-title>Disable</v-list-item-title>
                            </v-list-item>

                            <v-list-item 
                                v-if='item.installed == "1" && item.enabled == "1" && item.is_default != "1"' 
                                @click="handleSingleAction('default', item)"
                            >
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-star"></v-icon>
                                </template>
                                <v-list-item-title>Make Default</v-list-item-title>
                            </v-list-item>

                            <v-list-item v-if='item.official == "0"' @click="handleSingleAction('delete', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-trash-can"></v-icon>
                                </template>
                                <v-list-item-title>Delete</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                </template>

            </v-data-table-server>

            <ActionDialog
                :action='selectedAction'
                :actions='bulkActions'
                :queue='actionQueue'
                @onClose='handleCloseActions'
                @onSuccess='handleSuccessActions'
            />

            <EditDialog
                :recordId='editingId'
                max-width='800'
                loadRecord
                recordType='Volume Settings'
                recordIndex='Volume'
                @onClose='closeEdit'
                @afterLeave='closeEdit'
                @onSave='refreshGrid'
                url='/admin/volumes'
                v-slot='{data, errors}'
            >
                <EditForm :record='data' :errors='errors'></EditForm>
            </EditDialog>

        </v-sheet>`;

const bulkActions = [
    {
        // Only ever one default per type, so this is a row action, never a bulk one
        action: 'default',
        label: 'Make Default',
        dialogTitle: 'Make Default Volume',
        confirmText: 'Make this the default volume of its type?  It replaces the current default.',
        actioning: 'Setting default',
        icon: 'mdi-star',
        single: true,
    },
    {
        action: 'install',
        label: 'Install',
        actioning: 'Installing',
        icon: 'mdi-plus-box',
    },
    {
        action: 'uninstall',
        label: 'Uninstall',
        actioning: 'Uninstalling',
        icon: 'mdi-minus-box',
    },
    {
        action: 'enable',
        label: 'Enable',
        actioning: 'Enabling',
        icon: 'mdi-lock-open',
    },
    {
        action: 'disable',
        label: 'Disable',
        actioning: 'Disabling',
        icon: 'mdi-lock',
    },
    {
        action: 'delete',
        label: 'Delete',
        actioning: 'Deleting',
        icon: 'mdi-trash-can',
    },
];

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export default {
    components: {
        EditDialog,
        ActionDialog,
        TruncateTooltip,
        YesNoSel,
        ChipBool,
        ChipBoolAlt,
        EditForm
    },
    template: template,
    setup(props) {
        const bootstrap = Vue.inject('bootstrap');

        const grid = useGrid({
            url: '/admin/volumes/grid',
            gridData: {
                sidx: 'rank',
                sord: 'ASC',
                rows_per_page: 20,
                type: null,
                lang: null,
                copyright_id: null,
                installed: null,
                enabled: null,
                official: null,
                is_default: null,
            },

            // Grid searchable fields (will be added to gridData as strings if don't exist)
            searchFields: [
                'name', 'shortname', 'module', 'type', 'lang', 'copyright_id', 'year', 'installed', 'enabled', 'official',
                'is_default',
            ],
        }, props);

        const chipProps = {
            size: 'small',
            density: 'comfortable'
        };

        const extraCols = Vue.ref(false);
        const editingId = Vue.ref(null);
        const selectedAction = Vue.ref(null);
        const actionQueue = Vue.ref(null);
        const rowSelections = Vue.ref([]);
        const languagesWithVolumes = Vue.ref([]);

        const typeList = Object.entries(bootstrap.volume_types || {}).map(([value, type]) => ({value, title: type.label}));

        const hasRowSelections = Vue.computed(() => rowSelections.value.length > 0);

        const headers = Vue.computed(() => {
            const cols = [];

            cols.push({title: 'Name', key: 'name', width: 350});
            cols.push({title: 'Short Name', key: 'shortname', width: 150});
            cols.push({title: 'Module', key: 'module', width: 150});
            cols.push({title: 'Type', key: 'type', width: 150, searchComponent: 'v-select', searchProps: {
                'items': typeList,
            }});

            cols.push({title: 'Language', key: 'lang', width: 150, searchComponent: 'v-autocomplete', searchProps: {
                'items': languagesWithVolumes.value,
                'item-title': 'name',
                'item-value': 'code'
            }});

            if(extraCols.value) {
                cols.push({title: 'Copyright', key: 'copy', width: 250, searchComponent: 'v-autocomplete', searchField: 'copyright_id', searchProps: {
                    'items': bootstrap.copyrights,
                    'item-title': 'name',
                    'item-value': 'id'
                }});
            }

            cols.push({title: 'Year', key: 'year', width: 150});
            cols.push({title: 'Installed', key: 'installed', width: 50, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
            cols.push({title: 'Enabled', key: 'enabled', width: 50, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
            cols.push({title: 'Default', key: 'is_default', width: 50, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});

            if(extraCols.value) {
                cols.push({title: 'Official', key: 'official', width: 50, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
                cols.push({title: 'Updated', key: 'updated_at', width: 150, searchable: false, align: 'center'});
            }

            cols.push({title: 'Rank', key: 'rank', width: 50, searchable: false, align: 'center'});
            cols.push({title: '', key: 'actions', sortable: false, width: 100, searchable: false, align: 'end'});

            return cols;
        });

        function typeLabel(type) {
            const settings = bootstrap.volume_types ? bootstrap.volume_types[type] : null;
            return settings ? settings.label : type;
        }

        function loadLanguages() {
            axios.get('/admin/volumes/languages').then(response => {
                languagesWithVolumes.value = response.data.languages;
            }).catch(error => {
                console.error('Error loading languages:', error);
            });
        }

        function refreshGrid() {
            grid.gridRefresh();
            loadLanguages();
        }

        function clickEdit(item) {
            editingId.value = item ? item.id : -1;
        }

        function closeEdit() {
            editingId.value = null;
        }

        function handleBulkAction(action) {
            const s = rowSelections.value;
            actionHelper(action, grid.gridRows.value.filter(item => s.includes(item.id)));
        }

        function handleSingleAction(action, item) {
            actionHelper(action, [item]);
        }

        function actionHelper(action, queue) {
            selectedAction.value = action || null;
            actionQueue.value = queue || null;
        }

        function handleCloseActions() {
            // Refresh even on error: some of the queue may have succeeded
            selectedAction.value = null;
            refreshGrid();
        }

        function handleSuccessActions() {
            rowSelections.value = [];
        }

        function formatDateTime(datetime) {
            if(!datetime) {
                return '';
            }

            const pts = datetime.split(' ');
            const dpts = pts[0].split('-');
            const tpts = (pts[1] || '00:00').split(':');

            return dpts[2] + ' ' + months[dpts[1] - 1] + ' ' + dpts[0] + ' ' + tpts[0] + ':' + tpts[1];
        }

        Vue.onMounted(loadLanguages);

        return {
            ...grid,
            bootstrap,
            bulkActions,
            bulkActionsMulti: bulkActions.filter(action => !action.single),
            chipProps,
            extraCols,
            editingId,
            selectedAction,
            actionQueue,
            rowSelections,
            hasRowSelections,
            headers,
            typeLabel,
            refreshGrid,
            clickEdit,
            closeEdit,
            handleBulkAction,
            handleSingleAction,
            handleCloseActions,
            handleSuccessActions,
            formatDateTime,
        };
    }
}
