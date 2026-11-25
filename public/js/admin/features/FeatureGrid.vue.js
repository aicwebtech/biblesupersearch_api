import TruncateTooltip from '../../bin/custom_vue/components/Truncate.vue.js';
import YesNoSel from '../../bin/custom_vue/components/YesNoSelector.vue.js';
import ChipBool from '../../bin/custom_vue/components/ChipBool.vue.js';
import ActionDialogFeatures from './dialogs/ActionDialog.vue.js';
import Confirm from '../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';
import { gridTemplateProps, useGrid } from '../../bin/custom_vue/composables/grid/Grid.vue.js';

 const template = `<v-sheet class='feature-grid-container'>
            <h2 class='app'>
                Features
            </h2>

            <v-sheet v-for='(feature, identifier) in allFeatures' :key='identifier' class='feature-all-row mt-3'>
                <span class='mr-2'>{{feature.name}}:</span>
                <v-btn
                    v-for='action in feature.actions'
                    :key='action'
                    size='small'
                    class='mr-2'
                    :prepend-icon='allActionSettings[action].icon'
                    :loading='allActionRunning === identifier + action'
                    :disabled='!!allActionRunning'
                    @click='handleAllAction(identifier, feature, action)'
                >
                    {{allActionSettings[action].label}}
                </v-btn>
            </v-sheet>

            <v-alert
                v-if='allActionErrors.length'
                type='error'
                class='mt-3'
                closable
                @click:close='allActionErrors = []'
            >
                <div v-for='error in allActionErrors'>{{error}}</div>
            </v-alert>

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

            <Confirm ref='ConfirmAll' :title='allActionConfirmTitle'></Confirm>
            <Confirm ref='ConfirmEnable' title='Enable' okOption='Yes' cancelOption='No' persistent></Confirm>
            <Confirm ref='ConfirmLosses' :title='allActionConfirmTitle' okOption='Uninstall anyway' cancelOption='Cancel' persistent></Confirm>

            <v-overlay
                :model-value='!!allActionRunning'
                persistent
                class='align-center justify-center'
            >
                <v-card class='pa-6 text-center' min-width='300'>
                    <v-progress-circular indeterminate size='48' class='mb-4'></v-progress-circular>
                    <div>{{allActionRunningText}}</div>
                </v-card>
            </v-overlay>

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
                'name', 'language_name', 'installed', 'enabled'
            ],
        };

        return useGrid(data, props);
    },
    components: {
        ActionDialogFeatures,
        TruncateTooltip,
        ChipBool,
        YesNoSel,
        Confirm
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
            allActionRunning: null,
            allActionRunningText: null,
            allActionConfirmTitle: null,
            allActionErrors: [],
            allActionSettings: {
                install: {label: 'Install All', actioning: 'Installing', icon: 'mdi-plus-box', confirm: 'Install every {name} preset, in every language?', askEnable: true},
                enable: {label: 'Enable All', actioning: 'Enabling', icon: 'mdi-lock-open', confirm: 'Enable every installed {name} volume, in every language?'},
                disable: {label: 'Disable All', actioning: 'Disabling', icon: 'mdi-lock', confirm: 'Disable every enabled {name} volume, in every language, including the default?\n\nThis drops the current default. When enabled again, the standard default dictionary becomes the default if it is among them, otherwise the first volume enabled. The volumes can be enabled again later.', checkLosses: false},
                uninstall: {label: 'Uninstall All', actioning: 'Uninstalling', icon: 'mdi-minus-box', confirm: 'Uninstall every {name} volume, in every language, including the default?\n\nThis drops the current default. When installed and enabled again, the standard default dictionary becomes the default if it is among them, otherwise the first volume enabled. Volumes with a module file can be installed again later.', checkLosses: true},
            },
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

            cols.push({title: 'Name', key: 'name', width: 200, searchable: false});
            cols.push({title: 'Language', key: 'language_name', width: 150, searchable: false});
            cols.push({title: 'Description', key: 'description', searchable: false, sortable: false});
            cols.push({title: 'Installed', key: 'installed', width: 100, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
            cols.push({title: 'Enabled', key: 'enabled', width: 100, searchComponent: 'YesNoSel', searchLabel: false, align: 'center'});
            
            cols.push({title: '', key: 'actions', sortable: false, width: 100, searchable: false, align: 'end'});

            return cols;
        },
        hasRowSelections() {
            return this.rowSelections.length > 0;
        },
        // Features with actions over every language at once (FeatureDefinitions::allActions())
        allFeatures() {
            return this.bootstrap.feature_all_actions || {};
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
            // Selections are cleared by handleSuccessActions(), only when every item succeeded
            this.refreshGrid();
        },
        handleSuccessActions() {
            this.rowSelections = [];
        },
        handleAllAction(identifier, feature, action) {
            var settings = this.allActionSettings[action];

            this.allActionConfirmTitle = settings.label + ': ' + feature.name;

            this.$refs.ConfirmAll.confirmSingle(settings.confirm.replace('{name}', feature.name), function(confirmed) {
                if(!confirmed) {
                    return;
                }

                if(settings.checkLosses) {
                    this.confirmAllLosses(identifier, feature, action);
                    return;
                }

                if(!settings.askEnable) {
                    this.runAllAction(identifier, feature, action, {});
                    return;
                }

                // A dialog of its own: ConfirmDialog closes itself after running the callback,
                // so reopening the same one from here would shut it again at once
                this.$refs.ConfirmEnable.confirmSingle('Enable them too?', function(enable) {
                    this.runAllAction(identifier, feature, action, {enable: enable ? 1 : 0});
                }.bind(this));
            }.bind(this));
        },
        // A second question when the action would lose volumes for good: those with no module
        // file to install them again from (imported from a CSV or Excel file ...)
        confirmAllLosses(identifier, feature, action) {
            axios.get('/admin/features/all/' + action + '/' + identifier + '/losses')
            .then(function(response) {
                var losses = (response.data && response.data.losses) || [];

                if(!losses.length) {
                    this.runAllAction(identifier, feature, action, {});
                    return;
                }

                var message = 'These volumes have no module file, so uninstalling them deletes their content for good. They cannot be installed again:\n\n'
                    + losses.map(name => '• ' + name).join('\n')
                    + '\n\nUninstall them anyway?';

                this.$refs.ConfirmLosses.confirmSingle(message, function(confirmed) {
                    if(confirmed) {
                        this.runAllAction(identifier, feature, action, {});
                    }
                }.bind(this));
            }.bind(this))
            .catch(function() {
                this.allActionErrors = ['Could not check which volumes would be lost. Nothing was uninstalled.'];
            }.bind(this));
        },
        runAllAction(identifier, feature, action, params) {
            this.allActionRunningText = this.allActionSettings[action].actioning + ' all ' + feature.name + ' ...';
            this.allActionErrors = [];
            this.allActionRunning = identifier + action;

            axios.request({
                url: '/admin/features/all/' + action + '/' + identifier,
                method: 'POST',
                params: params,
            })
            .then(function(response) {
                if(response.data.success == false) {
                    this.allActionErrors = response.data.errors || ['An unknown error occurred'];
                }
            }.bind(this))
            .catch(function(error) {
                var data = (error.response && error.response.data) || {};
                this.allActionErrors = Array.isArray(data.errors) ? data.errors : ['An unknown error occurred'];
            }.bind(this))
            .finally(function() {
                this.allActionRunning = null;
                this.refreshGridClearSelections();
            }.bind(this));
        },
    }
}
