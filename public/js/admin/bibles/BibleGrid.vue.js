import EditForm from './dialogs_forms/BibleEditForm.vue.js';
import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';
import TruncateTooltip from '/js/bin/custom_vue/components/Truncate.vue.js';
import YesNoSel from '/js/bin/custom_vue/components/YesNoSelector.vue.js';
import ActionDialog from './dialogs_forms/ActionDialog.vue.js';
import ImportDialog from './dialogs_forms/ImportDialog.vue.js';
import { gridTemplateProps, useGrid } from '/js/bin/custom_vue/composables/Grid.vue.js';

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
                <v-btn size='small' prepend-icon='mdi-plus' class='float-right' @click='clickEdit'>
                    Add Bible
                </v-btn>
                <span class='float-right'>&nbsp;</span>
                <span class='clear-both'></span>
            </v-sheet>
            
            <v-data-table-server
                ` + gridTemplateProps + `
                
                :headers="headers"
                show-current-page
                :loading='loading ? "primary-darken-1" : false'
                fixed-header
                single-select
                hover
                density='compact'
                color='#333333'
                show-select
                :cell-props='gridCellProps'
                :header-props='gridCellProps'
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
                    <tr>
                        <td>
                            <v-chip text='Reset' @click='gridResetSearch'></v-chip>
                        </td>
                        <td v-for='col in headers'>
                            <component 
                                :is="col.searchComponent || 'v-text-field'" 
                                v-if='col.searchable != false'
                                v-model="gridData[col.searchField || col.key]" 
                                class="ma-0 mr-1 pa-0" 
                                density="compact" 
                                :placeholder="'Search ' + col.title + ' ...'" 
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
                        :maxLen='showExtraCols ? 30 : 50'>
                    </TruncateTooltip>
                </template>                   

                <template v-slot:item.shortname={item}>
                    <TruncateTooltip :text='item.shortname' :maxLen='10'></TruncateTooltip>
                </template>                          

                <template v-slot:item.copy={item}>
                    <TruncateTooltip :text='item.copy' :maxLen='20'></TruncateTooltip>
                </template>                           

                <template v-slot:item.has_module_file={item}>
                    <v-chip :size='chipSize'
                        :text="item.has_module_file == '1' ? 'Yes' : 'No'"
                    ></v-chip>
                </template>                        

                <template v-slot:item.installed={item}>
                    <v-chip 
                        v-if="item.installed == '1'" 
                        @click="handleSingleAction('uninstall', item)" 
                        :size='chipSize'
                        text='Yes'
                    />
                    <v-chip 
                        v-else 
                        @click="handleSingleAction('install', item)" 
                        :size='chipSize'
                        text='No'
                    />
                </template>                 

                <template v-slot:item.enabled={item}>
                    <v-chip 
                        v-if="item.enabled == '1'" 
                        @click="handleSingleAction('disable', item)" 
                        :size='chipSize'
                        text='Yes'
                    />
                    <v-chip 
                        v-else 
                        @click="handleSingleAction('enable', item)" 
                        :size='chipSize'
                        text='No'
                    />
                </template>                 
                
                <template v-slot:item.official={item}>
                    <v-chip :size='chipSize'
                        :text="item.official == '1' ? 'Yes' : 'No'"
                    ></v-chip>
                </template>                   

                <template v-slot:item.research={item}>
                    <v-chip 
                        v-if="item.research == '1'" 
                        @click="handleSingleAction('unresearch', item)" 
                        :size='chipSize'
                        text='Yes'
                    />
                    <v-chip 
                        v-else 
                        @click="handleSingleAction('research', item)" 
                        :size='chipSize'
                        text='No'
                    />
                </template>    
                
                <template 
                    v-slot:item.updated_at={item}
                >
                    {{ formatDateTime(item.updated_at, "fullDateTime") }}
                </template>    

                <template v-slot:item.actions={item}>
                    <v-chip :size='chipSize'
                        text='Edit'
                        @click='clickEdit(item)'
                    ></v-chip> 

                    <v-chip 
                        v-if='item.official == "0"'
                        :size='chipSize'
                        text='Replace Text'
                        @click='clickReplace(item)'
                    ></v-chip>                   
                </template>

            </v-data-table-server>    

            <ActionDialog 
                :action = 'selectedAction'
                :actions = 'bulkActions'
                :queue = 'actionQueue'
                @onClose='closeActions'
                @onSave='gridRefresh'

            ></ActionDialog>            

            <ImportDialog 
                :showing = 'importShowing'
                :replace = 'importReplace'
                @onClose='closeImport'
                @onTest='testBible'
                @onSave='gridRefresh'

            ></ImportDialog>

            <EditDialog
                :recordId='editingId'
                max-width='800'
                loadRecord
                recordType='Bible'
                recordIndex='Bible'
                @onClose='closeEdit'
                @afterLeave='closeEdit'
                @onSave='gridRefresh'
                url='/admin/bibles'
                v-slot='{data}'
            >
                <EditForm :record='data'></EditForm>
                
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
                rows_per_page: 10,
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
        TruncateTooltip,
        YesNoSel,
        EditForm
    },
    template: template, 
    data() {
        return { 
            gridCellProps: {class: 'pa-0'},
            chipSize: 'small',
            chipDensity: 'default',
            extraCols: false,
            editing: false,
            editingId: null,
            selectedAction: null,
            actionQueue: null,
            importShowing: false,
            importReplace: null,
            editingRecord: {},
            rowSelections: [],
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
                    // :todo what does this actually do?  Controller action 'update' is for saving module meta (IE PUT)
                    // dialogTitle: 'Update Bible Text from Module'
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
                    label: 'Update Module',
                    dialogTitle: 'Update Module File',
                    confirmText: 'Are you sure that you want to save settings changes to these Bible module files?',
                    actioning: 'Updating Meta',
                    requireDevTools: true,
                    icon: 'mdi-update'
                },
            ]
        }
    },
    computed: {
        showExtraCols() {
            return this.extraCols
        },
        headers() {
            if(this.showExtraCols) {
                return [
                    {title: 'Name', key: 'name', width: 250, cellProps: {size: 'small', _class: 'd-inline-block text-truncate'}},
                    {title: 'Short Name', key: 'shortname', width: 150},
                    {title: 'Module', key: 'module', width: 150},
                    {title: 'Language', key: 'lang', width: 150, searchComponent: 'v-autocomplete', searchProps: {
                        'items': bootstrap.languages,
                        'item-title': 'name',
                        'item-value': 'code'
                    }},
                    {title: 'Copyright', key: 'copy', width: 250, searchComponent: 'v-autocomplete', searchField: 'copyright_id', searchProps: {
                        'items': bootstrap.copyrights,
                        'item-title': 'name',
                        'item-value': 'id'
                    } },
                    {title: 'Year', key: 'year', width: 150},
                    {title: 'Installed', key: 'installed', width: 50, searchComponent: 'YesNoSel'},
                    {title: 'Enabled', key: 'enabled', width: 50, searchComponent: 'YesNoSel'},
                    {title: 'Official', key: 'official', width: 50, searchComponent: 'YesNoSel'},
                    {title: 'Research **', key: 'research', width: 50, searchComponent: 'YesNoSel'},
                    {title: 'Has File', key: 'has_module_file', width: 100, searchComponent: 'YesNoSel'},
                    {title: 'Updated', key: 'updated_at', width: 150, searchable: false},
                    {title: 'Rank', key: 'rank', width: 100, searchable: false},
                    {title: 'Actions', key: 'actions', sortable: false, width: 100, searchable: false},
                ];                

            } else {
                return [
                    {title: 'Name', key: 'name', width: 250, cellProps: {size: 'small', _class: 'd-inline-block text-truncate'}},
                    {title: 'Short Name', key: 'shortname', width: 150},
                    {title: 'Module', key: 'module', width: 150},
                    {title: 'Language', key: 'lang', width: 150, searchComponent: 'v-autocomplete', searchProps: {
                        'items': bootstrap.languages,
                        'item-title': 'name',
                        'item-value': 'code'
                    }},
                    {title: 'Year', key: 'year', width: 150},
                    {title: 'Installed', key: 'installed', width: 50, searchComponent: 'YesNoSel'},
                    {title: 'Enabled', key: 'enabled', width: 50, searchComponent: 'YesNoSel'},
                    {title: 'Rank', key: 'rank', width: 100, searchable: false},
                    {title: 'Actions', key: 'actions', sortable: false, width: 100, searchable: false},
                ];
            }
        },
        hasRowSelections() {
            return this.rowSelections.length > 0;
        }
    },
    methods: {        
        clickEdit(item) {
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
            console.log('test Bible', item);
            this.handleSingleAction('test', item);
        },
        handleBulkAction(action, event) {
            console.log('handleBulkAction', arguments);
            var s = this.rowSelections;
            var queue = this.gridRows.filter(item => s.includes(item.id));
            this.actionHelper(action, queue);
        },
        handleSingleAction(action, item) {
            console.log('handleSingleAction', arguments);
            var queue = [item];
            this.actionHelper(action, queue);
        },
        actionHelper(action, queue) {
            this.selectedAction = action || null;
            this.actionQueue = queue || null;
        },
        closeActions() {
            // this.gridRefresh();
            this.selectedAction = null;
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
