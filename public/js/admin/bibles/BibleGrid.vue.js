// import BibleForm from './dialogs_forms/BibleEditForm.vue.js';
import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';
import ActionDialog from './dialogs_forms/ActionDialog.vue.js';
import { gridTemplateProps, useGrid } from '/js/bin/custom_vue/composables/Grid.vue.js';

const template = `<v-sheet>
            <h2>

            Bibles

            </h2>

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
                <v-btn size='small' prepend-icon='mdi-book' class='float-right'>
                    Import Bible
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

                <template v-slot:item.installed={item}>
                    <v-chip
                        :text="item.installed == '1' ? 'Yes' : 'No'"
                    ></v-chip>
                </template>                 

                <template v-slot:item.enabled={item}>
                    <v-chip v-if="item.enabled == '1'" @click="handleSingleAction('disable', item)">
                        Yes
                    </v-chip>
                    <v-chip v-else @click="handleSingleAction('enable', item)">
                        No
                    </v-chip>
                </template>                 
                
                <template v-slot:item.official={item}>
                    <v-chip
                        :text="item.official == '1' ? 'Yes' : 'No'"
                    ></v-chip>
                </template>                   

                <template v-slot:item.research={item}>
                    <v-chip
                        :text="item.research == '1' ? 'Yes' : 'No'"
                    ></v-chip>
                </template>    

                <template v-slot:item.actions={item}>
                    <v-chip
                        text='Edit'
                        @click='clickEdit(item)'
                    ></v-chip>                    
                  
                    <!--
                        <v-chip
                            text='Edit PRE'
                            @click='clickEditPre(item)'
                        ></v-chip>
                    -->
                </template>

            </v-data-table-server>    

            <ActionDialog 
                :action = 'selectedAction'
                :actions = 'bulkActions'
                :queue = 'actionQueue'
                @onClose='closeActions'

            ></ActionDialog>

        </v-sheet>`;

/*
        <EditDialog
            :recordId='editingId'
            loadRecord
            recordType='Language'
            @onClose='closeEdit'
            @afterLeave='closeEdit'
            @onSave='gridRefresh'
            url='/admin/bibles/languages'
            v-slot='{data}'
        >
            <LanguageForm :record='data'></LanguageForm>
        </EditDialog>   

*/

export default {
    inject: ['bootstrap'],
    setup(props) {
        let data = {
            url: '/admin/bibles/grid',
            gridData: {
                sidx: 'name',
                sord: 'ASC',
                rows_per_page: 10,
            },

            // Grid searchable fields (will be added to gridData as strings if don't exist)
            // searchFields: ['code', 'name', 'native_name', 'family', 'bibles_min', 'bibles_max'],
        };

        return useGrid(data, props);
    },
    components: {
        EditDialog,
        ActionDialog
        // LanguageForm
    },
    template: template, 
    data() {
        return { 
            editing: false,
            editingId: null,
            selectedAction: null,
            actionQueue: null,
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
        headers() {
            return [
                {title: 'Name', key: 'name'},
                {title: 'Short Name', key: 'shortname'},
                {title: 'Module', key: 'module'},
                {title: 'Has File', key: 'has_module_file'},
                {title: 'Language', key: 'lang'},
                {title: 'Copyright', key: 'copy'},
                {title: 'Year', key: 'year'},
                {title: 'Installed', key: 'installed'},
                {title: 'Enabled', key: 'enabled'},
                {title: 'Official', key: 'official'},
                {title: 'Research **', key: 'research'},
                {title: 'Updated', key: 'updated_at'},
                {title: 'Rank', key: 'rank'},

                {title: 'Actions', key: 'actions', sortable: false},
            ];
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
            console.log('action', action);
            console.log('queue', queue);

            this.selectedAction = action || null;
            this.actionQueue = queue || null;
        },
        closeActions() {
            // this.gridRefresh();
            this.selectedAction = null;
        }
    }
}
