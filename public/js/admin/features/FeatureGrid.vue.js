import TruncateTooltip from '../../bin/custom_vue/components/Truncate.vue.js';
import ChipBool from '../../bin/custom_vue/components/ChipBool.vue.js';
import ActionDialogFeatures from './dialogs/ActionDialog.vue.js';
import { gridTemplateProps, useGrid } from '../../bin/custom_vue/composables/grid/Grid.vue.js';

 const template = `<v-sheet class='feature-grid-container'>
            <h2 class='app'>
                Features
            </h2>

            <v-sheet class='feature-bulk-row mt-3 mb-6'>
                <template v-if='hasRowSelections'>
                    <span class='float-left'>
                        With Selections:
                    </span>

                    <span v-for='action in bulkActions' class='float-left'>
                        <v-btn 
                            xdensity='comfortable'
                            size='small'
                            class='ml-2'
                            @click="handleBulkAction(action.action, $event)"
                            :prepend-icon='action.icon'
                        >
                            {{action.label}}
                        </v-btn>
                    </span>
                </template>
                <template v-else>
                    <span class='feature-bulk-placeholder'>&nbsp;</span>
                </template>

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
                        :maxLen='60'>
                    </TruncateTooltip>
                </template>                   

                <template v-slot:item.language_name={item}>
                    <TruncateTooltip :text='item.language_name' :maxLen='20'></TruncateTooltip>
                </template>                          

                <template v-slot:item.description={item}>
                    <TruncateTooltip :text='item.description' :maxLen='50'></TruncateTooltip>
                </template>                           

                <template v-slot:item.code={item}>
                    <TruncateTooltip :text='item.code' :maxLen='30'></TruncateTooltip>
                </template>

                <template v-slot:item.installed={item}>
                    <ChipBool
                        :value="item.installed == '1' || item.installed === true"
                        v-bind='chipProps'
                        @click-true="handleSingleAction('uninstall', item)" 
                        @click-false="handleSingleAction('install', item)"
                    />
                </template>                 

                <template v-slot:item.enabled={item}>
                    <ChipBool
                        :value="item.enabled == '1' || item.enabled === true"
                        v-bind='chipProps'
                        @click-true="handleSingleAction('disable', item)"
                        @click-false="handleSingleAction('enable', item)"
                    />
                </template>

                <template v-slot:item.actions={item}>
                    <v-menu>
                        <template v-slot:activator="{ props }">
                            <v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" density='compact' size='small'></v-btn>
                        </template>

                        <v-list density='compact'>
                            <v-list-item v-if='item.installed == "0" || item.installed === false' @click="handleSingleAction('install', item)">
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

                            <v-list-item v-if='(item.installed == "1" || item.installed === true) && (item.enabled == "0" || item.enabled === false)' @click="handleSingleAction('enable', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-lock-open"></v-icon>
                                </template>
                                <v-list-item-title>Enable</v-list-item-title>
                            </v-list-item>
                            <v-list-item v-else-if='item.enabled == "1" || item.enabled === true' @click="handleSingleAction('disable', item)">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-lock"></v-icon>
                                </template>
                                <v-list-item-title>Disable</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                </template>

            </v-data-table-server>    

            <ActionDialogFeatures 
                :action = 'selectedAction'
                :actions = 'bulkActions'
                :queue = 'actionQueue'
                @onClose='handleCloseActions'
                @onSave='handleSaveActions'
                @onSuccess='handleSuccessActions'
                @afterLeave='handleCloseActions'
            />   

        </v-sheet>`;

export default {
    inject: ['bootstrap'],
    setup(props) {
        let data = {
            url: '/admin/features/grid',
            gridData: {
                sidx: 'identifier',
                sord: 'ASC',
                rows_per_page: 25,
                name: null,
                code: null,
                language: null,
                installed: null,
                enabled: null,
            },

            searchFields: [
                'name', 'code', 'language', 'installed', 'enabled'
            ],
        };

        return useGrid(data, props);
    },
    components: {
        ActionDialogFeatures,
        TruncateTooltip,
        ChipBool,
    },
    template: template, 
    data() {
        return { 
            chipProps: {
                size: 'small',
                density: 'comfortable'
            },
            selectedAction: null,
            actionQueue: null,
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
            ]
        }
    },
    computed: {
        headers() {
            var cols = [];

            cols.push({title: 'Name', key: 'name', width: 200});
            cols.push({title: 'Code', key: 'code', width: 180});
            cols.push({title: 'Language', key: 'language_name', width: 150});
            cols.push({title: 'Description', key: 'description', width: 280});
            cols.push({title: 'Installed', key: 'installed', width: 100, searchComponent: 'v-select', searchProps: {
                'items': [{title: 'Yes', value: 1}, {title: 'No', value: 0}],
                'item-title': 'title',
                'item-value': 'value',
                'clearable': true,
            }, align: 'center'});
            cols.push({title: 'Enabled', key: 'enabled', width: 100, searchComponent: 'v-select', searchProps: {
                'items': [{title: 'Yes', value: 1}, {title: 'No', value: 0}],
                'item-title': 'title',
                'item-value': 'value',
                'clearable': true,
            }, align: 'center'});
            
            cols.push({title: '', key: 'actions', sortable: false, width: 100, searchable: false, align: 'end'});

            return cols;
        },
        hasRowSelections() {
            return this.rowSelections.length > 0;
        }
    },
    methods: {        
        refreshGrid() {
            this.gridRefresh();
        },
        refreshGridClearSelections() {
            this.rowSelections = [];
            this.refreshGrid();
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
    }
}
