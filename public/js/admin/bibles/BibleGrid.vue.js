// import LanguageForm from './LanguageEditForm.vue.js';
import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';
import { gridTemplateProps, useGrid } from '/js/bin/custom_vue/composables/Grid.vue.js';

const template = `<v-sheet>
            <h2>Bibles</h2>

            {{rowSelections}}

            <v-row v-if='hasRowSelections'>
                <v-col>
                With Selections:
                </v-col>

                <v-col v-for='action in bulkActions'>

                    <v-button >
                        {{action.label}}
                    </v-button>
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
                    <v-chip
                        :text="item.enabled == '1' ? 'Yes' : 'No'"
                    ></v-chip>
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
        // LanguageForm
    },
    template: template, 
    data() {
        return { 
            editing: false,
            editingId: null,
            editingRecord: {},
            rowSelections: [],
            blLanguage: null,
            test: 'hahaha',
            bulkActions: [
                {
                    action: 'install',
                    label: 'Install',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiInstall',
                    actioning: 'Installing'
                },
                {
                    action: 'uninstall',
                    label: 'Uninstall',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiUninstall',
                    actioning: 'Uninstalling'
                },
                {
                    action: 'enable',
                    label: 'Enable',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiEnable',
                    actioning: 'Enabling'
                },
                {
                    action: 'disable',
                    label: 'Disable',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiDisable',
                    actioning: 'Disabling'
                },                
                {
                    action: 'update',
                    label: 'Update',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiUpdateModule',
                    actioning: 'Updating'
                },
                {
                    action: 'test',
                    label: 'Test',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiTest',
                    actioning: 'Testing'
                },                
                {
                    action: 'research',
                    label: 'Mark as "Research"',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiFlagResearch',
                    actioning: 'Marking'
                },                
                {
                    action: 'unresearch',
                    label: 'Unmark as "Research"',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiUnflagResearch',
                    actioning: 'Unmarking'
                },                
                {
                    action: 'revert',
                    label: 'Revert Changes',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiRevert',
                    actioning: 'Reverting'
                },               
                {
                    action: 'revert', // ????
                    label: 'Delete',
                    tag: 'button',
                    classes: 'button bulk',
                    ontap: 'multiDelete',
                    actioning: 'Deleting'
                },
                {
                    action: 'export',
                    label: 'Export Module File',

                    kind: 'BibleManager.Components.Elements.Button',
                    
                    classes: 'button bulk',
                    ontap: 'multiExport',
                    actioning: 'Exporting',
                    requireDevTools: true
                },
                {
                    action: 'meta',
                    label: 'Update Module File',

                    kind: 'BibleManager.Components.Elements.Button',

                    classes: 'button bulk',
                    ontap: 'multiUpdate',
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
        }
    }
}

// vue-demi ?? for ??
// vuedate (validation)