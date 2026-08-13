import EditForm from './forms/BibleEditForm.vue.js';
import EditDialog from '../../bin/custom_vue/dialogs/EditDialog.vue.js';
import TruncateTooltip from '../../bin/custom_vue/components/Truncate.vue.js';
import YesNoSel from '../../bin/custom_vue/components/YesNoSelector.vue.js';
import ChipAlert from '../../bin/custom_vue/components/ChipAlert.vue.js';
import ChipBool from '../../bin/custom_vue/components/ChipBool.vue.js';
import ChipBoolAlt from '../../bin/custom_vue/components/ChipBoolAlt.vue.js';
import ActionDialog from './dialogs/ActionDialog.vue.js';
import ImportDialog from './dialogs/ImportDialog.vue.js';
import AudioDialog from './dialogs/AudioDialog.vue.js';
import { gridTemplateProps, useGrid } from '../../bin/custom_vue/composables/grid/Grid.vue.js';

const template = `<v-sheet>
            <h2 class='app'>
                Bibles
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

                <span v-for='action in bulkActions' class='float-left'>
                    <v-btn 
                        xdensity='comfortable'
                        size='small'
                        class='ml-2'
                        v-if="bootstrap.devToolsEnabled || !action.requireDevTools"
                        @click="handleBulkAction(action.action, $event)"
                        :prepend-icon='action.icon'
                    >
                        {{action.label}}

                        <template v-slot:append v-if='action.requireDevTools'>
                            <v-icon icon="mdi-flask-empty" color='warning'>
                                <v-tooltip text='Bible Development Tool' activator='parent'>
                            </v-tooltip>
                            </v-icon>
                        </template>
                    </v-btn>
                </span>
                <span class='clear-both'></span>
            </v-sheet>
            <v-sheet v-else class='mt-3 mb-12'>
                <v-btn size='small' prepend-icon='mdi-book' class='float-right' @click='openImport'>
                    Import Bible
                </v-btn>                
                <v-btn size='small' v-if='false' prepend-icon='mdi-plus' class='float-right' @click='clickEdit()'>
                    Add Bible
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
                <template v-slot:header.actions={column}>
                    <span>{{column.title}}</span>
                    
                    <!--
                        <v-chip
                            text='New'
                            @click='clickEdit()'
                            class='ml-4'
                        ></v-chip>
                    -->
                </template>
   
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
                        :maxLen='showExtraCols ? 40 : 70'>
                    </TruncateTooltip>
                </template>                   

                <template v-slot:item.shortname={item}>
                    <TruncateTooltip :text='item.shortname' :maxLen='20'></TruncateTooltip>
                </template>                          

                <template v-slot:item.copy={item}>
                    <TruncateTooltip :text='item.copy' :maxLen='34'></TruncateTooltip>
                </template>                           

                <template v-slot:item.has_module_file={item}>
                    <ChipBool
                        :value="item.has_module_file == '1'"
                        v-bind='chipProps'
                        @click-false="handleSingleAction('export', item)"
                    />
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
                
                <template v-slot:item.audio_enable={item}>
                    <ChipBool
                        :value="item.audio_enable == '1'"
                        v-bind='chipProps'
                        @click-true="clickAudio(item)" 
                        @ccclick-false="handleSingleAction('enable', item)" 
                    />
                </template>                 
                
                <template v-slot:item.official={item}>
                    <ChipBoolAlt
                        :value="item.official == '1'"
                        v-bind='chipProps'
                    />
                </template>                   

                <template v-slot:item.research={item}>
                    <ChipBoolAlt
                        :value="item.research == '1'"
                        v-bind='chipProps'
                        @click-true="handleSingleAction('unresearch', item)"
                        @click-false="handleSingleAction('research', item)"
                    />
                </template>    
                
                <template 
                    v-slot:item.updated_at={item}
                >
                    {{ formatDateTime(item.updated_at, "fullDateTime") }}
                </template>    

                <template v-slot:item.actions={item}>
                    <!-- Action Buttons -->
                    <!-- We only show 1 button directly, rest go in menu -->
                    <ChipAlert
                        v-if="item.has_module_file == '0'"
                        @click="handleSingleAction('export', item)" 
                        v-bind='chipProps'
                        text='Export'
                    />
                    <ChipAlert
                        v-else-if="item.needs_update == '1'
                        @click="handleSingleAction('update', item)" 
                        v-bind='chipProps'
                        text='Update'
                    />    
                    <v-chip v-else v-bind='chipProps'
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

                            <v-list-item @click="clickAudio(item)" v-if='item.audio_enable == "1" && item.installed == "1"'>
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-speaker"></v-icon>
                                </template>
                                <v-list-item-title>Audio</v-list-item-title>
                            </v-list-item>

                            <v-list-item v-if='false && item.official == "0"' @click='clickReplace(item)'>
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-book-arrow-left"></v-icon>
                                </template>
                                <v-list-item-title>Replace Text</v-list-item-title>
                            </v-list-item>
                            
                            <v-list-item @click="handleSingleAction('test', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-test-tube"></v-icon>
                                </template>
                                <v-list-item-title>Test</v-list-item-title>
                            </v-list-item>

                            <v-list-item @click="handleSingleAction('update', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-update"></v-icon>
                                </template>
                                <v-list-item-title>Update</v-list-item-title>
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
                                <v-list-item-title>Unistall</v-list-item-title>
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
                                v-if='bootstrap.devToolsEnabled' 
                                @click="handleSingleAction('export', item)"
                            >
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-export"></v-icon>
                                </template>
                                <v-list-item-title>Export Module</v-list-item-title>
                            </v-list-item>

                            <v-list-item 
                                v-if='bootstrap.devToolsEnabled' 
                                @click="handleSingleAction('meta', item)"
                            >
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-export"></v-icon>
                                </template>
                                <v-list-item-title>Module Settings</v-list-item-title>
                            </v-list-item>

                        </v-list>
                    </v-menu>
                </template>

            </v-data-table-server>    

            <ActionDialog 
                :action = 'selectedAction'
                :actions = 'bulkActions'
                :queue = 'actionQueue'
                @onClose='handleCloseActions'
                @onSave='handleSaveActions'
                @onSuccess='handleSuccessActions'
                @afterLeave='handleCloseActions'
            />          

            <ImportDialog 
                :showing = 'importShowing'
                :replace = 'importReplace'
                @onClose='closeImport'
                @onTest='testBible'
                @onSave='refreshGridRefreshWithExtras'
            />

            <AudioDialog 
                :recordId='audioManagingId'
                :record='selection'
                @onClose='closeAudio'
                @afterLeave='closeAudio'
            />

            <EditDialog
                :recordId='editingId'
                max-width='800'
                loadRecord
                recordType='Bible Settings'
                recordIndex='Bible'
                @onClose='closeEdit'
                @afterLeave='closeEdit'
                @onSave='refreshGridRefreshWithExtras'
                url='/admin/bibles'
                v-slot='{data, errors}'
            >
                <EditForm :record='data' :errors='errors'></EditForm>
                
            </EditDialog>   

        </v-sheet>`;

