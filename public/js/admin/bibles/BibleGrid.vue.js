// import BibleForm from './dialogs_forms/BibleEditForm.vue.js';
import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';
import ActionDialog from './dialogs_forms/ActionDialog.vue.js';
import { gridTemplateProps, useGrid } from '/js/bin/custom_vue/composables/Grid.vue.js';

const template = `<v-sheet>
            <h2>Bibles</h2>

            {{rowSelections}}

            <v-row v-if='hasRowSelections'>
                <v-col>
                With Selections:
                </v-col>

                <v-col v-for='action in bulkActions'>
                    <v-btn 
                        v-if="bootstrap.devToolsEnabled || !action.requireDevTools"
                        @click="handleBulkAction(action.action, $event)"
                    >
                        {{action.label}}

                        <span v-if='action.requireDevTools'>(Dev)</span>
                    </v-btn>
                </v-col>

            </v-row>
            
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
                    actioning: 'Installing'
                },
                {
                    action: 'uninstall',
                    label: 'Uninstall',
                    actioning: 'Uninstalling'
                },
                {
                    action: 'enable',
                    label: 'Enable',
                    actioning: 'Enabling'
                },
                {
                    action: 'disable',
                    label: 'Disable',
                    actioning: 'Disabling'
                },                
                {
                    action: 'update',
                    label: 'Update',
                    actioning: 'Updating'
                },
                {
                    action: 'test',
                    label: 'Test',
                    tag: 'button',
                    autoConfirm: true,
                },                
                {
                    action: 'research',
                    label: 'Mark as "Research"',
                    dialogTitle: 'Mark as "For Research Only"',
                    confirmText: 'Are you sure that you want to mark these Bibles for research only?',
                    actioning: 'Marking'
                },                
                {
                    action: 'unresearch',
                    label: 'Unmark as "Research"',
                    dialogTitle: 'Unmark as "For Research Only"',
                    confirmText: 'Are you sure that you want to unmark these Bibles for research only?',
                    actioning: 'Unmarking'
                },                
                {
                    action: 'revert',
                    label: 'Revert Changes',
                    confirmText: 'Are you sure that you want to revert all changes to the following Bibles?',
                    actioning: 'Reverting'
                },               
                {
                    action: 'delete',
                    label: 'Delete',
                    actioning: 'Deleting'
                },
                {
                    action: 'export',
                    label: 'Export Module File',

                    kind: 'BibleManager.Components.Elements.Button',
                    
                    actioning: 'Exporting',
                    requireDevTools: true
                },
                {
                    action: 'meta',
                    label: 'Update Module File',

                    kind: 'BibleManager.Components.Elements.Button',

                    actioning: 'Updating Meta',
                    requireDevTools: true
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