export default {
    inject: ['bootstrap'],
    setup(props) {
        let data = {
            url: '/admin/bibles/grid',
            gridData: {
                sidx: 'rank',
                sord: 'ASC',
                rows_per_page: 20,
                copyright_id: null,
                installed: null,
                enabled: null,
                official: null,
                research: null,
                has_module_file: null,
                lang: null
            },

            // Grid searchable fields (will be added to gridData as strings if don't exist)
            searchFields: [
                'name', 'shortname', 'module', 'copyright_id', 'year', 'lang', 'installed', 'enabled', 'official', 
                'research', 'has_module_file'
            ],
        };

        return useGrid(data, props);
    },
    components: {
        EditDialog,
        ActionDialog,
        ImportDialog,
        AudioDialog,
        TruncateTooltip,
        YesNoSel,
        ChipAlert,
        ChipBool,
        ChipBoolAlt,
        EditForm
    },
    template: template, 
    watch: {
        gridRows(is, was) {
            this.init();
        },
        extraCols(is, was) {
            // This is annoying and shouldn't be needed 
            // Commenting out for now
            // !is && this.gridResetRows();
        }
    },
    data() {
        return { 
            // gridCellProps: {class: 'pa-0'},
            chipProps: {
                size: 'small',
                density: 'comfortable'
            },
            extraCols: false,
            editing: false,
            editingId: null,
            audioManagingId: null,
            selection: null,
            selectedAction: null,
            actionQueue: null,
            importShowing: false,
            importReplace: null,
            inited: false,
            editingRecord: {},
            rowSelections: [],
            languagesWithBibles: bootstrap.languages,
            bulkActions: [
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
                    action: 'update',
                    label: 'Update',
                    dialogTitle: 'Update Bible',
                    confirmText: 'Are you sure that you want to update the selected Bibles?' + 
                        '  This will overwrite any local changes you have made to each Bible\'s text and settings.',
                    actioning: 'Updating',
                    icon: 'mdi-update',
                },
                {
                    action: 'test',
                    label: 'Test',
                    tag: 'button',
                    icon: 'mdi-test-tube',
                    autoConfirm: true,
                },                
                {
                    action: 'research',
                    label: '"Research"',
                    dialogTitle: 'Mark as "For Research Only"',
                    confirmText: 'Are you sure that you want to mark these Bibles for research only?',
                    actioning: 'Marking',
                    icon: 'mdi-flag'
                },                
                {
                    action: 'unresearch',
                    label: 'Not "Research"',
                    dialogTitle: 'Unmark as "For Research Only"',
                    confirmText: 'Are you sure that you want to unmark these Bibles for research only?',
                    actioning: 'Unmarking',
                    icon: 'mdi-flag-remove'
                },                
                {
                    action: 'revert',
                    label: 'Revert Changes',
                    dialogTitle: 'Revert Bible Changes',
                    confirmText: 'Are you sure that you want to revert all settings changes to the following Bibles?',
                    actioning: 'Reverting',
                    icon: 'mdi-undo-variant'
                },               
                {
                    action: 'delete',
                    label: 'Delete',
                    actioning: 'Deleting',
                    icon: 'mdi-trash-can'
                },
                {
                    action: 'export',
                    label: 'Export Module',
                    dialogTitle: 'Export Module File',
                    actioning: 'Exporting',
                    requireDevTools: true,
                    icon: 'mdi-export'
                },
                {
                    action: 'meta',
                    label: 'Module Settings',
                    dialogTitle: 'Module Settings',
                    confirmText: 'Are you sure that you want to export Bible settings changes (metadata) to these Bible module files?',
                    actioning: 'Updating Module Settings',
                    requireDevTools: true,
                    icon: 'mdi-export'
                },
            ]
        }
    },
    computed: {
        showExtraCols() {
            return this.extraCols;
        },
        headers() {
            var cols = [];

            cols.push({title: 'Name', key: 'name', width: 350, cellProps: {size: 'small', _class: 'd-inline-block text-truncate'}});
            cols.push({title: 'Short Name', key: 'shortname', width: 150});
            cols.push({title: 'Module', key: 'module', width: 150});

            cols.push({title: 'Language', key: 'lang', width: 150, searchComponent: 'v-autocomplete', searchProps: {
                'items': this.languagesWithBibles,
                'item-title': 'name',
                'item-value': 'code'
            }});

            if(this.showExtraCols) {
                cols.push({title: 'Copyright', key: 'copy', width: 250, searchComponent: 'v-autocomplete', searchField: 'copyright_id', searchProps: {
                    'items': bootstrap.copyrights,
                    'item-title': 'name',
                    'item-value': 'id'
                } });
            }

            cols.push({title: 'Year', key: 'year', width: 150});
            cols.push({title: 'Installed', key: 'installed', width: 50, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
            cols.push({title: 'Enabled', key: 'enabled', width: 50, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
            
            if(this.showExtraCols) {
                if(this.bootstrap.audio_enabled) {
                    cols.push({title: 'Audio', key: 'audio_enable', width: 50, searchComponent: 'YesNoSel', align: 'center'});
                }

                cols.push({title: 'Has File', key: 'has_module_file', width: 100, sortable: false, searchComponent: 'YesNoSel', align: 'center'});
                cols.push({title: 'Official*', key: 'official', width: 50, searchComponent: 'YesNoSel', align: 'center'});
                cols.push({title: 'Research**', key: 'research', width: 60, searchComponent: 'YesNoSel', align: 'center'});
                cols.push({title: 'Updated', key: 'updated_at', width: 150, searchable: false, align: 'center'});
            }
            
            cols.push({title: 'Rank', key: 'rank', width: 50, searchable: false, align: 'center'});
            cols.push({title: '', key: 'actions', sortable: false, width: 100, searchable: false, align: 'end'});

            return cols;
        },
        hasRowSelections() {
            return this.rowSelections.length > 0;
        }
    },
    methods: {        
        refreshGridRefreshWithExtras() {
            this.gridRefresh();
            this.loadBibleLanguage();
        },
        refreshGridClearSelections() {
            this.rowSelections = [];
            this.refreshGridRefreshWithExtras();
        },
        clickEdit(item) {
            this.closeAudio();

            if(item) {
                this.editingId = item.id;
            } else {
                this.editingId = -1;
            }
        },             
        closeEdit() {
            this.editingId = null;
            this.editingRecord = {};
        },
        clickAudio(item) {
            if(!item || item.audio_enable != '1') {
                return;
            }
            this.closeEdit();

            this.audioManagingId = item.id;
            this.selection = item;
            this.editingId = null;
        },
        closeAudio() {
            this.audioManagingId = null;
            this.editingId = null;
            this.selection = null;
        },
        closeDialogs() {
            this.closeEdit();
            this.closeAudio();
        },
        init() {
            if(this.inited) {
                return;
            }

            this.inited = true;
            this.loadBibleLanguage();
        },
        loadBibleLanguage() {
            axios.get('/admin/bibles/languages').then(response => {
                this.languagesWithBibles = response.data.languages;
            }).catch(error => {
                console.error('Error loading languages:', error);
            });
        },
        openImport() {
            this.importShowing = true;
        },
        clickReplace(item) {
            this.importReplace = item.id;
            this.importShowing = true;
        },
        closeImport() {
            this.importShowing = false;
            this.importReplace = null;
        },
        clickBookList(item) {
            if(item.book_list == '0') {
                return;
            }

            this.blLanguage = item.code;
            this.selectedLanguage = item;
        },
        closeBookList() {
            this.blLanguage = null;
            this.selectedLanguage = {};
        },
        bookListColor(item) {
            if(item.bibles == '0') {
                return 'grey';
            } else {
                return item.book_list == '1' ? 'green' : 'red';
            }
        },
        testBible(item) {
            this.handleSingleAction('test', item);
        },
        handleBulkAction(action, event) {
            var s = this.rowSelections;
            var queue = this.gridRows.filter(item => s.includes(item.id));
            this.actionHelper(action, queue);
        },
        handleSingleAction(action, item) {
            var queue = [item];
            this.actionHelper(action, queue);
        },
        actionHelper(action, queue) {
            this.selectedAction = action || null;
            this.actionQueue = queue || null;
        },
        handleCloseActions() {
            this.selectedAction = null;
        },
        handleSaveActions() {
            this.refreshGridClearSelections();
        },
        handleSuccessActions() {

            this.rowSelections = [];
        },
        closeActionsRefresh() {
            this.handleCloseActions();
            this.refreshGridClearSelections();
        },
        formatDateTime(datetime, format) {
            var pts = datetime.split(' ');
            var dpts = pts[0].split('-');
            var tpts = pts[1].split(':');

            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            var str = dpts[2] + ' ' + months[dpts[1] - 1] + ' ' + dpts[0] + ' ' + tpts[0] + ':' + tpts[1];

            return str;
        }
    }
}
